<?php
// Pripájame PDO
$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

// Načítame zoznam kurzov s ohľadom na teacher_id
try {
    $teacherId = null;

    if (is_logged_in()) {
        $userRole = current_user_role();

        if ($userRole === 'teacher') {
            // Učiteľ vidí len svoje kurzy
            $teacherId = current_user_teacher_id();
            if (!$teacherId) {
                // Ak teacher_id nie je v session, načítame z DB
                $teacherId = get_user_teacher_id($pdo, current_user_id());
                if ($teacherId) {
                    $_SESSION['teacher_id'] = $teacherId;
                }
            }
        } elseif ($userRole === 'student') {
            // Študent vidí len kurzy svojho učiteľa
            $teacherId = current_user_student_teacher_id();
            if (!$teacherId) {
                // Ak teacher_id nie je v session, načítame z DB
                $teacherId = get_user_student_teacher_id($pdo, current_user_id());
                if ($teacherId) {
                    $_SESSION['student_teacher_id'] = $teacherId;
                }
            }
        }
    }

    if ($teacherId !== null) {
        // Filtrujeme kurzy podľa teacher_id
        $stmt = $pdo->prepare(
            'SELECT id, title, description, image, created_at 
             FROM courses 
             WHERE teacher_id = :teacher_id
             ORDER BY created_at DESC'
        );
        $stmt->execute(['teacher_id' => $teacherId]);
        $courses = $stmt->fetchAll();
    } else {
        // Pre neprihlásených alebo adminov zobrazujeme všetky kurzy
        $stmt = $pdo->query(
            'SELECT id, title, description, image, created_at 
             FROM courses 
             ORDER BY created_at DESC'
        );
        $courses = $stmt->fetchAll();
    }

    $error = null;
} catch (Throwable $e) {
    $courses = [];
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>E-Learn – Kurzy</title>
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
        }
        header h1 {
            margin: 0;
            font-size: 24px;
        }
        header small {
            color: #8f9bb3;
        }
        .user-nav {
            margin-top: 12px;
            font-size: 14px;
        }
        .user-nav a {
            color: #9ca3af;
            text-decoration: none;
        }
        .user-nav a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        main {
            max-width: 960px;
            margin: 40px auto 80px;
            padding: 0 16px;
        }
        h2 {
            font-size: 28px;
            margin-bottom: 24px;
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
        }
        .course-card {
            background: #0b1020;
            border-radius: 12px;
            padding: 0;
            border: 1px solid #1f2435;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.5);
        }
        .course-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }
        .course-content {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .course-title {
            font-size: 18px;
            font-weight: 600;
        }
        .course-description {
            font-size: 14px;
            color: #a3aec7;
        }
        .course-meta {
            font-size: 12px;
            color: #6b7385;
        }
        .course-link {
            margin-top: 12px;
        }
        .course-link a {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            font-size: 13px;
        }
        .course-link a:hover {
            background: #2563eb;
        }
        .empty {
            margin-top: 40px;
            text-align: center;
            color: #8f9bb3;
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
    <h1>E-Learn</h1>
    <small>Jednoduchá platforma pre online vzdelávanie</small>

    <nav class="user-nav">
        <?php if (is_logged_in()): ?>
            <span>Prihlásený ako <?= htmlspecialchars(current_user_name(), ENT_QUOTES) ?></span>
            <?php if (is_admin() || is_teacher()): ?>
                · <a href="admin/index.php">Administrácia</a>
            <?php endif; ?>
            · <a href="dashboard.php">Môj prehľad</a>
            · <a href="logout.php">Odhlásiť sa</a>
        <?php else: ?>
            <a href="login.php">Prihlásiť sa</a>
            · <a href="register.php">Registrácia</a>
        <?php endif; ?>

    </nav>
</header>


<main>
    <h2>Kurzy</h2>

    <?php if ($error): ?>
        <div class="error">
            Chyba pri načítaní kurzov: <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!$error && empty($courses)): ?>
        <p class="empty">
            Zatiaľ nie sú pridané žiadne kurzy. Pridajte ich do tabuľky <strong>courses</strong> v databáze.
        </p>
    <?php elseif (!$error): ?>
        <div class="courses-grid">
            <?php foreach ($courses as $course): ?>
                <article class="course-card">
                    <?php if (!empty($course['image'])): ?>
                        <img src="<?= htmlspecialchars($course['image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" 
                             alt="<?= htmlspecialchars($course['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" 
                             class="course-image">
                    <?php else: ?>
                        <div class="course-image" style="display:flex;align-items:center;justify-content:center;color:#475569;font-size:48px;">
                            📚
                        </div>
                    <?php endif; ?>
                    <div class="course-content">
                        <div class="course-title">
                            <?= htmlspecialchars($course['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </div>
                        <div class="course-description">
                            <?= nl2br(htmlspecialchars($course['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?>
                        </div>
                        <div class="course-meta">
                            Vytvorený: <?= htmlspecialchars($course['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </div>
                        <div class="course-link">
                            <a href="course.php?id=<?= (int)$course['id'] ?>">
                                Otvoriť kurz →
                            </a>
                        </div>
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
