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
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Administrácia – E-Learn</title>
    <link rel="stylesheet" href="../styles.css">
    <script src="../theme.js" defer></script>
</head>
<body class="admin-body">
<div class="admin-wrap">
    <div class="admin-card">
        <h1>Administrácia</h1>
        <p class="muted">Správa kurzov, lekcií a otázok.</p>

        <?php if (is_teacher()): ?>
            <?php
            $teacherId = current_user_teacher_id();
            if (!$teacherId) {
                $teacherId = get_user_teacher_id($pdo, current_user_id());
                if ($teacherId) {
                    $_SESSION['teacher_id'] = $teacherId;
                }
            }
            if ($teacherId) {
                $stmt = $pdo->prepare('SELECT teacher_code FROM users WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $teacherId]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($teacher && !empty($teacher['teacher_code'])):
                    ?>
                    <div class="mt-16 card teacher-code-card">
                        <strong class="teacher-code-label">Váš kód učiteľa:</strong>
                        <div class="mt-8 teacher-code-value">
                            <?= h($teacher['teacher_code']) ?>
                        </div>
                        <div class="muted inline-text-sm mt-8">
                            Tento kód dajte svojim študentom pre registráciu.
                        </div>
                    </div>
                <?php
                endif;
            }
            ?>
        <?php endif; ?>

        <div class="admin-grid mt-20">
            <div class="admin-item"><a href="courses.php">Kurzy</a><div class="muted">Pridať / upraviť kurzy</div></div>
            <?php if (is_admin()): ?>
                <div class="admin-item"><a href="users.php">Používatelia</a><div class="muted">Zoznam všetkých používateľov</div></div>
            <?php else: ?>
                <div class="admin-item"><a href="users.php">Moji študenti</a><div class="muted">Zoznam mojich študentov</div></div>
            <?php endif; ?>
            <div class="admin-item"><a href="results.php">Výsledky</a><div class="muted">Testy a úspešnosť</div></div>
            <div class="admin-item"><a href="code-results.php">Kódové úlohy</a><div class="muted">Výsledky programovacích úloh</div></div>
            <div class="admin-item"><a href="../2fa-setup.php">2FA nastavenie</a>
                <div class="muted">Zapnúť / vypnúť dvojfaktor</div>
            </div>
            <div class="admin-item"><a href="../index.php">← Späť na web</a><div class="muted">Hlavná stránka</div></div>
        </div>
        <p class="muted inline-text-sm mt-20">
            <strong>Poznámka:</strong> Pre správu lekcií a otázok otvorte kurz a použite odkazy "Lekcie" a "Otázky".
        </p>
    </div>
</div>
</body>
</html>
