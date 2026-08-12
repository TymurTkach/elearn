<?php
// Тестовый файл для диагностики ошибки 500
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Test 1: PHP работает<br>";
echo "Test 2: Проверка подключения к config.php<br>";

try {
    $pdo = require dirname(__DIR__) . '/config.php';
    echo "✓ config.php загружен успешно<br>";
} catch (Exception $e) {
    echo "✗ Ошибка загрузки config.php: " . htmlspecialchars($e->getMessage()) . "<br>";
    exit;
}

echo "Test 3: Проверка auth.php<br>";
try {
    require dirname(__DIR__) . '/auth.php';
    echo "✓ auth.php загружен успешно<br>";
} catch (Exception $e) {
    echo "✗ Ошибка загрузки auth.php: " . htmlspecialchars($e->getMessage()) . "<br>";
    exit;
}

echo "Test 4: Проверка сессии<br>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "✓ Сессия работает<br>";

echo "Test 5: Проверка прав на папку uploads<br>";
$uploadDir = dirname(__DIR__) . '/uploads/courses/';
if (is_dir($uploadDir)) {
    echo "✓ Папка uploads/courses существует<br>";
    if (is_writable($uploadDir)) {
        echo "✓ Папка uploads/courses доступна для записи<br>";
    } else {
        echo "✗ Папка uploads/courses НЕ доступна для записи<br>";
    }
} else {
    echo "✗ Папка uploads/courses НЕ существует<br>";
}

echo "<br>Все тесты завершены!";
?>

