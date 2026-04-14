<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

$lessonId = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
if ($lessonId <= 0) {
    header('Location: lessons.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT l.*, c.title AS course_title, c.teacher_id
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    WHERE l.id = :id
');
$stmt->execute(['id' => $lessonId]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    header('Location: lessons.php');
    exit;
}

if (is_teacher()) {
    $teacherId = current_user_teacher_id();
    if (!$teacherId) {
        $teacherId = get_user_teacher_id($pdo, current_user_id());
        if ($teacherId) {
            $_SESSION['teacher_id'] = $teacherId;
        }
    }
    if ($lesson['teacher_id'] != $teacherId) {
        header('Location: lessons.php');
        exit;
    }
}

// Delete code task
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0) {
        $check = $pdo->prepare('SELECT id FROM code_tasks WHERE id = :id AND lesson_id = :lesson_id');
        $check->execute(['id' => $deleteId, 'lesson_id' => $lessonId]);
        if ($check->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare('DELETE FROM code_tasks WHERE id = :id')->execute(['id' => $deleteId]);
            header('Location: code-tasks.php?lesson_id=' . $lessonId . '&deleted=1');
            exit;
        }
    }
}

$tasks = [];
try {
    $stmt = $pdo->prepare('
        SELECT id, title, description, expected_output, language_id, stdin, order_index, created_at
        FROM code_tasks
        WHERE lesson_id = :lesson_id
        ORDER BY order_index ASC, id ASC
    ');
    $stmt->execute(['lesson_id' => $lessonId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$langNames = [71 => 'Python', 62 => 'Java', 54 => 'C++'];
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Kódové úlohy – Administrácia</title>
    <link rel="stylesheet" href="../styles.css">
    <script src="../theme.js" defer></script>
</head>
<body class="admin-body">
<div class="admin-wrap">
    <div class="admin-card">
        <div class="muted"><a href="lessons.php?course_id=<?= (int)$lesson['course_id'] ?>">← Späť na lekcie</a></div>
        <h1>Kódové úlohy: <?= h($lesson['title']) ?></h1>
        <p class="muted">Kurz: <strong><?= h($lesson['course_title']) ?></strong>. Zadanie úlohy, očakávaný výstup a uloženie výsledku do DB.</p>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="success">Kódová úloha bola odstránená.</div>
        <?php endif; ?>

        <a href="code-task-create.php?lesson_id=<?= (int)$lessonId ?>" class="btn">Pridať kódovú úlohu</a>

        <table class="admin-table mt-20">
            <thead>
            <tr>
                <th>Poradie</th>
                <th>Názov</th>
                <th>Jazyk</th>
                <th>Očakávaný výstup (náhľad)</th>
                <th>Akcie</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($tasks)): ?>
                <tr>
                    <td colspan="5" class="muted">Zatiaľ nie sú pridané žiadne kódové úlohy.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($tasks as $t): ?>
                    <tr>
                        <td><?= (int)$t['order_index'] ?></td>
                        <td><strong><?= h($t['title']) ?></strong></td>
                        <td><?= h($langNames[$t['language_id']] ?? (string)$t['language_id']) ?></td>
                        <td class="muted inline-text-sm" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= h(mb_substr($t['expected_output'], 0, 80)) ?><?= mb_strlen($t['expected_output']) > 80 ? '…' : '' ?></td>
                        <td class="actions">
                            <a href="code-task-edit.php?id=<?= (int)$t['id'] ?>&lesson_id=<?= (int)$lessonId ?>">Upraviť</a>
                            <a href="code-tasks.php?lesson_id=<?= (int)$lessonId ?>&delete=<?= (int)$t['id'] ?>" class="delete-link" onclick="return confirm('Naozaj chcete odstrániť túto kódovú úlohu?');">Zmazať</a>
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
