<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

// Učitelia a admini môžu vstúpiť do administrácie
if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

$lessonId = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
if ($lessonId <= 0) {
    header('Location: courses.php');
    exit;
}

// Načítame lekciu a kurz
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

// Kontrolujeme práva prístupu: učiteľ môže vidieť len otázky vo svojich lekciách
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

// Načítame otázky
$stmt = $pdo->prepare('
    SELECT id, question, option_a, option_b, option_c, option_d, correct_option
    FROM quizzes
    WHERE lesson_id = :lesson_id
    ORDER BY id ASC
');
$stmt->execute(['lesson_id' => $lessonId]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Otázky – Administrácia</title>
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
        .correct{color:#22c55e;font-weight:600}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="muted"><a href="lessons.php?course_id=<?= (int)$lesson['course_id'] ?>">← Späť na lekcie</a></div>
        <h1>Otázky: <?= h($lesson['title']) ?></h1>
        <p class="muted">Kurz: <strong><?= h($lesson['course_title']) ?></strong></p>

        <a href="quiz-create.php?lesson_id=<?= (int)$lessonId ?>" class="btn">Pridať novú otázku</a>

        <table style="margin-top:20px">
            <thead>
            <tr>
                <th>ID</th>
                <th>Otázka</th>
                <th>Možnosti</th>
                <th>Správna odpoveď</th>
                <th>Akcie</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($quizzes)): ?>
                <tr>
                    <td colspan="5" class="muted">Zatiaľ nie sú pridané žiadne otázky.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($quizzes as $q): ?>
                    <tr>
                        <td><?= (int)$q['id'] ?></td>
                        <td><strong><?= h($q['question']) ?></strong></td>
                        <td class="muted" style="font-size:12px">
                            <?php
                            $options = [];
                            if ($q['option_a']) $options[] = 'A: ' . h($q['option_a']);
                            if ($q['option_b']) $options[] = 'B: ' . h($q['option_b']);
                            if ($q['option_c']) $options[] = 'C: ' . h($q['option_c']);
                            if ($q['option_d']) $options[] = 'D: ' . h($q['option_d']);
                            echo implode('<br>', $options);
                            ?>
                        </td>
                        <td class="correct"><?= strtoupper($q['correct_option'] ?? '-') ?></td>
                        <td class="actions">
                            <a href="quiz-edit.php?id=<?= (int)$q['id'] ?>">Upraviť</a>
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

