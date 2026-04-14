<?php
// Pomocné funkcie pre prihlásenie
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

require __DIR__ . '/vendor/autoload.php';

$pdo = $pdo ?? require __DIR__ . '/config.php';

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function current_user_name(): ?string {
    return $_SESSION['user_name'] ?? null;
}

function is_logged_in(): bool {
    return current_user_id() !== null;
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}
function login_user($userId, $userName) {
    $_SESSION = [];

    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$userId;
    $_SESSION['user_name'] = (string)$userName;
}
function current_user_role(): ?string {
    return $_SESSION['user_role'] ?? null;
}

function is_admin(): bool {
    return current_user_role() === 'admin';
}

function is_teacher(): bool {
    return current_user_role() === 'teacher';
}

function require_admin(): void {
    if (!is_logged_in() || !is_admin()) {
        header('Location: login.php');
        exit;
    }
}

function require_teacher(): void {
    if (!is_logged_in() || !is_teacher()) {
        header('Location: login.php');
        exit;
    }
}

function current_user_teacher_id(): ?int {
    return $_SESSION['teacher_id'] ?? null;
}

function current_user_student_teacher_id(): ?int {
    return $_SESSION['student_teacher_id'] ?? null;
}

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

