<?php
$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

// Funkcie pre získanie teacher_id (ak ešte nie sú načítané)
if (!function_exists('get_user_teacher_id')) {
    function get_user_teacher_id($pdo, $userId): ?int {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id AND role = "teacher" LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    function get_user_student_teacher_id($pdo, $userId): ?int {
        $stmt = $pdo->prepare('SELECT teacher_user_id FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row['teacher_user_id']) ? (int)$row['teacher_user_id'] : null;
    }
}

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = null;
$email = '';

// Chyby z Google OAuth callback
$oauthErrors = [
    'google_not_configured' => 'Google prihlásenie nie je nakonfigurované. Kontaktujte administrátora.',
    'invalid_state' => 'Neplatná OAuth odpoveď. Skúste znova.',
    'no_code' => 'Google nevrátil autorizačný kód.',
    'oauth_failed' => 'Prihlásenie cez Google zlyhalo.',
    'no_email' => 'Google účet nemá e-mail.',
];
if (!empty($_GET['error']) && isset($oauthErrors[$_GET['error']])) {
    $error = $oauthErrors[$_GET['error']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $passwordHash = trim((string)($_POST['password_hash'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, totp_enabled FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $loginSuccess = false;

    if ($user) {

        if ($passwordHash && strlen($passwordHash) === 64) {
            $loginSuccess = password_verify($passwordHash, $user['password_hash']);
        }

        if (!$loginSuccess && $password && strlen($password) > 0) {
            $loginSuccess = password_verify($password, $user['password_hash']);
        }
    }

    if ($loginSuccess) {
        $userId = (int)$user['id'];
        $userRole = $user['role'] ?? 'student';
        $totpEnabled = (int)($user['totp_enabled'] ?? 0);

        if ($totpEnabled === 1) {
            // 2FA zapnutá – uložíme do session rozpracovaný login a presmerujeme na 2fa-verify
            $_SESSION['2fa_user_id'] = $userId;
            $_SESSION['2fa_pending'] = true;

            header('Location: 2fa-verify.php');
            exit;
        } else {
            // Bez 2FA – dokončíme login
            $_SESSION = [];
            session_regenerate_id(true);

            if (function_exists('login_user')) {
                login_user($userId, (string)$user['name']);
            } else {
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = (string)$user['name'];
            }
            $_SESSION['user_role'] = $userRole;

            // Ukladáme teacher_id ak je to učiteľ
            if ($userRole === 'teacher') {
                $teacherId = get_user_teacher_id($pdo, $userId);
                if ($teacherId) {
                    $_SESSION['teacher_id'] = $teacherId;
                }
            } elseif ($userRole === 'student') {
                // Ukladáme teacher_id študenta
                $studentTeacherId = get_user_student_teacher_id($pdo, $userId);
                if ($studentTeacherId) {
                    $_SESSION['student_teacher_id'] = $studentTeacherId;
                }
            }

            header('Location: index.php');
            exit;
        }
    } else {
        $error = 'Nesprávny e-mail alebo heslo.';
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
    <title>Prihlásenie – E-Learn</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="styles.css">
    <script src="theme.js" defer></script>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Prihlásenie</h1>

        <?php if ($error): ?>
            <div class="err">• <?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off" id="login-form">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="<?= h($email) ?>" required>

            <label for="password">Heslo</label>
            <input id="password" type="password" name="password" required>
            <input type="hidden" name="password_hash" id="password_hash">

            <button class="btn" type="submit">Prihlásiť sa</button>
        </form>

        <div class="mt-16" style="text-align:center;">
            <span class="muted inline-text-sm">— alebo —</span>
        </div>
        <div class="mt-12" style="text-align:center;">
            <a href="google-login.php" class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M16.51 8H8.98v3h4.3c-.18 1-.74 1.48-1.6 2.04v2.01h2.6a7.8 7.8 0 0 0 2.38-5.88c0-.57-.05-.66-.15-1.18z"/><path fill="#34A853" d="M8.98 17c2.16 0 3.97-.72 5.3-1.94l-2.6-2a4.8 4.8 0 0 1-7.4-2.54H1.83v2.07A8 8 0 0 0 8.98 17z"/><path fill="#FBBC05" d="M4.5 10.52a4.8 4.8 0 0 1 0-3.04V5.41H1.83a8 8 0 0 0 0 7.18l2.67-2.07z"/><path fill="#EA4335" d="M8.98 4.18c1.17 0 2.23.4 3.06 1.2l2.3-2.3A8 8 0 0 0 1.83 5.4L4.5 7.49a4.77 4.77 0 0 1 4.48-3.3z"/></svg>
                Prihlásiť sa cez Google
            </a>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
        <script>
            document.getElementById('login-form').addEventListener('submit', function(e) {
                const passwordInput = document.getElementById('password');
                const passwordHashInput = document.getElementById('password_hash');

                if (passwordInput.value && typeof CryptoJS !== 'undefined') {
                    try {
                        const hash = CryptoJS.SHA256(passwordInput.value).toString();
                        passwordHashInput.value = hash;
                    } catch (err) {
                        console.error('Ошибка хеширования:', err);
                    }
                }
            });
        </script>

        <div class="links">
            <div class="muted"><a href="index.php">← Späť na kurzy</a></div>
            <div class="muted">Nemáš účet? <a href="register.php">Registrácia</a></div>
        </div>
    </div>
</div>
</body>
</html>
