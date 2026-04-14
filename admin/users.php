<?php
$pdo = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/auth.php';
require_login();

if (!is_admin() && !is_teacher()) {
    header('Location: ../login.php');
    exit;
}

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
        // Študenti učiteľa sú teraz používatelia s teacher_user_id = id učiteľa
        $stmt = $pdo->prepare('
            SELECT u.id, u.name, u.email, u.role, u.created_at
            FROM users u
            WHERE u.teacher_user_id = :teacher_id
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
    <link rel="stylesheet" href="../styles.css">
    <script src="../theme.js" defer></script>
</head>
<body class="admin-body">
<div class="admin-wrap">
    <div class="admin-card">
        <div class="muted"><a href="index.php">← Administrácia</a></div>
        <h1>Používatelia</h1>
        <p class="muted">Zoznam všetkých registrovaných používateľov.</p>

        <table class="admin-table mt-20">
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
        <div class="muted mt-16">
            Celkom: <span class="pill"><?= count($users) ?></span> používateľov
        </div>
    </div>
</div>
</body>
</html>

