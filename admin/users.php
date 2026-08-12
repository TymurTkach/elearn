<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

// Учителя и админы могут заходить в админку
if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

// Načítame používateľov s ohľadom na práva prístupu
if (is_admin()) {
    // Admin vidí všetkých používateľov
    $stmt = $pdo->query('
        SELECT id, name, email, role, created_at
        FROM users
        ORDER BY created_at DESC
    ');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Učiteľ vidí len svojich študentov
    $teacherId = current_user_teacher_id();
    if (!$teacherId) {
        $teacherId = get_user_teacher_id($pdo, current_user_id());
        if ($teacherId) {
            $_SESSION['teacher_id'] = $teacherId;
        }
    }

    if ($teacherId) {
        $stmt = $pdo->prepare('
            SELECT u.id, u.name, u.email, u.role, u.created_at
            FROM users u
            JOIN students s ON u.id = s.user_id
            WHERE s.teacher_id = :teacher_id
            ORDER BY u.created_at DESC
        ');
        $stmt->execute(['teacher_id' => $teacherId]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $users = [];
    }
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Používatelia – Administrácia</title>
    <style>
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#0f172a;color:#e5e7eb}
        .wrap{max-width:1000px;margin:40px auto;padding:0 16px}
        .card{background:#020617;border:1px solid #1e293b;border-radius:16px;padding:20px;margin-bottom:14px}
        a{color:#e5e7eb;text-decoration:none}
        a:hover{text-decoration:underline}
        .muted{color:#9ca3af}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px;border-bottom:1px solid #1e293b;text-align:left;vertical-align:top}
        th{color:#cbd5e1;font-size:13px}
        .role-admin{color:#38bdf8;font-weight:600}
        .role-student{color:#9ca3af}
        .pill{display:inline-block;padding:4px 10px;border-radius:999px;border:1px solid #1e293b;background:#0b1220;font-size:12px;color:#cbd5e1}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="muted"><a href="index.php">← Administrácia</a></div>
        <h1>Používatelia</h1>
        <p class="muted">Zoznam všetkých registrovaných používateľov.</p>

        <table style="margin-top:20px">
            <thead>
            <tr>
                <th>ID</th>
                <th>Meno</th>
                <th>E-mail</th>
                <th>Rola</th>
                <th>Registrovaný</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5" class="muted">Zatiaľ nie sú registrovaní žiadni používatelia.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= (int)$u['id'] ?></td>
                        <td><strong><?= h($u['name']) ?></strong></td>
                        <td><?= h($u['email']) ?></td>
                        <td>
                            <span class="<?= $u['role'] === 'admin' ? 'role-admin' : 'role-student' ?>">
                                <?= h($u['role'] === 'admin' ? 'Administrátor' : 'Študent') ?>
                            </span>
                        </td>
                        <td class="muted"><?= h($u['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <div style="margin-top:16px" class="muted">
            Celkom: <span class="pill"><?= count($users) ?></span> používateľov
        </div>
    </div>
</div>
</body>
</html>

