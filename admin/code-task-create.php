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

$errors = [];
$title = '';
$description = '';
$expected_output = '';
$language_id = 71;
$stdin = '';
$order_index = 1;

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
        $errors[] = 'Očakávaný výstup nemôže byť prázdny (podľa neho sa porovnáva odpoveď).';
    }
    if ($order_index < 1) {
        $order_index = 1;
    }

    if (empty($errors)) {
        try {
            $ins = $pdo->prepare('
                INSERT INTO code_tasks (lesson_id, title, description, expected_output, language_id, stdin, order_index, created_at)
                VALUES (:lesson_id, :title, :description, :expected_output, :language_id, :stdin, :order_index, NOW())
            ');
            $ins->execute([
                'lesson_id' => $lessonId,
                'title' => $title,
                'description' => $description,
                'expected_output' => $expected_output,
                'language_id' => $language_id,
                'stdin' => $stdin,
                'order_index' => $order_index,
            ]);
            header('Location: code-tasks.php?lesson_id=' . $lessonId . '&created=1');
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
    <title>Pridať kódovú úlohu – Administrácia</title>
    <link rel="stylesheet" href="../styles.css">
    <script src="../theme.js" defer></script>
</head>
<body class="admin-body">
<div class="admin-wrap">
    <div class="admin-card">
        <div class="muted"><a href="code-tasks.php?lesson_id=<?= (int)$lessonId ?>">← Späť na kódové úlohy</a></div>
        <h1>Pridať kódovú úlohu</h1>
        <p class="muted">Lekcia: <strong><?= h($lesson['title']) ?></strong></p>

        <?php foreach ($errors as $e): ?>
            <div class="error"><?= h($e) ?></div>
        <?php endforeach; ?>

        <form method="post" class="form-grid">
            <label>Názov úlohy <span class="muted">(zobrazí sa nad editorom)</span></label>
            <input type="text" name="title" value="<?= h($title) ?>" required minlength="2">

            <label>Zadanie pre študenta</label>
            <textarea name="description" rows="4" placeholder="Napríklad: Napíšte program, ktorý vypíše Hello World."><?= h($description) ?></textarea>

            <label>Očakávaný výstup <span class="muted">(presne sa porovná s výstupom programu po trim)</span></label>
            <textarea name="expected_output" rows="4" required placeholder="Hello World"><?= h($expected_output) ?></textarea>

            <label>Jazyk (Judge0)</label>
            <select name="language_id">
                <option value="71" <?= $language_id === 71 ? 'selected' : '' ?>>Python</option>
                <option value="62" <?= $language_id === 62 ? 'selected' : '' ?>>Java</option>
                <option value="54" <?= $language_id === 54 ? 'selected' : '' ?>>C++</option>
            </select>

            <label>Vstup pre program (stdin) <span class="muted">(voliteľný)</span></label>
            <textarea name="stdin" rows="2" placeholder="Prázdne = žiadny vstup"><?= h($stdin) ?></textarea>

            <label>Poradie</label>
            <input type="number" name="order_index" value="<?= (int)$order_index ?>" min="1">

            <div class="form-actions">
                <button type="submit" class="btn">Uložiť úlohu</button>
                <a href="code-tasks.php?lesson_id=<?= (int)$lessonId ?>" class="btn btn-secondary">Zrušiť</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
