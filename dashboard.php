<?php
// dashboard.php – prehľad výsledkov študenta

$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

// len prihlásený používateľ
require_login();
$userId = current_user_id();
$userName = current_user_name() ?? 'Študent';

// Получаем фильтры из GET параметров
$filter_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;

// Загружаем курсы для фильтра (только те, где есть результаты студента)
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

// Загружаем уроки для фильтра (если выбран курс)
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

// Načítame výsledky s filtrami
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

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Moje výsledky – E-Learn</title>
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
        .user-info {
            font-size: 14px;
            color: #9ca3af;
        }
        main {
            max-width: 960px;
            margin: 32px auto 80px;
            padding: 0 16px;
        }
        h1 {
            font-size: 28px;
            margin-bottom: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #020617;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #1e293b;
        }
        th, td {
            padding: 10px 14px;
            font-size: 14px;
        }
        th {
            text-align: left;
            background: #020617;
            border-bottom: 1px solid #1e293b;
            color: #9ca3af;
        }
        tr:nth-child(even) td {
            background: #020617;
        }
        tr:nth-child(odd) td {
            background: #020617;
        }
        .score-good {
            color: #22c55e;
            font-weight: 600;
        }
        .score-bad {
            color: #ef4444;
            font-weight: 600;
        }
        .empty {
            margin-top: 16px;
            font-size: 14px;
            color: #9ca3af;
        }
        a {
            color: #38bdf8;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<header>
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

<main>
    <h1>Moje výsledky v testoch</h1>

    <!-- Форма фильтров -->
    <?php if (!empty($allCourses)): ?>
    <form method="get" style="margin-bottom:24px;padding:16px;background:#020617;border-radius:12px;border:1px solid #1e293b">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:12px">
            <div>
                <label for="filter_course_id" style="display:block;margin-bottom:6px;font-size:13px;color:#cbd5e1">Kurz</label>
                <select id="filter_course_id" name="course_id" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #1e293b;background:#0b1220;color:#e5e7eb;font-size:13px">
                    <option value="0">Všetky kurzy</option>
                    <?php foreach ($allCourses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $filter_course_id === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= h($c['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_lesson_id" style="display:block;margin-bottom:6px;font-size:13px;color:#cbd5e1">Lekcia</label>
                <select id="filter_lesson_id" name="lesson_id" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #1e293b;background:#0b1220;color:#e5e7eb;font-size:13px" <?= $filter_course_id <= 0 ? 'disabled' : '' ?>>
                    <option value="0">Všetky lekcie</option>
                    <?php foreach ($allLessons as $l): ?>
                        <option value="<?= (int)$l['id'] ?>" <?= $filter_lesson_id === (int)$l['id'] ? 'selected' : '' ?>>
                            <?= h($l['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="display:flex;gap:8px">
            <button type="submit" style="padding:8px 16px;background:#38bdf8;color:#020617;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px">
                Filtrovať
            </button>
            <a href="dashboard.php" style="padding:8px 16px;background:#475569;color:#e5e7eb;border:none;border-radius:8px;text-decoration:none;font-size:13px;display:inline-block">
                Zrušiť filtre
            </a>
        </div>
    </form>

    <script>
        // Обновляем список уроков при изменении курса
        document.getElementById('filter_course_id').addEventListener('change', function() {
            const lessonSelect = document.getElementById('filter_lesson_id');
            const courseId = this.value;
            
            if (courseId > 0) {
                // Перезагружаем страницу с выбранным курсом для загрузки уроков
                const url = new URL(window.location.href);
                url.searchParams.set('course_id', courseId);
                url.searchParams.delete('lesson_id'); // Сбрасываем урок при смене курса
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
        <table>
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
</main>
</body>
</html>
