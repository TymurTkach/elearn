<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

// Učitelia a admini môžu vstúpiť do administrácie
if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

$errors = [];
$title = '';
$description = '';

if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0) {
        $teacherId = null;
        if (is_teacher()) {
            $teacherId = current_user_teacher_id();
            if (!$teacherId) {
                $teacherId = get_user_teacher_id($pdo, current_user_id());
                if ($teacherId) {
                    $_SESSION['teacher_id'] = $teacherId;
                }
            }
        }

        $checkStmt = $pdo->prepare('SELECT id, teacher_id, image FROM courses WHERE id = :id');
        $checkStmt->execute(['id' => $deleteId]);
        $courseToDelete = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($courseToDelete) {
            if (is_teacher() && $courseToDelete['teacher_id'] != $teacherId) {
                header('Location: courses.php');
                exit;
            }

            $pdo->beginTransaction();
            try {
                $lessonsStmt = $pdo->prepare('SELECT id FROM lessons WHERE course_id = :course_id');
                $lessonsStmt->execute(['course_id' => $deleteId]);
                $lessonIds = $lessonsStmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($lessonIds)) {
                    $placeholders = implode(',', array_fill(0, count($lessonIds), '?'));
                    $resultsStmt = $pdo->prepare("DELETE FROM results WHERE lesson_id IN ($placeholders)");
                    $resultsStmt->execute($lessonIds);

                    $quizzesStmt = $pdo->prepare("DELETE FROM quizzes WHERE lesson_id IN ($placeholders)");
                    $quizzesStmt->execute($lessonIds);

                    $lessonsDeleteStmt = $pdo->prepare('DELETE FROM lessons WHERE course_id = :course_id');
                    $lessonsDeleteStmt->execute(['course_id' => $deleteId]);
                }

                if ($courseToDelete['image'] && file_exists(dirname(__DIR__) . '/' . $courseToDelete['image'])) {
                    @unlink(dirname(__DIR__) . '/' . $courseToDelete['image']);
                }

                $deleteStmt = $pdo->prepare('DELETE FROM courses WHERE id = :id');
                $deleteStmt->execute(['id' => $deleteId]);

                $pdo->commit();
                header('Location: courses.php?deleted=1');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Chyba pri odstraňovaní kurzu: ' . $e->getMessage();
            }
        }
    }
}

$uploadDir = dirname(__DIR__) . '/uploads/courses/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        $errors[] = 'Nepodarilo sa vytvoriť priečinok pre obrázky. Skontrolujte oprávnenia.';
    }
}

if (is_dir($uploadDir) && !is_writable($uploadDir)) {
    @chmod($uploadDir, 0755);
    if (!is_writable($uploadDir)) {
        $errors[] = 'Priečinok pre obrázky nie je zapisovateľný. Skontrolujte oprávnenia.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));

    if ($title === '' || mb_strlen($title) < 2) {
        $errors[] = 'Názov kurzu musí mať aspoň 2 znaky.';
    }

    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = 'Povolené sú len obrázky (JPEG, PNG, GIF, WebP).';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'Obrázok je príliš veľký. Maximálna veľkosť je 5MB.';
        } else {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fileName = uniqid('course_', true) . '.' . $extension;
            $targetPath = $uploadDir . $fileName;
            
            if (!is_dir($uploadDir)) {
                $errors[] = 'Priečinok pre obrázky neexistuje.';
            } elseif (!is_writable($uploadDir)) {
                $errors[] = 'Priečinok pre obrázky nie je zapisovateľný.';
            } elseif (!is_uploaded_file($file['tmp_name'])) {
                $errors[] = 'Súbor nebol správne nahraný.';
            } elseif (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $imagePath = 'uploads/courses/' . $fileName;
            } else {
                $errors[] = 'Chyba pri nahrávaní obrázka. Skontrolujte oprávnenia priečinka.';
            }
        }
    }

    if (!$errors) {
        $teacherId = null;
        if (is_teacher()) {
            $teacherId = current_user_teacher_id();
            if (!$teacherId) {
                $teacherId = get_user_teacher_id($pdo, current_user_id());
                if ($teacherId) {
                    $_SESSION['teacher_id'] = $teacherId;
                }
            }
        }

        if ($imagePath) {
            $ins = $pdo->prepare("INSERT INTO courses (title, description, image, teacher_id, created_at) VALUES (:t, :d, :img, :teacher_id, NOW())");
            $ins->execute(['t' => $title, 'd' => $description, 'img' => $imagePath, 'teacher_id' => $teacherId]);
        } else {
            $ins = $pdo->prepare("INSERT INTO courses (title, description, teacher_id, created_at) VALUES (:t, :d, :teacher_id, NOW())");
            $ins->execute(['t' => $title, 'd' => $description, 'teacher_id' => $teacherId]);
        }
        header('Location: courses.php');
        exit;
    }
}

// Načítame kurzy s ohľadom na teacher_id
$teacherId = null;
if (is_teacher()) {
    $teacherId = current_user_teacher_id();
    if (!$teacherId) {
        $teacherId = get_user_teacher_id($pdo, current_user_id());
        if ($teacherId) {
            $_SESSION['teacher_id'] = $teacherId;
        }
    }
}

if ($teacherId !== null) {
    $stmt = $pdo->prepare("SELECT id, title, description, image, created_at FROM courses WHERE teacher_id = :teacher_id ORDER BY created_at DESC");
    $stmt->execute(['teacher_id' => $teacherId]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Admin vidí všetky kurzy
    $stmt = $pdo->query("SELECT id, title, description, image, created_at FROM courses ORDER BY created_at DESC");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Kurzy – Administrácia</title>
    <link rel="stylesheet" href="../styles.css">
    <script src="../theme.js" defer></script>
</head>
<body class="admin-body">
<div class="admin-wrap">

    <div class="admin-card">
        <div class="muted"><a href="index.php">← Administrácia</a></div>
        <h1>Kurzy</h1>
        <p class="muted">Pridaj nový kurz alebo uprav existujúci.</p>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="success">
                Kurz bol úspešne odstránený.
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="err">
                <?php foreach ($errors as $e): ?><div>• <?= h($e) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off" enctype="multipart/form-data">
            <div class="row">
                <div>
                    <label for="title">Názov</label>
                    <input id="title" name="title" value="<?= h($title) ?>" required>
                </div>
                <div>
                    <label for="description">Popis</label>
                    <textarea id="description" name="description"><?= h($description) ?></textarea>
                </div>
            </div>
            <div>
                <label for="image">Obrázok kurzu</label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <small class="muted inline-text-sm mt-4">Povolené formáty: JPEG, PNG, GIF, WebP (max. 5MB)</small>
            </div>
            <button class="btn" type="submit">Pridať kurz</button>
        </form>
    </div>

    <div class="admin-card">
        <h2>Zoznam kurzov <span class="pill"><?= count($courses) ?></span></h2>
        <table class="admin-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Názov</th>
                <th>Popis</th>
                <th>Akcie</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($courses as $c): ?>
                <tr>
                    <td><?= (int)$c['id'] ?></td>
                    <td><strong><?= h($c['title']) ?></strong></td>
                    <td class="muted"><?= h($c['description']) ?></td>
                    <td class="actions">
                        <a href="course_edit.php?id=<?= (int)$c['id'] ?>">Upraviť</a>
                        <a href="lessons.php?course_id=<?= (int)$c['id'] ?>">Lekcie</a>
                        <a href="courses.php?delete=<?= (int)$c['id'] ?>" class="delete-link" onclick="return confirm('Naozaj chcete odstrániť tento kurz? Táto akcia je nevratná a odstráni všetky lekcie a otázky.');">Zmazať</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
