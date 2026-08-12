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
    <style>
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#0f172a;color:#e5e7eb}
        .wrap{max-width:900px;margin:40px auto;padding:0 16px}
        .card{background:#020617;border:1px solid #1e293b;border-radius:16px;padding:20px}
        a{color:#e5e7eb;text-decoration:none}
        a:hover{text-decoration:underline}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}
        .item{padding:14px;border:1px solid #1e293b;border-radius:14px;background:#0b1220}
        .muted{color:#9ca3af}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
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
                $stmt = $pdo->prepare('SELECT code FROM teachers WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $teacherId]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($teacher):
            ?>
                <div style="margin-top:16px;padding:12px;background:#0b1220;border-radius:8px;border:1px solid #1e293b">
                    <strong style="color:#38bdf8">Váš kód učiteľa:</strong>
                    <div style="margin-top:8px;font-size:24px;font-weight:700;letter-spacing:2px;color:#22c55e">
                        <?= h($teacher['code']) ?>
                    </div>
                    <div class="muted" style="margin-top:8px;font-size:12px">
                        Tento kód dajte svojim študentom pre registráciu.
                    </div>
                </div>
            <?php
                endif;
            }
            ?>
        <?php endif; ?>

        <div class="grid" style="margin-top:20px">
            <div class="item"><a href="courses.php">Kurzy</a><div class="muted">Pridať / upraviť kurzy</div></div>
            <?php if (is_admin()): ?>
                <div class="item"><a href="users.php">Používatelia</a><div class="muted">Zoznam všetkých používateľov</div></div>
            <?php else: ?>
                <div class="item"><a href="users.php">Moji študenti</a><div class="muted">Zoznam mojich študentov</div></div>
            <?php endif; ?>
            <div class="item"><a href="results.php">Výsledky</a><div class="muted">Testy a úspešnosť</div></div>
            <div class="item"><a href="../index.php">← Späť na web</a><div class="muted">Hlavná stránka</div></div>
        </div>
        <p class="muted" style="margin-top:20px;font-size:13px">
            <strong>Poznámka:</strong> Pre správu lekcií a otázok otvorte kurz a použite odkazy "Lekcie" a "Otázky".
        </p>
    </div>
</div>
</body>
</html>
