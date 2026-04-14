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
    <link rel="stylesheet" href="../styles.css">
    <script src="../theme.js" defer></script>
</head>
<body class="admin-body">
<div class="admin-wrap">
    <div class="admin-card">
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

