<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

// Учителя и админы могут заходить в админку
if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($courseId <= 0) {
    header('Location: courses.php');
    exit;
}

// Načítame kurz
$stmt = $pdo->prepare('SELECT id, title, description, image, teacher_id FROM courses WHERE id = :id');
$stmt->execute(['id' => $courseId]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: courses.php');
    exit;
}

// Kontrolujeme práva prístupu: učiteľ môže upravovať len svoje kurzy
if (is_teacher()) {
    $teacherId = current_user_teacher_id();
    if (!$teacherId) {
        $teacherId = get_user_teacher_id($pdo, current_user_id());
        if ($teacherId) {
            $_SESSION['teacher_id'] = $teacherId;
        }
    }
    if ($course['teacher_id'] != $teacherId) {
        header('Location: courses.php');
        exit;
    }
}

$errors = [];

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
$title = $course['title'];
$description = $course['description'];
$currentImage = $course['image'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));

    if ($title === '' || mb_strlen($title) < 2) {
        $errors[] = 'Názov kurzu musí mať aspoň 2 znaky.';
    }

    $imagePath = $currentImage;
    
    // Удаление изображения, если пользователь выбрал "удалить"
    if (isset($_POST['delete_image']) && $_POST['delete_image'] === '1') {
        if ($currentImage && file_exists(dirname(__DIR__) . '/' . $currentImage)) {
            unlink(dirname(__DIR__) . '/' . $currentImage);
        }
        $imagePath = null;
    }
    
    // Загрузка нового изображения
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = 'Povolené sú len obrázky (JPEG, PNG, GIF, WebP).';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'Obrázok je príliš veľký. Maximálna veľkosť je 5MB.';
        } else {
            // Удаляем старое изображение, если есть
            if ($currentImage && file_exists(dirname(__DIR__) . '/' . $currentImage)) {
                unlink(dirname(__DIR__) . '/' . $currentImage);
            }
            
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
        if ($imagePath !== $currentImage) {
            $upd = $pdo->prepare('UPDATE courses SET title = :t, description = :d, image = :img WHERE id = :id');
            $upd->execute(['t' => $title, 'd' => $description, 'img' => $imagePath, 'id' => $courseId]);
        } else {
            $upd = $pdo->prepare('UPDATE courses SET title = :t, description = :d WHERE id = :id');
            $upd->execute(['t' => $title, 'd' => $description, 'id' => $courseId]);
        }
        header('Location: courses.php');
        exit;
    }
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Upraviť kurz – Administrácia</title>
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
        .btn-secondary{margin-left:8px;background:#475569;color:#e5e7eb}
        .btn-secondary:hover{background:#64748b}
        a{color:#e5e7eb;text-decoration:none}
        a:hover{text-decoration:underline}
        .muted{color:#9ca3af}
        .err{margin:10px 0;padding:10px 12px;border-radius:12px;background:rgba(220,38,38,.15);border:1px solid #ef4444;font-size:14px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="muted"><a href="courses.php">← Späť na kurzy</a></div>
        <h1>Upraviť kurz</h1>

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
                <?php if ($currentImage): ?>
                    <div style="margin-bottom:10px;">
                        <img src="../<?= h($currentImage) ?>" alt="Aktuálny obrázok" style="max-width:200px;max-height:150px;border-radius:8px;border:1px solid #1e293b;object-fit:cover;">
                        <div style="margin-top:8px;">
                            <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                                <input type="checkbox" name="delete_image" value="1" style="width:auto;">
                                <span style="font-size:13px;color:#ef4444;">Odstrániť obrázok</span>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
                <input type="file" id="image" name="image" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <small class="muted" style="display:block;margin-top:4px;font-size:12px;">Povolené formáty: JPEG, PNG, GIF, WebP (max. 5MB)</small>
            </div>
            <button class="btn" type="submit">Uložiť zmeny</button>
            <a href="courses.php" class="btn btn-secondary">Zrušiť</a>
        </form>
    </div>
</div>
</body>
</html>

