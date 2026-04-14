<?php
use OTPHP\TOTP;

$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

// Ak už je používateľ plne prihlásený, pošleme ho na kurzy
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

// Musí existovať rozpracované 2FA po prvom kroku prihlásenia
$pendingUserId = $_SESSION['2fa_user_id'] ?? null;
if (!$pendingUserId || empty($_SESSION['2fa_pending'])) {
    header('Location: login.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim((string)($_POST['code'] ?? ''));

    if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
        $error = 'Zadajte platný 6-miestny kód.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, role, totp_secret FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int)$pendingUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['totp_secret'])) {
            $error = '2FA pre tento účet nie je nastavená.';
        } else {
            $totp = TOTP::create($user['totp_secret']);
            if ($totp->verify($code)) {
                // Úspešné 2FA – dokončíme prihlásenie
                $userId = (int)$user['id'];
                $userRole = $user['role'] ?? 'student';

                $_SESSION = [];
                session_regenerate_id(true);

                if (function_exists('login_user')) {
                    login_user($userId, (string)$user['name']);
                } else {
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_name'] = (string)$user['name'];
                }
                $_SESSION['user_role'] = $userRole;

                // teacher / student väzby
                if ($userRole === 'teacher') {
                    $teacherId = get_user_teacher_id($pdo, $userId);
                    if ($teacherId) {
                        $_SESSION['teacher_id'] = $teacherId;
                    }
                } elseif ($userRole === 'student') {
                    $studentTeacherId = get_user_student_teacher_id($pdo, $userId);
                    if ($studentTeacherId) {
                        $_SESSION['student_teacher_id'] = $studentTeacherId;
                    }
                }

                header('Location: index.php');
                exit;
            } else {
                $error = 'Nesprávny 2FA kód.';
            }
        }
    }
}

function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>2FA overenie – E-Learn</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="styles.css">
    <script src="theme.js" defer></script>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Dvojfaktorové overenie</h1>
        <p class="muted">Zadaj 6-miestny kód z tvojej autentifikačnej aplikácie.</p>

        <?php if ($error): ?>
            <div class="err">• <?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <label for="code">2FA kód</label>
            <input id="code" type="text" name="code" maxlength="6" pattern="\d{6}" required placeholder="123456">

            <button class="btn" type="submit">Overiť</button>
        </form>

        <div class="links">
            <div class="muted"><a href="login.php">← Späť na prihlásenie</a></div>
        </div>
    </div>
</div>
</body>
</html>

