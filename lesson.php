<?php
//Stránka jednej lekcie s testom
$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
require_once __DIR__ . '/gamification.php';

function normalize_correct_option_indexes(array $rawValues, int $optionCount): array {
    $map = ['a' => 0, 'b' => 1, 'c' => 2, 'd' => 3];
    $normalized = [];
    foreach ($rawValues as $raw) {
        $idx = null;
        if (is_int($raw) || (is_string($raw) && ctype_digit($raw))) {
            $idx = (int)$raw;
        } elseif (is_string($raw)) {
            $key = strtolower(trim($raw));
            if (isset($map[$key])) {
                $idx = $map[$key];
            }
        }
        if ($idx !== null && $idx >= 0 && $idx < $optionCount) {
            $normalized[$idx] = $idx;
        }
    }
    ksort($normalized);
    return array_values($normalized);
}

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
try {
    $quizStmt = $pdo->prepare("
        SELECT id, question, options, correct_options, option_a, option_b, option_c, option_d, correct_option
        FROM quizzes
        WHERE lesson_id = :id
        ORDER BY id
    ");
    $quizStmt->execute(['id' => $lessonId]);
    $quizzesRaw = $quizStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $quizStmt = $pdo->prepare("
        SELECT id, question, option_a, option_b, option_c, option_d, correct_option
        FROM quizzes
        WHERE lesson_id = :id
        ORDER BY id
    ");
    $quizStmt->execute(['id' => $lessonId]);
    $quizzesRaw = $quizStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($quizzesRaw as &$q) {
        $q['options'] = null;
        $q['correct_options'] = null;
    }
    unset($q);
}

$quizzes = [];
foreach ($quizzesRaw as $q) {
    $options = [];
    $correctOptions = [];

    if (!empty($q['options']) && $q['options'] !== null) {
        $decoded = json_decode($q['options'], true);
        if (is_array($decoded) && !empty($decoded)) {
            $options = array_filter($decoded, function($v) { return $v !== '' && $v !== null; });
            $options = array_values($options); // Переиндексируем массив
        }
    }

    if (!empty($q['correct_options']) && $q['correct_options'] !== null) {
        $decoded = json_decode($q['correct_options'], true);
        if (is_array($decoded) && !empty($decoded)) {
            $correctOptions = $decoded;
        }
    }

    if (empty($options)) {
        if (!empty($q['option_a'])) $options[] = $q['option_a'];
        if (!empty($q['option_b'])) $options[] = $q['option_b'];
        if (!empty($q['option_c'])) $options[] = $q['option_c'];
        if (!empty($q['option_d'])) $options[] = $q['option_d'];
        $options = array_filter($options);
        $options = array_values($options); // Переиндексируем массив
    }

    if (empty($correctOptions) && $q['correct_option']) {
        $map = ['a' => 0, 'b' => 1, 'c' => 2, 'd' => 3];
        if (isset($map[$q['correct_option']])) {
            $correctOptions = [$map[$q['correct_option']]];
        }
    }

    $q['options'] = array_values($options);
    $q['correct_options'] = normalize_correct_option_indexes($correctOptions, count($q['options']));
    $q['is_multiple'] = count($q['correct_options']) > 1;
    $quizzes[] = $q;
}

// načítame kódové úlohy k lekcii (ak existuje tabuľka)
$codeTasks = [];
try {
    $taskStmt = $pdo->prepare("
        SELECT id, title, description, expected_output, language_id, stdin, order_index
        FROM code_tasks
        WHERE lesson_id = :id
        ORDER BY order_index ASC, id ASC
    ");
    $taskStmt->execute(['id' => $lessonId]);
    $codeTasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // tabuľka code_tasks ešte neexistuje
}

// spracovanie odpovedí (ak používateľ odoslal formulár)
$evaluation = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($quizzes)) {
    $total = count($quizzes);
    $correct = 0;
    $answers = [];

    foreach ($quizzes as $q) {
        $qid = $q['id'];
        $userAnswers = [];
        if (isset($_POST['answer'][$qid])) {
            if (is_array($_POST['answer'][$qid])) {
                $userAnswers = array_map('intval', $_POST['answer'][$qid]);
            } else {
                $userAnswers = [(int)$_POST['answer'][$qid]];
            }
        }
        $answers[$qid] = $userAnswers;

        sort($userAnswers);
        $correctOptions = $q['correct_options'];
        sort($correctOptions);

        if ($userAnswers === $correctOptions) {
            $correct++;
        }
    }

    $evaluation = [
        'total'   => $total,
        'correct' => $correct,
        'answers' => $answers,
    ];

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
        gamification_after_quiz_result($pdo, $userId, $correct, $total);
    }
}


function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title><?= h($lesson['course_title']) ?> – <?= h($lesson['title']) ?></title>
    <link rel="stylesheet" href="styles.css">
    <script src="theme.js" defer></script>
</head>
<body>
<header class="page-header">
    <div class="logo">E-<span>Learn</span></div>
    <nav class="breadcrumbs">
        <a href="index.php">Kurzy</a>
        <span>›</span>
        <span><?= h($lesson['course_title']) ?></span>
        <span>›</span>
        <span><?= h($lesson['title']) ?></span>
    </nav>
</header>

<main class="container">
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

    <?php if (!empty($codeTasks)): ?>
        <?php
        $langOptions = [71 => 'Python', 62 => 'Java', 54 => 'C++'];
    foreach ($codeTasks as $ct):
        $ctId = (int)$ct['id'];
        $langId = (int)$ct['language_id'];
        ?>
        <section class="code-task-block" data-task-id="<?= $ctId ?>">
            <h2 class="code-task-title"><?= h($ct['title']) ?></h2>
            <?php if (!empty(trim((string)($ct['description'] ?? '')))): ?>
                <p class="code-task-description"><?= nl2br(h($ct['description'])) ?></p>
            <?php endif; ?>
            <p class="muted inline-text-sm">Vaša úloha: napíšte kód a stlačte Spustiť. Výstup sa porovná s očakávaným a výsledok sa uloží.</p>
            <div class="code-task-editor">
                <label class="code-task-lang-label">Jazyk:</label>
                <select class="code-task-lang" data-task-id="<?= $ctId ?>">
                    <?php foreach ($langOptions as $lid => $lname): ?>
                        <option value="<?= $lid ?>" <?= $lid === $langId ? 'selected' : '' ?>><?= h($lname) ?></option>
                    <?php endforeach; ?>
                </select>
                <textarea class="code-task-code" data-task-id="<?= $ctId ?>" rows="12" placeholder="Sem napíšte svoj kód..."></textarea>
                <button type="button" class="btn code-task-run" data-task-id="<?= $ctId ?>">Spustiť</button>
                <pre class="code-task-output" data-task-id="<?= $ctId ?>" aria-live="polite"></pre>
                <div class="code-task-result muted" data-task-id="<?= $ctId ?>" role="status"></div>
            </div>
        </section>
    <?php endforeach; ?>

        <script>
            (function() {
                var apiBase = 'api/';
                document.querySelectorAll('.code-task-run').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var taskId = parseInt(btn.getAttribute('data-task-id'), 10);
                        var block = btn.closest('.code-task-block');
                        var codeEl = block.querySelector('.code-task-code');
                        var langEl = block.querySelector('.code-task-lang');
                        var outEl = block.querySelector('.code-task-output');
                        var resEl = block.querySelector('.code-task-result');

                        outEl.textContent = 'Spúšťam…';
                        outEl.classList.remove('result-ok', 'result-bad');
                        resEl.textContent = '';
                        resEl.className = 'code-task-result muted';

                        var payload = {
                            task_id: taskId,
                            source_code: codeEl.value,
                            language_id: parseInt(langEl.value, 10),
                            stdin: ''
                        };

                        fetch(apiBase + 'run-code-task.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                outEl.textContent = data.output !== undefined && data.output !== '' ? data.output : '(žiadny výstup)';
                                if (data.passed === true) {
                                    outEl.classList.add('result-ok');
                                    resEl.textContent = 'Výstup zodpovedá očakávaniu. Výsledok bol uložený.';
                                    resEl.className = 'code-task-result success';
                                } else if (data.passed === false && !data.error) {
                                    outEl.classList.add('result-bad');
                                    resEl.textContent = 'Výstup nezodpovedá očakávaniu. Skúste to znova.';
                                    resEl.className = 'code-task-result error';
                                } else if (data.error) {
                                    resEl.textContent = 'Chyba pri spustení. Skontrolujte Judge0.';
                                    resEl.className = 'code-task-result error';
                                }
                            })
                            .catch(function(err) {
                                outEl.textContent = 'Chyba: ' + err.message;
                                outEl.classList.add('result-bad');
                                resEl.textContent = 'Nepodarilo sa odoslať požiadavku.';
                                resEl.className = 'code-task-result error';
                            });
                    });
                });
            })();
        </script>
    <?php endif; ?>

    <?php if (!empty($quizzes)): ?>
        <section class="quiz-block">
            <div class="quiz-title">Krátky test k lekcii</div>

            <form method="post">
                <?php foreach ($quizzes as $index => $q): ?>
                    <?php
                    $qid = $q['id'];
                    $userAnswers = $evaluation['answers'][$qid] ?? [];
                    $options = $q['options'];
                    $isMultiple = $q['is_multiple'];
                    ?>
                    <div class="question">
                        <div class="question-text">
                            <?= ($index + 1) ?>. <?= h($q['question']) ?>
                            <?php if ($isMultiple): ?>
                                <small class="muted inline-text-sm">(môžete vybrať viacero odpovedí)</small>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($options)): ?>
                            <?php foreach ($options as $optIndex => $optionText): ?>
                                <?php
                                $letter = chr(65 + $optIndex); // A, B, C, D, E, ...
                                $isChecked = in_array($optIndex, $userAnswers);
                                ?>
                                <label>
                                    <input
                                            type="<?= $isMultiple ? 'checkbox' : 'radio' ?>"
                                            name="answer[<?= $qid ?>]<?= $isMultiple ? '[]' : '' ?>"
                                            value="<?= $optIndex ?>"
                                        <?= $isChecked ? 'checked' : '' ?>
                                    >
                                    <strong><?= $letter ?>:</strong> <?= h($optionText) ?>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="muted">Chyba: Táto otázka nemá žiadne možnosti odpovede.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary">Odoslať odpovede</button>
            </form>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const inputs = document.querySelectorAll('.quiz-block input[type="radio"], .quiz-block input[type="checkbox"]');

                    function updateLabels() {
                        inputs.forEach(input => {
                            const label = input.closest('label');
                            if (input.checked) {
                                label.classList.add('selected');
                            } else {
                                label.classList.remove('selected');
                            }
                        });
                    }

                    inputs.forEach(input => {
                        input.addEventListener('change', updateLabels);
                    });
                    
                    updateLabels();
                });
            </script>

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
