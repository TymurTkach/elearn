<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

// Učitelia a admini môžu vstúpiť do administrácie
if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Spracovanie manuálneho hodnotenia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['result_id'], $_POST['manual_score'])) {
    $resultId = (int)$_POST['result_id'];
    $manualScore = $_POST['manual_score'] === '1' ? 1 : 0;

    if ($resultId > 0) {
        $stmt = $pdo->prepare('
            UPDATE code_task_results r
            JOIN code_tasks ct ON r.code_task_id = ct.id
            JOIN lessons l ON ct.lesson_id = l.id
            JOIN courses c ON l.course_id = c.id
            SET r.manual_score = :manual_score
            WHERE r.id = :id
        ');
        $stmt->execute([
            'manual_score' => $manualScore,
            'id' => $resultId,
        ]);
        header('Location: code-results.php?updated=1');
        exit;
    }
}

// Filtre
$filter_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$filter_status = isset($_GET['status']) ? (string)$_GET['status'] : '';

// Kurzy učiteľa / všetky (pre admina)
if (is_teacher()) {
    $teacherId = current_user_teacher_id();
    if (!$teacherId) {
        $teacherId = get_user_teacher_id($pdo, current_user_id());
        if ($teacherId) {
            $_SESSION['teacher_id'] = $teacherId;
        }
    }
    $coursesStmt = $pdo->prepare('
        SELECT id, title
        FROM courses
        WHERE teacher_id = :tid
        ORDER BY title
    ');
    $coursesStmt->execute(['tid' => $teacherId]);
} else {
    $coursesStmt = $pdo->query('SELECT id, title FROM courses ORDER BY title');
}
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

// Lekcie podľa kurzu
$lessons = [];
if ($filter_course_id > 0) {
    $lessonsStmt = $pdo->prepare('
        SELECT id, title
        FROM lessons
        WHERE course_id = :cid
        ORDER BY order_index, title
    ');
    $lessonsStmt->execute(['cid' => $filter_course_id]);
    $lessons = $lessonsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Zoznam výsledkov
$where = [];
$params = [];

if (is_teacher()) {
    $where[] = 'c.teacher_id = :tid';
    $params['tid'] = $teacherId;
}

if ($filter_course_id > 0) {
    $where[] = 'c.id = :course_id';
    $params['course_id'] = $filter_course_id;
}
if ($filter_lesson_id > 0) {
    $where[] = 'l.id = :lesson_id';
    $params['lesson_id'] = $filter_lesson_id;
}

if ($filter_status === 'correct') {
    $where[] = '(r.manual_score = 1 OR (r.manual_score IS NULL AND r.passed = 1))';
} elseif ($filter_status === 'incorrect') {
    $where[] = '(r.manual_score = 0 OR (r.manual_score IS NULL AND r.passed = 0))';
} elseif ($filter_status === 'manual') {
    $where[] = 'r.manual_score IS NOT NULL';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.passed,
        r.manual_score,
        r.created_at,
        r.source_code,
        r.output,
        u.name AS user_name,
        ct.title AS task_title,
        l.title AS lesson_title,
        c.title AS course_title
    FROM code_task_results r
    JOIN users u ON r.user_id = u.id
    JOIN code_tasks ct ON r.code_task_id = ct.id
    JOIN lessons l ON ct.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    $whereSql
    ORDER BY r.created_at DESC
    LIMIT 50
");
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Kódové výsledky – Administrácia</title>
    <link rel="stylesheet" href="../styles.css">
    <script src="../theme.js" defer></script>
</head>
<body class="admin-body">
<div class="admin-wrap">
    <div class="admin-card">
        <div class="muted"><a href="index.php">← Späť na administráciu</a></div>
        <h1>Výsledky kódových úloh</h1>
        <p class="muted">Prehľad pokusov študentov v kódových úlohách s možnosťou manuálneho hodnotenia.</p>

        <?php if (isset($_GET['updated'])): ?>
            <div class="success">Hodnotenie bolo aktualizované.</div>
        <?php endif; ?>

        <form method="get" class="form-filters mt-16">
            <div class="form-grid">
                <div>
                    <label for="course_id">Kurz</label>
                    <select id="course_id" name="course_id">
                        <option value="0">Všetky kurzy</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $filter_course_id === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= h($c['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="lesson_id">Lekcia</label>
                    <select id="lesson_id" name="lesson_id" <?= $filter_course_id <= 0 ? 'disabled' : '' ?>>
                        <option value="0">Všetky lekcie</option>
                        <?php foreach ($lessons as $l): ?>
                            <option value="<?= (int)$l['id'] ?>" <?= $filter_lesson_id === (int)$l['id'] ? 'selected' : '' ?>>
                                <?= h($l['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status">Stav</label>
                    <select id="status" name="status">
                        <option value="">Všetky</option>
                        <option value="correct" <?= $filter_status === 'correct' ? 'selected' : '' ?>>Správne</option>
                        <option value="incorrect" <?= $filter_status === 'incorrect' ? 'selected' : '' ?>>Nesprávne</option>
                        <option value="manual" <?= $filter_status === 'manual' ? 'selected' : '' ?>>Manuálne upravené</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">Filtrovať</button>
                <a href="code-results.php" class="btn btn-secondary">Zrušiť filtre</a>
            </div>
        </form>

        <?php if (empty($results)): ?>
            <p class="empty mt-16">Zatiaľ nie sú žiadne výsledky kódových úloh podľa zvolených filtrov.</p>
        <?php else: ?>
            <table class="admin-table mt-20">
                <thead>
                <tr>
                    <th>Dátum</th>
                    <th>Študent</th>
                    <th>Kurz / lekcia</th>
                    <th>Úloha</th>
                    <th>Stav (auto / manuálne)</th>
                    <th>Akcie</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                    <?php
                    $auto = (int)$r['passed'] === 1 ? 'správne' : 'nesprávne';
                    $manual = $r['manual_score'];
                    if ($manual === null) {
                        $manualText = '—';
                    } elseif ((int)$manual === 1) {
                        $manualText = 'schválené';
                    } else {
                        $manualText = 'zamietnuté';
                    }
                    ?>
                    <tr>
                        <td><?= h($r['created_at']) ?></td>
                        <td><?= h($r['user_name']) ?></td>
                        <td>
                            <strong><?= h($r['course_title']) ?></strong><br>
                            <span class="muted inline-text-sm"><?= h($r['lesson_title']) ?></span>
                        </td>
                        <td><?= h($r['task_title']) ?></td>
                        <td class="muted inline-text-sm">
                            Auto: <?= h($auto) ?><br>
                            Manuálne: <?= h($manualText) ?>
                        </td>
                        <td class="actions">
                            <details>
                                <summary>Zobraziť</summary>
                                <div class="mt-8 inline-text-sm">
                                    <strong>Kód:</strong>
                                    <pre class="code-task-output" style="margin-top:4px;"><?= h($r['source_code']) ?></pre>
                                    <strong>Výstup:</strong>
                                    <pre class="code-task-output" style="margin-top:4px;"><?= h($r['output'] ?? '(žiadny výstup)') ?></pre>
                                </div>
                            </details>
                            <form method="post" style="margin-top:6px;">
                                <input type="hidden" name="result_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" name="manual_score" value="1" class="btn btn-secondary" style="margin-bottom:4px;">Uznať ako správne</button>
                                <button type="submit" name="manual_score" value="0" class="btn btn-secondary">Označiť ako nesprávne</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

