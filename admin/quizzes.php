<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();


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

if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0) {
        $checkStmt = $pdo->prepare('SELECT id FROM quizzes WHERE id = :id AND lesson_id = :lesson_id');
        $checkStmt->execute(['id' => $deleteId, 'lesson_id' => $lessonId]);
        $quizToDelete = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($quizToDelete) {
            try {
                $deleteStmt = $pdo->prepare('DELETE FROM quizzes WHERE id = :id');
                $deleteStmt->execute(['id' => $deleteId]);

                header('Location: quizzes.php?lesson_id=' . $lessonId . '&deleted=1');
                exit;
            } catch (Exception $e) {
            }
        }
    }
}

// Načítame otázky
try {
    $stmt = $pdo->prepare('
        SELECT id, question, options, correct_options, option_a, option_b, option_c, option_d, correct_option
        FROM quizzes
        WHERE lesson_id = :lesson_id
        ORDER BY id ASC
    ');
    $stmt->execute(['lesson_id' => $lessonId]);
    $quizzesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt = $pdo->prepare('
        SELECT id, question, option_a, option_b, option_c, option_d, correct_option
        FROM quizzes
        WHERE lesson_id = :lesson_id
        ORDER BY id ASC
    ');
    $stmt->execute(['lesson_id' => $lessonId]);
    $quizzesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        if (is_array($decoded)) {
            $options = array_filter($decoded, function($v) { return $v !== '' && $v !== null; });
        }
    }

    if (!empty($q['correct_options']) && $q['correct_options'] !== null) {
        $decoded = json_decode($q['correct_options'], true);
        if (is_array($decoded)) {
            $correctOptions = $decoded;
        }
    }
    
    if (empty($options)) {
        if ($q['option_a']) $options[] = $q['option_a'];
        if ($q['option_b']) $options[] = $q['option_b'];
        if ($q['option_c']) $options[] = $q['option_c'];
        if ($q['option_d']) $options[] = $q['option_d'];
        $options = array_filter($options);
    }

    if (empty($correctOptions) && $q['correct_option']) {
        $map = ['a' => 0, 'b' => 1, 'c' => 2, 'd' => 3];
        if (isset($map[$q['correct_option']])) {
            $correctOptions = [$map[$q['correct_option']]];
        }
    }

    $q['options'] = array_values($options);
    $q['correct_options'] = $correctOptions;
    $quizzes[] = $q;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Otázky – Administrácia</title>
    <link rel="stylesheet" href="../styles.css">
    <script src="../theme.js" defer></script>
</head>
<body class="admin-body">
<div class="admin-wrap">
    <div class="admin-card">
        <div class="muted"><a href="lessons.php?course_id=<?= (int)$lesson['course_id'] ?>">← Späť na lekcie</a></div>
        <h1>Otázky: <?= h($lesson['title']) ?></h1>
        <p class="muted">Kurz: <strong><?= h($lesson['course_title']) ?></strong></p>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="success">
                Otázka bola úspešne odstránená.
            </div>
        <?php endif; ?>

        <a href="quiz-create.php?lesson_id=<?= (int)$lessonId ?>" class="btn">Pridať novú otázku</a>

        <table class="admin-table mt-20">
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
                        <td class="muted inline-text-sm">
                            <?php
                            $options = $q['options'] ?? [];
                            $displayOptions = [];
                            if (!empty($options)) {
                                foreach ($options as $idx => $opt) {
                                    $letter = chr(65 + (int)$idx);
                                    $displayOptions[] = $letter . ': ' . h($opt);
                                }
                                echo implode('<br>', $displayOptions);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="correct">
                            <?php
                            $correctOptions = $q['correct_options'] ?? [];
                            $displayCorrect = [];
                            if (!empty($correctOptions)) {
                                foreach ($correctOptions as $idx) {
                                    $letter = chr(65 + (int)$idx);
                                    $displayCorrect[] = $letter;
                                }
                                echo implode(', ', $displayCorrect);
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td class="actions">
                            <?php
                            $quiz = $q;
                            $editUrl = "quiz-edit.php?id=" . (int)$quiz['id'] . "&lesson_id=" . (int)$lessonId;
                            ?>
                            <a href="<?= $editUrl ?>">Upraviť</a>
                            <a href="quizzes.php?lesson_id=<?= (int)$lessonId ?>&delete=<?= (int)$q['id'] ?>" class="delete-link" onclick="return confirm('Naozaj chcete odstrániť túto otázku?');">Zmazať</a>
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

