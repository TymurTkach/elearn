<?php
//Spracovanie odpovede od Google OAuth
$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$clientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$clientSecret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';
$redirectUri = defined('GOOGLE_REDIRECT_URI') && GOOGLE_REDIRECT_URI !== ''
    ? GOOGLE_REDIRECT_URI
    : (function () {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        return $protocol . '://' . $host . $path . '/google-callback.php';
    })();

if ($clientId === '' || $clientSecret === '') {
    header('Location: login.php?error=google_not_configured');
    exit;
}

require __DIR__ . '/vendor/autoload.php';

use League\OAuth2\Client\Provider\Google;

// Chyba od Google
if (!empty($_GET['error'])) {
    header('Location: login.php?error=' . urlencode($_GET['error']));
    exit;
}

// Kontrola state (CSRF)
$state = $_GET['state'] ?? '';
unset($_SESSION['oauth2state']);

$code = $_GET['code'] ?? '';
if ($code === '') {
    header('Location: login.php?error=no_code');
    exit;
}

$provider = new Google([
    'clientId'     => $clientId,
    'clientSecret' => $clientSecret,
    'redirectUri'  => $redirectUri,
]);

try {
    $token = $provider->getAccessToken('authorization_code', ['code' => $code]);
    $resourceOwner = $provider->getResourceOwner($token);
} catch (Exception $e) {
    header('Location: login.php?error=oauth_failed');
    exit;
}

$googleId = $resourceOwner->getId();
$email = $resourceOwner->getEmail();
$name = $resourceOwner->getName() ?: $email;

if (empty($email)) {
    header('Location: login.php?error=no_email');
    exit;
}

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

// Hľadáme používateľa podľa google_id alebo email
$stmt = $pdo->prepare('
    SELECT id, name, email, role, totp_enabled, google_id
    FROM users
    WHERE google_id = :gid OR email = :email
    LIMIT 1
');
$stmt->execute(['gid' => $googleId, 'email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Existujúci používateľ – aktualizujeme google_id ak chýba
    $userId = (int)$user['id'];
    if (empty($user['google_id'])) {
        $upd = $pdo->prepare('UPDATE users SET google_id = :gid WHERE id = :id');
        $upd->execute(['gid' => $googleId, 'id' => $userId]);
    }
} else {
    // Nový používateľ – vytvoríme študenta bez učiteľa
    $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $ins = $pdo->prepare('
        INSERT INTO users (name, email, password_hash, role, google_id, created_at)
        VALUES (:name, :email, :hash, :role, :google_id, NOW())
    ');
    $ins->execute([
        'name' => $name,
        'email' => $email,
        'hash' => $passwordHash,
        'role' => 'student',
        'google_id' => $googleId,
    ]);
    $userId = (int)$pdo->lastInsertId();
    $user = [
        'id' => $userId,
        'name' => $name,
        'role' => 'student',
        'totp_enabled' => 0,
    ];
}

$userRole = $user['role'] ?? 'student';
$totpEnabled = (int)($user['totp_enabled'] ?? 0);

if ($totpEnabled === 1) {
    $_SESSION['2fa_user_id'] = $userId;
    $_SESSION['2fa_pending'] = true;
    header('Location: 2fa-verify.php');
    exit;
}

// Prihlásenie bez 2FA
$_SESSION = [];
session_regenerate_id(true);

if (function_exists('login_user')) {
    login_user($userId, (string)$user['name']);
} else {
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = (string)$user['name'];
}
$_SESSION['user_role'] = $userRole;

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
