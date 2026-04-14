<?php
require __DIR__ . '/auth.php';

$_SESSION = [];

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

header('Location: index.php');
exit;
