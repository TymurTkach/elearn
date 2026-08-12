<?php
// course.php — stránka jedného kurzu

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
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #050b17;
            color: #e5e9f0;
        }
        header {
            background: #0b1020;
            padding: 16px 40px;
            border-bottom: 1px solid #1f2435;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .back {
            font-size: 13px;
        }
        .back a {
            color: #9ca3af;
            text-decoration: none;
        }
        .back a:hover {
            color: #ffffff;
        }
        header h1 {
            margin: 0;
            font-size: 22px;
        }
        main {
            max-width: 900px;
            margin: 32px auto 80px;
            padding: 0 16px;
        }
        .course-image-header {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid #1f2435;
        }
        .course-image-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            margin-bottom: 24px;
            border: 1px solid #1f2435;
        }
        .course-description {
            font-size: 15px;
            line-height: 1.6;
            color: #e5e9f0;
            margin-bottom: 24px;
        }
        .course-meta {
            font-size: 13px;
            color: #9ca3af;
            margin-bottom: 16px;
        }
        h2 {
            font-size: 20px;
            margin-top: 32px;
            margin-bottom: 16px;
        }
        .lessons-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .lesson-item {
            background: #0b1020;
            border-radius: 10px;
            padding: 12px 16px;
            border: 1px solid #1f2435;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .lesson-main {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .lesson-title {
            font-size: 15px;
            font-weight: 500;
        }
        .lesson-meta {
            font-size: 12px;
            color: #9ca3af;
        }
        .lesson-link a {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            font-size: 13px;
        }
        .lesson-link a:hover {
            background: #2563eb;
        }
        .empty {
            font-size: 14px;
            color: #9ca3af;
        }
        footer {
            text-align: center;
            padding: 24px;
            font-size: 12px;
            color: #6b7385;
            border-top: 1px solid #1f2435;
            background: #050b17;
        }
        .error {
            padding: 12px 16px;
            border-radius: 8px;
            background: #451b1b;
            color: #fecaca;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<header>
    <div class="back">
        <a href="index.php">&larr; Späť na zoznam kurzov</a>
    </div>
    <h1><?= htmlspecialchars($course['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
</header>

<main>
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

<footer>
    © 2025 E-Learn. Bakalársky projekt.
</footer>
</body>
</html>
