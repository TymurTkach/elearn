<?php
// auth.php – pomocné funkcie pre prihlásenie
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = $pdo ?? require dirname(__FILE__) . '/config.php';

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
    // Очищаем все данные сессии перед новым логином
    // Это предотвращает конфликты при логине в разных вкладках
    $_SESSION = [];
    
    // Регенерируем ID сессии для безопасности
    session_regenerate_id(true);
    
    // Устанавливаем новые данные пользователя
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
    $stmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    return $teacher ? (int)$teacher['id'] : null;
}

function get_user_student_teacher_id($pdo, $userId): ?int {
    $stmt = $pdo->prepare('
        SELECT s.teacher_id 
        FROM students s 
        WHERE s.user_id = :user_id 
        LIMIT 1
    ');
    $stmt->execute(['user_id' => $userId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    return $student ? (int)$student['teacher_id'] : null;
}

