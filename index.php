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
    <link rel="stylesheet" href="styles.css">
    <script src="theme.js" defer></script>
</head>
<body>
<header class="site-header">
    <h1>E-Learn</h1>
    <small>Jednoduchá platforma pre online vzdelávanie</small>

    <nav class="user-nav">
        <?php if (is_logged_in()): ?>
            <span>Prihlásený ako <?= htmlspecialchars(current_user_name(), ENT_QUOTES) ?></span>
            <?php if (is_admin() || is_teacher()): ?>
                · <a href="admin/index.php">Administrácia</a>
            <?php endif; ?>
            · <a href="dashboard.php">Môj prehľad</a>
            · <a href="compiler.php">Online kompilátor</a>
            · <a href="2fa-setup.php">2FA nastavenie</a>
            · <a href="logout.php">Odhlásiť sa</a>
        <?php else: ?>
            <a href="login.php">Prihlásiť sa</a>
            · <a href="register.php">Registrácia</a>
        <?php endif; ?>

    </nav>
</header>


<main class="container">
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
                        <div class="course-image">
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

<footer class="site-footer">
    © 2026 E-Learn. Bakalársky projekt.
</footer>
</body>
</html>
