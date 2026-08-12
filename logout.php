<?php
require __DIR__ . '/auth.php';

// Очищаем все данные сессии
$_SESSION = [];

// Уничтожаем сессию
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Удаляем cookie сессии
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

header('Location: index.php');
exit;
