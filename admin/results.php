<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();


if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

$filter_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$filter_student_name = isset($_GET['student_name']) ? trim($_GET['student_name']) : '';

$teacherId = null;
if (is_teacher()) {
    $teacherId = current_user_teacher_id();
    if (!$teacherId) {
        $teacherId = get_user_teacher_id($pdo, current_user_id());
        if ($teacherId) {
            $_SESSION['teacher_id'] = $teacherId;
        }
    }
}

if (is_admin()) {
    $coursesStmt = $pdo->query('SELECT id, title FROM courses ORDER BY title');
    $allCourses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($teacherId) {
    $coursesStmt = $pdo->prepare('SELECT id, title FROM courses WHERE teacher_id = :teacher_id ORDER BY title');
    $coursesStmt->execute(['teacher_id' => $teacherId]);
    $allCourses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $allCourses = [];
}

$allLessons = [];
if ($filter_course_id > 0) {
    if (is_teacher() && $teacherId) {
        $courseCheck = $pdo->prepare('SELECT id FROM courses WHERE id = :course_id AND teacher_id = :teacher_id');
        $courseCheck->execute(['course_id' => $filter_course_id, 'teacher_id' => $teacherId]);
        if ($courseCheck->fetch()) {
            $lessonsStmt = $pdo->prepare('SELECT id, title FROM lessons WHERE course_id = :course_id ORDER BY order_index, title');
            $lessonsStmt->execute(['course_id' => $filter_course_id]);
            $allLessons = $lessonsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $lessonsStmt = $pdo->prepare('SELECT id, title FROM lessons WHERE course_id = :course_id ORDER BY order_index, title');
        $lessonsStmt->execute(['course_id' => $filter_course_id]);
        $allLessons = $lessonsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$allStudents = [];
if (is_teacher() && $teacherId) {
    $studentsStmt = $pdo->prepare('
        SELECT id, name
        FROM users
        WHERE teacher_user_id = :teacher_id
        ORDER BY name
    ');
    $studentsStmt->execute(['teacher_id' => $teacherId]);
    $allStudents = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (is_admin()) {
    $studentsStmt = $pdo->query('
        SELECT id, name
        FROM users
        WHERE role = "student"
        ORDER BY name
    ');
    $allStudents = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Načítame výsledky s ohľadom na práva prístupu a filtre
$whereConditions = [];
$params = [];

if (is_admin()) {
    // Admin vidí všetky výsledky
    $baseQuery = '
        SELECT 
            r.id,
            r.correct,
            r.total,
            r.created_at,
            u.id AS user_id,
            u.name AS user_name,
            u.email AS user_email,
            l.id AS lesson_id,
            l.title AS lesson_title,
            c.id AS course_id,
            c.title AS course_title,
            IF(r.total > 0, ROUND(r.correct / r.total * 100), 0) AS score_pct
        FROM results r
        JOIN users u ON r.user_id = u.id
        JOIN lessons l ON r.lesson_id = l.id
        JOIN courses c ON l.course_id = c.id
    ';
} else {
    // Učiteľ vidí len výsledky svojich študentov – tých, ktorí majú teacher_user_id = id učiteľa
    if ($teacherId) {
        $baseQuery = '
            SELECT 
                r.id,
                r.correct,
                r.total,
                r.created_at,
                u.id AS user_id,
                u.name AS user_name,
                u.email AS user_email,
                l.id AS lesson_id,
                l.title AS lesson_title,
                c.id AS course_id,
                c.title AS course_title,
                IF(r.total > 0, ROUND(r.correct / r.total * 100), 0) AS score_pct
            FROM results r
            JOIN users u ON r.user_id = u.id
            JOIN lessons l ON r.lesson_id = l.id
            JOIN courses c ON l.course_id = c.id
            WHERE u.teacher_user_id = :teacher_id
        ';
        $params['teacher_id'] = $teacherId;
    } else {
        $results = [];
        $baseQuery = '';
    }
}

if (!empty($baseQuery)) {
    if ($filter_course_id > 0) {
        $whereConditions[] = 'c.id = :filter_course_id';
        $params['filter_course_id'] = $filter_course_id;
    }

    if ($filter_lesson_id > 0) {
        $whereConditions[] = 'l.id = :filter_lesson_id';
        $params['filter_lesson_id'] = $filter_lesson_id;
    }

    if ($filter_student_name !== '') {
        $whereConditions[] = 'u.name LIKE :filter_student_name';
        $params['filter_student_name'] = '%' . $filter_student_name . '%';
    }

    $query = $baseQuery;
    if (!empty($whereConditions)) {
        $query .= (is_admin() ? ' WHERE ' : ' AND ') . implode(' AND ', $whereConditions);
    }
    $query .= ' ORDER BY r.created_at DESC LIMIT 200';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $results = [];
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Výsledky – Administrácia</title>
    <link rel="stylesheet" href="../styles.css">
    <script src="../theme.js" defer></script>
</head>
<body class="admin-body">
<div class="admin-wrap">
    <div class="admin-card">
        <div class="muted"><a href="index.php">← Administrácia</a></div>
        <h1>Výsledky testov</h1>
        <p class="muted">Prehľad všetkých výsledkov od študentov.</p>

        <form method="get" class="form-filters mt-20">
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
                <?php if (!empty($allStudents)): ?>
                    <div>
                        <label for="filter_student_name">Meno študenta</label>
                        <input type="text" id="filter_student_name" name="student_name" value="<?= h($filter_student_name) ?>" placeholder="Hľadať podľa mena...">
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn">
                    Filtrovať
                </button>
                <a href="results.php" class="btn btn-secondary">
                    Zrušiť filtre
                </a>
            </div>
        </form>

        <script>
            document.getElementById('filter_course_id').addEventListener('change', function() {
                const courseId = this.value;
                const url = new URL(window.location.href);

                if (courseId > 0) {
                    url.searchParams.set('course_id', courseId);
                    url.searchParams.delete('lesson_id');
                } else {
                    url.searchParams.delete('course_id');
                    url.searchParams.delete('lesson_id');
                }
                
                const studentName = document.getElementById('filter_student_name');
                if (studentName && studentName.value) {
                    url.searchParams.set('student_name', studentName.value);
                } else {
                    url.searchParams.delete('student_name');
                }

                window.location.href = url.toString();
            });
        </script>

        <table class="admin-table mt-20">
            <thead>
            <tr>
                <th>Dátum</th>
                <th>Študent</th>
                <th>Kurz</th>
                <th>Lekcia</th>
                <th>Správne / spolu</th>
                <th>Úspešnosť</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($results)): ?>
                <tr>
                    <td colspan="6" class="muted">Zatiaľ nie sú žiadne výsledky.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($results as $r): ?>
                    <?php
                    $ok = $r['score_pct'] >= 60;
                    ?>
                    <tr>
                        <td class="muted"><?= h($r['created_at']) ?></td>
                        <td>
                            <strong><?= h($r['user_name']) ?></strong><br>
                            <span class="muted inline-text-sm"><?= h($r['user_email']) ?></span>
                        </td>
                        <td><?= h($r['course_title']) ?></td>
                        <td><?= h($r['lesson_title']) ?></td>
                        <td><?= (int)$r['correct'] ?> / <?= (int)$r['total'] ?></td>
                        <td class="<?= $ok ? 'score-good' : 'score-bad' ?>">
                            <?= (int)$r['score_pct'] ?> %
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <div class="muted mt-16">
            Zobrazené: <span class="pill"><?= count($results) ?></span> výsledkov
            <?php if ($filter_course_id > 0 || $filter_lesson_id > 0 || $filter_student_name !== ''): ?>
                <span class="inline-text-sm ml-12">
                    (filtrované)
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

