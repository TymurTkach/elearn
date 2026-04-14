<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

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

if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    if ($deleteId > 0) {
        $checkStmt = $pdo->prepare('SELECT id FROM lessons WHERE id = :id AND course_id = :course_id');
        $checkStmt->execute(['id' => $deleteId, 'course_id' => $courseId]);
        $lessonToDelete = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($lessonToDelete) {
            $pdo->beginTransaction();
            try {
                $resultsStmt = $pdo->prepare('DELETE FROM results WHERE lesson_id = :lesson_id');
                $resultsStmt->execute(['lesson_id' => $deleteId]);

                $quizzesStmt = $pdo->prepare('DELETE FROM quizzes WHERE lesson_id = :lesson_id');
                $quizzesStmt->execute(['lesson_id' => $deleteId]);

                try {
                    $pdo->prepare('DELETE FROM code_task_results WHERE code_task_id IN (SELECT id FROM code_tasks WHERE lesson_id = :lesson_id)')
                        ->execute(['lesson_id' => $deleteId]);
                } catch (PDOException $e) { /* code_task_results alebo code_tasks môžu neexistovať */ }
                try {
                    $pdo->prepare('DELETE FROM code_tasks WHERE lesson_id = :lesson_id')->execute(['lesson_id' => $deleteId]);
                } catch (PDOException $e) { /* tabuľka code_tasks môže neexistovať */ }

                $deleteStmt = $pdo->prepare('DELETE FROM lessons WHERE id = :id');
                $deleteStmt->execute(['id' => $deleteId]);

                $pdo->commit();
                header('Location: lessons.php?course_id=' . $courseId . '&deleted=1');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    }
}

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
    <link rel="stylesheet" href="../styles.css">
    <script src="../theme.js" defer></script>
</head>
<body class="admin-body">
<div class="admin-wrap">
    <div class="admin-card">
        <div class="muted"><a href="courses.php">← Kurzy</a></div>
        <h1>Lekcie: <?= h($course['title']) ?></h1>
        <p class="muted">Správa lekcií pre tento kurz.</p>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="success">
                Lekcia bola úspešne odstránená.
            </div>
        <?php endif; ?>

        <a href="lesson-create.php?course_id=<?= (int)$courseId ?>" class="btn">Pridať novú lekciu</a>

        <table class="admin-table mt-20">
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
                            <a href="code-tasks.php?lesson_id=<?= (int)$l['id'] ?>">Kódové úlohy</a>
                            <a href="lessons.php?course_id=<?= (int)$courseId ?>&delete=<?= (int)$l['id'] ?>" class="delete-link" onclick="return confirm('Naozaj chcete odstrániť túto lekciu? Táto akcia je nevratná a odstráni všetky otázky a kódové úlohy.');">Zmazať</a>
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

