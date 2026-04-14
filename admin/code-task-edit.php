<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$lessonId = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
if ($taskId <= 0 || $lessonId <= 0) {
    header('Location: lessons.php');
    exit;
}

$stmt = $pdo->prepare('
    SELECT ct.*, l.title AS lesson_title, c.teacher_id
    FROM code_tasks ct
    JOIN lessons l ON l.id = ct.lesson_id
    JOIN courses c ON l.course_id = c.id
    WHERE ct.id = :id AND ct.lesson_id = :lesson_id
');
$stmt->execute(['id' => $taskId, 'lesson_id' => $lessonId]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    header('Location: code-tasks.php?lesson_id=' . $lessonId);
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
    if ($task['teacher_id'] != $teacherId) {
        header('Location: lessons.php');
        exit;
    }
}

$errors = [];
$title = $task['title'];
$description = $task['description'] ?? '';
$expected_output = $task['expected_output'];
$language_id = (int)$task['language_id'];
$stdin = $task['stdin'] ?? '';
$order_index = (int)$task['order_index'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $expected_output = trim((string)($_POST['expected_output'] ?? ''));
    $language_id = isset($_POST['language_id']) ? (int)$_POST['language_id'] : 71;
    $stdin = trim((string)($_POST['stdin'] ?? ''));
    $order_index = isset($_POST['order_index']) ? (int)$_POST['order_index'] : 1;

    if (mb_strlen($title) < 2) {
        $errors[] = 'Názov úlohy musí mať aspoň 2 znaky.';
    }
    if ($expected_output === '') {
        $errors[] = 'Očakávaný výstup nemôže byť prázdny.';
    }
    if ($order_index < 1) {
        $order_index = 1;
    }

    if (empty($errors)) {
        try {
            $upd = $pdo->prepare('
                UPDATE code_tasks
                SET title = :title, description = :description, expected_output = :expected_output,
                    language_id = :language_id, stdin = :stdin, order_index = :order_index
                WHERE id = :id
            ');
            $upd->execute([
                'title' => $title,
                'description' => $description,
                'expected_output' => $expected_output,
                'language_id' => $language_id,
                'stdin' => $stdin,
                'order_index' => $order_index,
                'id' => $taskId,
            ]);
            header('Location: code-tasks.php?lesson_id=' . $lessonId . '&updated=1');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Chyba DB: ' . $e->getMessage();
        }
    }
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Upraviť kódovú úlohu – Administrácia</title>
    <link rel="stylesheet" href="../styles.css">
    <script src="../theme.js" defer></script>
</head>
<body class="admin-body">
<div class="admin-wrap">
    <div class="admin-card">
        <div class="muted"><a href="code-tasks.php?lesson_id=<?= (int)$lessonId ?>">← Späť na kódové úlohy</a></div>
        <h1>Upraviť kódovú úlohu</h1>
        <p class="muted">Lekcia: <strong><?= h($task['lesson_title']) ?></strong></p>

        <?php foreach ($errors as $e): ?>
            <div class="error"><?= h($e) ?></div>
        <?php endforeach; ?>

        <form method="post" class="form-grid">
            <label>Názov úlohy</label>
            <input type="text" name="title" value="<?= h($title) ?>" required minlength="2">

            <label>Zadanie pre študenta</label>
            <textarea name="description" rows="4"><?= h($description) ?></textarea>

            <label>Očakávaný výstup</label>
            <textarea name="expected_output" rows="4" required><?= h($expected_output) ?></textarea>

            <label>Jazyk (Judge0)</label>
            <select name="language_id">
                <option value="71" <?= $language_id === 71 ? 'selected' : '' ?>>Python</option>
                <option value="62" <?= $language_id === 62 ? 'selected' : '' ?>>Java</option>
                <option value="54" <?= $language_id === 54 ? 'selected' : '' ?>>C++</option>
            </select>

            <label>Vstup pre program (stdin)</label>
            <textarea name="stdin" rows="2"><?= h($stdin) ?></textarea>

            <label>Poradie</label>
            <input type="number" name="order_index" value="<?= (int)$order_index ?>" min="1">

            <div class="form-actions">
                <button type="submit" class="btn">Uložiť zmeny</button>
                <a href="code-tasks.php?lesson_id=<?= (int)$lessonId ?>" class="btn btn-secondary">Zrušiť</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
