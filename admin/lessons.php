<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

// Учителя и админы могут заходить в админку
if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if ($courseId <= 0) {
    header('Location: courses.php');
    exit;
}

// Načítame kurz
$stmt = $pdo->prepare('SELECT id, title, teacher_id FROM courses WHERE id = :id');
$stmt->execute(['id' => $courseId]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    header('Location: courses.php');
    exit;
}

// Kontrolujeme práva prístupu: učiteľ môže vidieť len svoje kurzy
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

// Načítame lekcie
$stmt = $pdo->prepare('
    SELECT id, title, order_index, estimated_min, created_at
    FROM lessons
    WHERE course_id = :course_id
    ORDER BY order_index ASC, created_at ASC
');
$stmt->execute(['course_id' => $courseId]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Lekcie – Administrácia</title>
    <style>
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#0f172a;color:#e5e7eb}
        .wrap{max-width:1000px;margin:40px auto;padding:0 16px}
        .card{background:#020617;border:1px solid #1e293b;border-radius:16px;padding:20px;margin-bottom:14px}
        a{color:#e5e7eb;text-decoration:none}
        a:hover{text-decoration:underline}
        .muted{color:#9ca3af}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px;border-bottom:1px solid #1e293b;text-align:left;vertical-align:top}
        th{color:#cbd5e1;font-size:13px}
        .actions a{margin-right:10px}
        .btn{display:inline-block;margin-top:12px;background:#38bdf8;color:#020617;border:none;padding:10px 18px;border-radius:999px;font-weight:800;cursor:pointer;text-decoration:none}
        .btn:hover{background:#0ea5e9}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="muted"><a href="courses.php">← Kurzy</a></div>
        <h1>Lekcie: <?= h($course['title']) ?></h1>
        <p class="muted">Správa lekcií pre tento kurz.</p>

        <a href="lesson-create.php?course_id=<?= (int)$courseId ?>" class="btn">Pridať novú lekciu</a>

        <table style="margin-top:20px">
            <thead>
            <tr>
                <th>Poradie</th>
                <th>Názov</th>
                <th>Odhadovaný čas (min)</th>
                <th>Vytvorené</th>
                <th>Akcie</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($lessons)): ?>
                <tr>
                    <td colspan="5" class="muted">Zatiaľ nie sú pridané žiadne lekcie.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($lessons as $l): ?>
                    <tr>
                        <td><?= (int)$l['order_index'] ?></td>
                        <td><strong><?= h($l['title']) ?></strong></td>
                        <td><?= $l['estimated_min'] ? (int)$l['estimated_min'] : '-' ?></td>
                        <td class="muted"><?= h($l['created_at']) ?></td>
                        <td class="actions">
                            <a href="lesson-edit.php?id=<?= (int)$l['id'] ?>">Upraviť</a>
                            <a href="quizzes.php?lesson_id=<?= (int)$l['id'] ?>">Otázky</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>

