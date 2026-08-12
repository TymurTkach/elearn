<?php
$host = getenv('DB_HOST') ?: 'localhost';
$db = getenv('DB_NAME') ?: 'elearn';
$user = getenv('DB_USER') ?: 'example_user';
$pass = getenv('DB_PASSWORD') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo "DB connection failed: " . htmlspecialchars($e->getMessage());
    exit;
}

define('TEACHER_REGISTRATION_KEY', getenv('TEACHER_REGISTRATION_KEY') ?: '');

return $pdo;
