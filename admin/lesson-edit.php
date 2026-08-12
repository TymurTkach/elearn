<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

// Učitelia a admini môžu vstúpiť do administrácie
if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

$lessonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($lessonId <= 0) {
    header('Location: courses.php');
    exit;
}

// Načítame lekciu
$stmt = $pdo->prepare('
    SELECT l.*, c.title AS course_title, c.teacher_id
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    WHERE l.id = :id
');
$stmt->execute(['id' => $lessonId]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    header('Location: courses.php');
    exit;
}

// Kontrolujeme práva prístupu: učiteľ môže upravovať len lekcie vo svojich kurzoch
if (is_teacher()) {
    $teacherId = current_user_teacher_id();
    if (!$teacherId) {
        $teacherId = get_user_teacher_id($pdo, current_user_id());
        if ($teacherId) {
            $_SESSION['teacher_id'] = $teacherId;
        }
    }
    if ($lesson['teacher_id'] != $teacherId) {
        header('Location: courses.php');
        exit;
    }
}

$errors = [];
$title = $lesson['title'];
$content = $lesson['content'];
$order_index = $lesson['order_index'];
$estimated_min = $lesson['estimated_min'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $order_index = isset($_POST['order_index']) ? (int)$_POST['order_index'] : 1;
    $estimated_min = isset($_POST['estimated_min']) && $_POST['estimated_min'] !== '' ? (int)$_POST['estimated_min'] : null;

    if ($title === '' || mb_strlen($title) < 2) {
        $errors[] = 'Názov lekcie musí mať aspoň 2 znaky.';
    }

    if ($content === '') {
        $errors[] = 'Obsah lekcie nemôže byť prázdny.';
    }

    if ($order_index < 1) {
        $order_index = 1;
    }

    if (!$errors) {
        $upd = $pdo->prepare('
            UPDATE lessons
            SET title = :title, content = :content, order_index = :order_index, estimated_min = :estimated_min
            WHERE id = :id
        ');
        $upd->execute([
            'title' => $title,
            'content' => $content,
            'order_index' => $order_index,
            'estimated_min' => $estimated_min,
            'id' => $lessonId,
        ]);
        header('Location: lessons.php?course_id=' . (int)$lesson['course_id']);
        exit;
    }
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Upraviť lekciu – Administrácia</title>
    <style>
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#0f172a;color:#e5e7eb}
        .wrap{max-width:1000px;margin:40px auto;padding:0 16px}
        .card{background:#020617;border:1px solid #1e293b;border-radius:16px;padding:20px;margin-bottom:14px}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        label{display:block;margin:10px 0 6px;color:#cbd5e1;font-size:14px}
        input,textarea{width:100%;box-sizing:border-box;padding:10px 12px;border-radius:10px;border:1px solid #1e293b;background:#0b1220;color:#e5e7eb}
        textarea{min-height:200px;resize:vertical;font-family:inherit}
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
        <div class="muted"><a href="lessons.php?course_id=<?= (int)$lesson['course_id'] ?>">← Späť na lekcie</a></div>
        <h1>Upraviť lekciu</h1>
        <p class="muted">Kurz: <strong><?= h($lesson['course_title']) ?></strong></p>

        <?php if ($errors): ?>
            <div class="err">
                <?php foreach ($errors as $e): ?><div>• <?= h($e) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="row">
                <div>
                    <label for="title">Názov lekcie</label>
                    <input id="title" name="title" value="<?= h($title) ?>" required>
                </div>
                <div>
                    <label for="order_index">Poradie</label>
                    <input id="order_index" name="order_index" type="number" min="1" value="<?= (int)$order_index ?>" required>
                </div>
            </div>
            <div class="row">
                <div>
                    <label for="estimated_min">Odhadovaný čas (minúty, voliteľné)</label>
                    <input id="estimated_min" name="estimated_min" type="number" min="1" value="<?= $estimated_min ? (int)$estimated_min : '' ?>">
                </div>
                <div></div>
            </div>
            <label for="content">Obsah lekcie</label>
            <textarea id="content" name="content" required><?= h($content) ?></textarea>

            <button class="btn" type="submit">Uložiť zmeny</button>
            <a href="lessons.php?course_id=<?= (int)$lesson['course_id'] ?>" class="btn btn-secondary">Zrušiť</a>
        </form>
    </div>
</div>
</body>
</html>

