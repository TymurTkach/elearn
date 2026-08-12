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

// Создаем папку для загрузки изображений, если её нет
$uploadDir = dirname(__DIR__) . '/uploads/courses/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        $errors[] = 'Nepodarilo sa vytvoriť priečinok pre obrázky. Skontrolujte oprávnenia.';
    }
}
// Проверяем права на запись
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

            // Проверяем, что папка существует и доступна для записи
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
    <style>
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#0f172a;color:#e5e7eb}
        .wrap{max-width:1000px;margin:40px auto;padding:0 16px}
        .card{background:#020617;border:1px solid #1e293b;border-radius:16px;padding:20px;margin-bottom:14px}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        label{display:block;margin:10px 0 6px;color:#cbd5e1;font-size:14px}
        input,textarea{width:100%;box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid #1e293b;background:#0b1220;color:#e5e7eb}
        input[type="file"]{padding:8px 12px;cursor:pointer}
        input[type="file"]::-webkit-file-upload-button{background:#38bdf8;color:#020617;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;margin-right:8px;font-weight:600}
        input[type="file"]::-webkit-file-upload-button:hover{background:#0ea5e9}
        textarea{min-height:90px;resize:vertical}
        .btn{margin-top:12px;background:#38bdf8;color:#020617;border:none;padding:10px 18px;border-radius:999px;font-weight:800;cursor:pointer}
        .btn:hover{background:#0ea5e9}
        a{color:#e5e7eb;text-decoration:none}
        a:hover{text-decoration:underline}
        .muted{color:#9ca3af}
        .err{margin:10px 0;padding:10px 12px;border-radius:12px;background:rgba(220,38,38,.15);border:1px solid #ef4444;font-size:14px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px;border-bottom:1px solid #1e293b;text-align:left;vertical-align:top}
        th{color:#cbd5e1;font-size:13px}
        .actions a{margin-right:10px}
        .pill{display:inline-block;padding:4px 10px;border-radius:999px;border:1px solid #1e293b;background:#0b1220;font-size:12px;color:#cbd5e1}
    </style>
</head>
<body>
<div class="wrap">

    <div class="card">
        <div class="muted"><a href="index.php">← Administrácia</a></div>
        <h1>Kurzy</h1>
        <p class="muted">Pridaj nový kurz alebo uprav existujúci.</p>

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
                <small class="muted" style="display:block;margin-top:4px;font-size:12px;">Povolené formáty: JPEG, PNG, GIF, WebP (max. 5MB)</small>
            </div>
            <button class="btn" type="submit">Pridať kurz</button>
        </form>
    </div>

    <div class="card">
        <h2>Zoznam kurzov <span class="pill"><?= count($courses) ?></span></h2>
        <table>
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
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
