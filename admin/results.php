<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

// Учителя и админы могут заходить в админку
if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

// Получаем фильтры из GET параметров
$filter_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
$filter_student_name = isset($_GET['student_name']) ? trim($_GET['student_name']) : '';

// Загружаем списки для фильтров
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

// Загружаем курсы для фильтра
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

// Загружаем уроки для фильтра (если выбран курс)
$allLessons = [];
if ($filter_course_id > 0) {
    // Проверяем, что курс принадлежит учителю (если это учитель)
    if (is_teacher() && $teacherId) {
        $courseCheck = $pdo->prepare('SELECT id FROM courses WHERE id = :course_id AND teacher_id = :teacher_id');
        $courseCheck->execute(['course_id' => $filter_course_id, 'teacher_id' => $teacherId]);
        if ($courseCheck->fetch()) {
            $lessonsStmt = $pdo->prepare('SELECT id, title FROM lessons WHERE course_id = :course_id ORDER BY order_index, title');
            $lessonsStmt->execute(['course_id' => $filter_course_id]);
            $allLessons = $lessonsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Для админов загружаем все уроки выбранного курса
        $lessonsStmt = $pdo->prepare('SELECT id, title FROM lessons WHERE course_id = :course_id ORDER BY order_index, title');
        $lessonsStmt->execute(['course_id' => $filter_course_id]);
        $allLessons = $lessonsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Загружаем студентов для фильтра (только для учителей)
$allStudents = [];
if (is_teacher() && $teacherId) {
    $studentsStmt = $pdo->prepare('
        SELECT DISTINCT u.id, u.name 
        FROM users u
        JOIN students s ON u.id = s.user_id
        WHERE s.teacher_id = :teacher_id
        ORDER BY u.name
    ');
    $studentsStmt->execute(['teacher_id' => $teacherId]);
    $allStudents = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (is_admin()) {
    $studentsStmt = $pdo->query('SELECT DISTINCT u.id, u.name FROM users u JOIN students s ON u.id = s.user_id ORDER BY u.name');
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
    // Učiteľ vidí len výsledky svojich študentov
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
            JOIN students s ON u.id = s.user_id
            JOIN lessons l ON r.lesson_id = l.id
            JOIN courses c ON l.course_id = c.id
            WHERE s.teacher_id = :teacher_id
        ';
        $params['teacher_id'] = $teacherId;
    } else {
        $results = [];
        $baseQuery = '';
    }
}

// Добавляем фильтры
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
    
    // Собираем запрос
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
    <style>
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#0f172a;color:#e5e7eb}
        .wrap{max-width:1200px;margin:40px auto;padding:0 16px}
        .card{background:#020617;border:1px solid #1e293b;border-radius:16px;padding:20px;margin-bottom:14px}
        a{color:#e5e7eb;text-decoration:none}
        a:hover{text-decoration:underline}
        .muted{color:#9ca3af}
        table{width:100%;border-collapse:collapse;font-size:13px}
        th,td{padding:10px;border-bottom:1px solid #1e293b;text-align:left;vertical-align:top}
        th{color:#cbd5e1;font-size:12px}
        .score-good{color:#22c55e;font-weight:600}
        .score-bad{color:#ef4444;font-weight:600}
        .pill{display:inline-block;padding:4px 10px;border-radius:999px;border:1px solid #1e293b;background:#0b1220;font-size:12px;color:#cbd5e1}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="muted"><a href="index.php">← Administrácia</a></div>
        <h1>Výsledky testov</h1>
        <p class="muted">Prehľad všetkých výsledkov od študentov.</p>

        <!-- Форма фильтров -->
        <form method="get" style="margin-top:20px;padding:16px;background:#0b1220;border-radius:12px;border:1px solid #1e293b">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:12px">
                <div>
                    <label for="filter_course_id" style="display:block;margin-bottom:6px;font-size:13px;color:#cbd5e1">Kurz</label>
                    <select id="filter_course_id" name="course_id" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #1e293b;background:#020617;color:#e5e7eb;font-size:13px">
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
                    <select id="filter_lesson_id" name="lesson_id" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #1e293b;background:#020617;color:#e5e7eb;font-size:13px" <?= $filter_course_id <= 0 ? 'disabled' : '' ?>>
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
                    <label for="filter_student_name" style="display:block;margin-bottom:6px;font-size:13px;color:#cbd5e1">Meno študenta</label>
                    <input type="text" id="filter_student_name" name="student_name" value="<?= h($filter_student_name) ?>" placeholder="Hľadať podľa mena..." style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid #1e293b;background:#020617;color:#e5e7eb;font-size:13px">
                </div>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:8px">
                <button type="submit" style="padding:8px 16px;background:#38bdf8;color:#020617;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px">
                    Filtrovať
                </button>
                <a href="results.php" style="padding:8px 16px;background:#475569;color:#e5e7eb;border:none;border-radius:8px;text-decoration:none;font-size:13px;display:inline-block">
                    Zrušiť filtre
                </a>
            </div>
        </form>

        <script>
            // Обновляем список уроков при изменении курса
            document.getElementById('filter_course_id').addEventListener('change', function() {
                const courseId = this.value;
                const url = new URL(window.location.href);
                
                if (courseId > 0) {
                    url.searchParams.set('course_id', courseId);
                    url.searchParams.delete('lesson_id'); // Сбрасываем урок при смене курса
                } else {
                    url.searchParams.delete('course_id');
                    url.searchParams.delete('lesson_id');
                }
                
                // Сохраняем фильтр по имени студента
                const studentName = document.getElementById('filter_student_name');
                if (studentName && studentName.value) {
                    url.searchParams.set('student_name', studentName.value);
                } else {
                    url.searchParams.delete('student_name');
                }
                
                window.location.href = url.toString();
            });
        </script>

        <table style="margin-top:20px">
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
                            <span class="muted" style="font-size:11px"><?= h($r['user_email']) ?></span>
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
        <div style="margin-top:16px" class="muted">
            Zobrazené: <span class="pill"><?= count($results) ?></span> výsledkov
            <?php if ($filter_course_id > 0 || $filter_lesson_id > 0 || $filter_student_name !== ''): ?>
                <span style="margin-left:12px;font-size:12px">
                    (filtrované)
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

