<?php
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_DATABASE') ?: 'CHANGE_ME_DATABASE';
$user = getenv('DB_USERNAME') ?: 'CHANGE_ME_USERNAME';
$pass = getenv('DB_PASSWORD') ?: 'CHANGE_ME_PASSWORD';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo "Database connection failed.";
    exit;
}

define('TEACHER_REGISTRATION_KEY', getenv('TEACHER_REGISTRATION_KEY') ?: 'CHANGE_ME_TEACHER_REGISTRATION_KEY');

define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: 'your-google-client-id');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: 'your-google-client-secret');
define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost/google-callback.php');

return $pdo;
