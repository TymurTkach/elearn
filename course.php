<?php
//Stránka jedného kurzu

$pdo = require dirname(__FILE__) . '/config.php';
require dirname(__FILE__) . '/auth.php';

// id kurzu z GET
$courseId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$courseId) {
    http_response_code(404);
    echo 'Nesprávny identifikátor kurzu.';
    exit;
}

// načítame kurz
try {
    $stmt = $pdo->prepare(
        'SELECT id, title, description, image, created_at 
         FROM courses 
         WHERE id = :id'
    );
    $stmt->execute(['id' => $courseId]);
    $course = $stmt->fetch();
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Chyba pri načítaní kurzu: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}

if (!$course) {
    http_response_code(404);
    echo 'Kurz nebol nájdený.';
    exit;
}

// načítame lekcie
try {
    $stmt = $pdo->prepare(
        'SELECT id, title, order_index, estimated_min, created_at
         FROM lessons
         WHERE course_id = :course_id
         ORDER BY order_index ASC, created_at ASC'
    );
    $stmt->execute(['course_id' => $courseId]);
    $lessons = $stmt->fetchAll();
    $lessonsError = null;
} catch (Throwable $e) {
    $lessons = [];
    $lessonsError = $e->getMessage();
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($course['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> – E-Learn</title>
    <link rel="stylesheet" href="styles.css">
    <script src="theme.js" defer></script>
</head>
<body>
<header class="site-header">
    <div class="back inline-text-sm">
        <a href="index.php">&larr; Späť na zoznam kurzov</a>
    </div>
    <h1><?= htmlspecialchars($course['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
</header>

<main class="container">
    <?php if (!empty($course['image'])): ?>
        <img src="<?= htmlspecialchars($course['image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
             alt="<?= htmlspecialchars($course['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
             class="course-image-header">
    <?php else: ?>
        <div class="course-image-placeholder">📚</div>
    <?php endif; ?>

    <div class="course-meta">
        Vytvorený: <?= htmlspecialchars($course['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    </div>

    <div class="course-description">
        <?= nl2br(htmlspecialchars($course['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?>
    </div>

    <h2>Lekcie v tomto kurze</h2>

    <?php if ($lessonsError): ?>
        <div class="error">
            Chyba pri načítaní lekcií: <?= htmlspecialchars($lessonsError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php elseif (empty($lessons)): ?>
        <p class="empty">
            Tento kurz zatiaľ nemá žiadne lekcie.
            Pridajte ich do tabuľky <strong>lessons</strong> v databáze.
        </p>
    <?php else: ?>
        <div class="lessons-list">
            <?php foreach ($lessons as $lesson): ?>
                <article class="lesson-item">
                    <div class="lesson-main">
                        <div class="lesson-title">
                            <?= (int)$lesson['order_index'] ?>.
                            <?= htmlspecialchars($lesson['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </div>
                        <div class="lesson-meta">
                            <?php if ($lesson['estimated_min']): ?>
                                Odhadovaný čas: <?= (int)$lesson['estimated_min'] ?> min ·
                            <?php endif; ?>
                            Vytvorená: <?= htmlspecialchars($lesson['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </div>
                    </div>
                    <div class="lesson-link">
                        <a href="lesson.php?id=<?= (int)$lesson['id'] ?>">Otvoriť lekciu →</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer class="site-footer">
    © 2025 E-Learn. Bakalársky projekt.
</footer>
</body>
</html>
