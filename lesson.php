<?php
// lesson.php – stránka jednej lekcie s testom
$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

// získame ID lekcie z URL, napr. lesson.php?id=3
$lessonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($lessonId <= 0) {
    http_response_code(400);
    echo 'Neplatné ID lekcie.';
    exit;
}

// načítame lekciu + kurz
$stmt = $pdo->prepare("
    SELECT l.*, c.title AS course_title
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    WHERE l.id = :id
");
$stmt->execute(['id' => $lessonId]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    http_response_code(404);
    echo 'Lekcia neexistuje.';
    exit;
}

// načítame otázky k lekcii
$quizStmt = $pdo->prepare("
    SELECT *
    FROM quizzes
    WHERE lesson_id = :id
    ORDER BY id
");
$quizStmt->execute(['id' => $lessonId]);
$quizzes = $quizStmt->fetchAll(PDO::FETCH_ASSOC);

// spracovanie odpovedí (ak používateľ odoslal formulár)
$evaluation = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($quizzes)) {
    $total = count($quizzes);
    $correct = 0;
    $answers = [];

    foreach ($quizzes as $q) {
        $qid = $q['id'];
        $userAnswer = isset($_POST['answer'][$qid]) ? $_POST['answer'][$qid] : null;
        $answers[$qid] = $userAnswer;

        if ($userAnswer !== null && $userAnswer === $q['correct_option']) {
            $correct++;
        }
    }

    $evaluation = [
        'total'   => $total,
        'correct' => $correct,
        'answers' => $answers,
    ];

    // ak je používateľ prihlásený, uložíme výsledok do databázy
    if (is_logged_in()) {
        $userId = current_user_id();

        $insert = $pdo->prepare("
            INSERT INTO results (user_id, lesson_id, correct, total)
            VALUES (:user_id, :lesson_id, :correct, :total)
        ");
        $insert->execute([
            'user_id'   => $userId,
            'lesson_id' => $lessonId,
            'correct'   => $correct,
            'total'     => $total,
        ]);
    }
}


// pomocná funkcia na escapovanie HTML
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title><?= h($lesson['course_title']) ?> – <?= h($lesson['title']) ?></title>
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #0f172a;
            color: #e5e7eb;
        }
        header {
            background: #020617;
            border-bottom: 1px solid #1e293b;
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-weight: 700;
            font-size: 20px;
        }
        .logo span {
            color: #38bdf8;
        }
        .breadcrumbs {
            font-size: 14px;
            color: #9ca3af;
        }
        .breadcrumbs a {
            color: #e5e7eb;
            text-decoration: none;
        }
        .breadcrumbs a:hover {
            text-decoration: underline;
        }
        main {
            max-width: 960px;
            margin: 32px auto 80px;
            padding: 0 16px;
        }
        h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }
        .meta {
            font-size: 14px;
            color: #9ca3af;
            margin-bottom: 24px;
        }
        .lesson-content {
            background: #020617;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #1e293b;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        .lesson-content h2 {
            font-size: 24px;
            margin-top: 32px;
            margin-bottom: 16px;
            color: #e5e7eb;
            border-bottom: 1px solid #1e293b;
            padding-bottom: 8px;
        }
        .lesson-content h2:first-child {
            margin-top: 0;
        }
        .lesson-content h3 {
            font-size: 20px;
            margin-top: 24px;
            margin-bottom: 12px;
            color: #cbd5e1;
        }
        .lesson-content p {
            margin-bottom: 16px;
            color: #e5e7eb;
        }
        .lesson-content ul, .lesson-content ol {
            margin-bottom: 16px;
            padding-left: 24px;
            color: #e5e7eb;
        }
        .lesson-content li {
            margin-bottom: 8px;
        }
        .lesson-content code {
            background: #0b1220;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            color: #38bdf8;
            border: 1px solid #1e293b;
        }
        .lesson-content pre {
            background: #0b1220;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #1e293b;
            overflow-x: auto;
            margin-bottom: 16px;
        }
        .lesson-content pre code {
            background: transparent;
            padding: 0;
            border: none;
            color: #e5e7eb;
            font-size: 14px;
        }
        .lesson-content strong {
            color: #cbd5e1;
            font-weight: 600;
        }
        .quiz-block {
            background: #020617;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #1e293b;
        }
        .quiz-title {
            font-size: 20px;
            margin-bottom: 16px;
        }
        .question {
            margin-bottom: 20px;
        }
        .question-text {
            margin-bottom: 8px;
            font-weight: 500;
        }
        label {
            display: block;
            margin-bottom: 4px;
            cursor: pointer;
        }
        .btn-primary {
            margin-top: 8px;
            background: #38bdf8;
            color: #020617;
            border: none;
            padding: 10px 18px;
            border-radius: 999px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #0ea5e9;
        }
        .result-box {
            margin-top: 16px;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
        }
        .result-ok {
            background: rgba(22, 163, 74, 0.15);
            border: 1px solid #22c55e;
        }
        .result-bad {
            background: rgba(220, 38, 38, 0.15);
            border: 1px solid #ef4444;
        }
    </style>
</head>
<body>
<header>
    <div class="logo">E-<span>Learn</span></div>
    <nav class="breadcrumbs">
        <a href="index.php">Kurzy</a>
        <span>›</span>
        <span><?= h($lesson['course_title']) ?></span>
        <span>›</span>
        <span><?= h($lesson['title']) ?></span>
    </nav>
</header>

<main>
    <h1><?= h($lesson['title']) ?></h1>
    <p class="meta">
        Kurz: <strong><?= h($lesson['course_title']) ?></strong>
        <?php if ($lesson['estimated_min'] !== null): ?>
            · Odhadovaný čas: <?= (int)$lesson['estimated_min'] ?> min
        <?php endif; ?>
    </p>

    <section class="lesson-content">
        <?= $lesson['content'] ?>
    </section>

    <?php if (!empty($quizzes)): ?>
        <section class="quiz-block">
            <div class="quiz-title">Krátky test k lekcii</div>

            <form method="post">
                <?php foreach ($quizzes as $index => $q): ?>
                    <?php
                    $qid = $q['id'];
                    $userAnswer = $evaluation['answers'][$qid] ?? null;
                    ?>
                    <div class="question">
                        <div class="question-text">
                            <?= ($index + 1) ?>. <?= h($q['question']) ?>
                        </div>

                        <?php foreach (['a', 'b', 'c', 'd'] as $opt): ?>
                            <?php
                            $field = 'option_' . $opt;
                            if ($q[$field] === null || $q[$field] === '') continue;
                            $value = $opt;
                            ?>
                            <label>
                                <input
                                        type="radio"
                                        name="answer[<?= $qid ?>]"
                                        value="<?= $value ?>"
                                    <?= $userAnswer === $value ? 'checked' : '' ?>
                                >
                                <?= h($q[$field]) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn-primary">Odoslať odpovede</button>
            </form>

            <?php if ($evaluation !== null): ?>
                <?php
                $ok = $evaluation['correct'] >= ceil($evaluation['total'] * 0.6);
                ?>
                <div class="result-box <?= $ok ? 'result-ok' : 'result-bad' ?>">
                    Správne odpovede:
                    <strong><?= $evaluation['correct'] ?> z <?= $evaluation['total'] ?></strong>.
                    <?= $ok ? 'Výborne, môžeš pokračovať ďalej.' : 'Skús si lekciu ešte raz prejsť a zopakovať test.' ?>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="quiz-block">
            <div class="quiz-title">Test k lekcii</div>
            <p>K tejto lekcii zatiaľ nie sú priradené žiadne otázky.</p>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
