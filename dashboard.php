<?php
//Prehľad výsledkov študenta

$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

// len prihlásený používateľ
require_login();
$userId = current_user_id();
$userName = current_user_name() ?? 'Študent';

$filter_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;

$coursesStmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.title
    FROM courses c
    JOIN lessons l ON c.id = l.course_id
    JOIN results r ON l.id = r.lesson_id
    WHERE r.user_id = :uid
    ORDER BY c.title
");
$coursesStmt->execute(['uid' => $userId]);
$allCourses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

$allLessons = [];
if ($filter_course_id > 0) {
    $lessonsStmt = $pdo->prepare("
        SELECT DISTINCT l.id, l.title
        FROM lessons l
        JOIN results r ON l.id = r.lesson_id
        WHERE r.user_id = :uid AND l.course_id = :course_id
        ORDER BY l.order_index, l.title
    ");
    $lessonsStmt->execute(['uid' => $userId, 'course_id' => $filter_course_id]);
    $allLessons = $lessonsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$whereConditions = ['r.user_id = :uid'];
$params = ['uid' => $userId];

if ($filter_course_id > 0) {
    $whereConditions[] = 'c.id = :filter_course_id';
    $params['filter_course_id'] = $filter_course_id;
}

if ($filter_lesson_id > 0) {
    $whereConditions[] = 'l.id = :filter_lesson_id';
    $params['filter_lesson_id'] = $filter_lesson_id;
}

$query = "
    SELECT 
        r.lesson_id,
        r.correct,
        r.total,
        r.created_at,
        l.title  AS lesson_title,
        c.title  AS course_title,
        c.id AS course_id,
        IF(r.total > 0, ROUND(r.correct / r.total * 100), 0) AS score_pct
    FROM results r
    JOIN lessons l ON r.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    WHERE " . implode(' AND ', $whereConditions) . "
    ORDER BY r.created_at DESC
    LIMIT 50
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/gamification.php';

$gamStats = null;
try {
    $uStmt = $pdo->prepare('
        SELECT xp_total, level, streak_current, streak_best
        FROM users WHERE id = :id LIMIT 1
    ');
    $uStmt->execute(['id' => $userId]);
    $urow = $uStmt->fetch(PDO::FETCH_ASSOC);
    if ($urow) {
        $bStmt = $pdo->prepare('
            SELECT badge_code, awarded_at
            FROM user_badges
            WHERE user_id = :uid
            ORDER BY awarded_at DESC
        ');
        $bStmt->execute(['uid' => $userId]);
        $badges = $bStmt->fetchAll(PDO::FETCH_ASSOC);
        $xp = (int)$urow['xp_total'];
        $level = max(1, (int)$urow['level']);
        $xpCurrentLevelStart = ($level - 1) * 200;
        $xpNextLevel = $level * 200;
        $xpInLevel = max(0, $xp - $xpCurrentLevelStart);
        $xpNeeded = max(1, $xpNextLevel - $xpCurrentLevelStart);
        $levelProgressPct = (int)min(100, floor(($xpInLevel / $xpNeeded) * 100));

        $gamStats = [
            'xp' => (int)$urow['xp_total'],
            'level' => (int)$urow['level'],
            'streak' => (int)$urow['streak_current'],
            'streak_best' => (int)$urow['streak_best'],
            'xp_to_next' => max(0, $xpNextLevel - $xp),
            'level_progress_pct' => $levelProgressPct,
            'badges' => $badges,
        ];
    }
} catch (PDOException $e) {
    $gamStats = null;
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Moje výsledky – E-Learn</title>
    <link rel="stylesheet" href="styles.css?v=3">
    <script src="theme.js" defer></script>
</head>
<body>
<header class="page-header">
    <div class="logo">E-<span>Learn</span></div>
    <div class="user-info">
        Prihlásený používateľ: <strong><?= h($userName) ?></strong>
        · <a href="index.php">Kurzy</a>
        <?php if (is_admin() || is_teacher()): ?>
            · <a href="admin/index.php">Administrácia</a>
        <?php endif; ?>
        · <a href="logout.php">Odhlásiť sa</a>
    </div>
</header>

<main class="container">
    <h1>Moje výsledky v testoch</h1>

    <?php if ($gamStats !== null): ?>
        <section class="gamification-card" aria-label="Postup a odznaky">
            <div class="gamification-stats">
                <div class="gamification-stat">
                    <span class="gamification-stat-label">Úroveň</span>
                    <span class="gamification-stat-value"><?= (int)$gamStats['level'] ?></span>
                </div>
                <div class="gamification-stat">
                    <span class="gamification-stat-label">XP celkom</span>
                    <span class="gamification-stat-value"><?= (int)$gamStats['xp'] ?></span>
                </div>
                <div class="gamification-stat">
                    <span class="gamification-stat-label">Séria (dni)</span>
                    <span class="gamification-stat-value"><?= (int)$gamStats['streak'] ?></span>
                    <?php if ((int)$gamStats['streak_best'] > 0): ?>
                        <span class="gamification-stat-hint">rekord <?= (int)$gamStats['streak_best'] ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="gamification-progress-wrap" aria-label="Postup do ďalšej úrovne">
                <div class="gamification-progress-text">
                    Do ďalšej úrovne: <?= (int)$gamStats['xp_to_next'] ?> XP
                </div>
                <div class="gamification-progress-bar">
                    <span style="width: <?= (int)$gamStats['level_progress_pct'] ?>%"></span>
                </div>
            </div>
            <?php if (!empty($gamStats['badges'])): ?>
                <div class="gamification-badges">
                    <h2 class="gamification-badges-title">Odznaky</h2>
                    <ul class="gamification-badge-list">
                        <?php foreach ($gamStats['badges'] as $b): ?>
                            <li class="gamification-badge" title="<?= h($b['awarded_at']) ?>">
                                <?= h(gamification_badge_label($b['badge_code'])) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <p class="gamification-empty">Zatiaľ nemáš žiadne odznaky — dokonči test alebo kódovú úlohu.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($allCourses)): ?>
        <form method="get" class="form-filters">
            <div class="form-grid">
                <div>
                    <label for="filter_course_id">Kurz</label>
                    <select id="filter_course_id" name="course_id">
                        <option value="0">Všetky kurzy</option>
                        <?php foreach ($allCourses as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $filter_course_id === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= h($c['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_lesson_id">Lekcia</label>
                    <select id="filter_lesson_id" name="lesson_id" <?= $filter_course_id <= 0 ? 'disabled' : '' ?>>
                        <option value="0">Všetky lekcie</option>
                        <?php foreach ($allLessons as $l): ?>
                            <option value="<?= (int)$l['id'] ?>" <?= $filter_lesson_id === (int)$l['id'] ? 'selected' : '' ?>>
                                <?= h($l['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">
                    Filtrovať
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    Zrušiť filtre
                </a>
            </div>
        </form>

        <script>
            document.getElementById('filter_course_id').addEventListener('change', function() {
                const lessonSelect = document.getElementById('filter_lesson_id');
                const courseId = this.value;

                if (courseId > 0) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('course_id', courseId);
                    url.searchParams.delete('lesson_id');
                    window.location.href = url.toString();
                } else {
                    lessonSelect.disabled = true;
                    lessonSelect.value = '0';
                }
            });
        </script>
    <?php endif; ?>

    <?php if (empty($results)): ?>
        <p class="empty">
            Zatiaľ nemáš uložené žiadne výsledky. Skús si otvoriť kurz,
            prejsť lekciu a vyplniť test.
        </p>
    <?php else: ?>
        <table class="table">
            <thead>
            <tr>
                <th>Dátum</th>
                <th>Kurz</th>
                <th>Lekcia</th>
                <th>Správne / spolu</th>
                <th>Úspešnosť</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $row): ?>
                <?php
                $ok = $row['score_pct'] >= 60;
                ?>
                <tr>
                    <td><?= h($row['created_at']) ?></td>
                    <td><?= h($row['course_title']) ?></td>
                    <td>
                        <a href="lesson.php?id=<?= (int)$row['lesson_id'] ?>">
                            <?= h($row['lesson_title']) ?>
                        </a>
                    </td>
                    <td><?= (int)$row['correct'] ?> / <?= (int)$row['total'] ?></td>
                    <td class="<?= $ok ? 'score-good' : 'score-bad' ?>">
                        <?= (int)$row['score_pct'] ?> %
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php
    // Kódové úlohy – posledné pokusy študenta
    $codeStmt = $pdo->prepare('
        SELECT
            r.id,
            r.passed,
            r.manual_score,
            r.created_at,
            ct.title AS task_title,
            l.title  AS lesson_title,
            c.title  AS course_title
        FROM code_task_results r
        JOIN code_tasks ct ON r.code_task_id = ct.id
        JOIN lessons l ON ct.lesson_id = l.id
        JOIN courses c ON l.course_id = c.id
        WHERE r.user_id = :uid
        ORDER BY r.created_at DESC
        LIMIT 20
    ');
    $codeStmt->execute(['uid' => $userId]);
    $codeResults = $codeStmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <h2 class="mt-20">Moje kódové úlohy</h2>

    <?php if (empty($codeResults)): ?>
        <p class="empty">
            Zatiaľ si neskúšal žiadne kódové úlohy.
        </p>
    <?php else: ?>
        <table class="table mt-8">
            <thead>
            <tr>
                <th>Dátum</th>
                <th>Kurz</th>
                <th>Lekcia</th>
                <th>Úloha</th>
                <th>Stav</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($codeResults as $cr): ?>
                <?php
                $status = 'Nesprávne';
                $class  = 'score-bad';
                if ($cr['manual_score'] === '1' || $cr['manual_score'] === 1) {
                    $status = 'Správne (učiteľ schválil)';
                    $class  = 'score-good';
                } elseif ($cr['manual_score'] === '0' || $cr['manual_score'] === 0) {
                    $status = 'Nesprávne (učiteľ zamietol)';
                    $class  = 'score-bad';
                } elseif ((int)$cr['passed'] === 1) {
                    $status = 'Správne (automaticky)';
                    $class  = 'score-good';
                }
                ?>
                <tr>
                    <td><?= h($cr['created_at']) ?></td>
                    <td><?= h($cr['course_title']) ?></td>
                    <td><?= h($cr['lesson_title']) ?></td>
                    <td><?= h($cr['task_title']) ?></td>
                    <td class="<?= $class ?>"><?= h($status) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>
</body>
</html>
