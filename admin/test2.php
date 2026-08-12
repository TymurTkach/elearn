<?php
// Расширенный тест для диагностики ошибки 500 в admin/index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Расширенная диагностика admin/index.php</h2>";

echo "<h3>Test 1: Загрузка config.php</h3>";
try {
    $pdo = require dirname(__DIR__) . '/config.php';
    echo "✓ config.php загружен успешно<br>";
    echo "✓ PDO объект создан<br>";
} catch (Exception $e) {
    echo "✗ Ошибка: " . htmlspecialchars($e->getMessage()) . "<br>";
    exit;
}

echo "<h3>Test 2: Загрузка auth.php</h3>";
try {
    require dirname(__DIR__) . '/auth.php';
    echo "✓ auth.php загружен успешно<br>";
} catch (Exception $e) {
    echo "✗ Ошибка: " . htmlspecialchars($e->getMessage()) . "<br>";
    exit;
}

echo "<h3>Test 3: Проверка сессии</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "✓ Сессия работает<br>";
echo "Session ID: " . session_id() . "<br>";

echo "<h3>Test 4: Проверка функций авторизации</h3>";
echo "current_user_id(): " . var_export(current_user_id(), true) . "<br>";
echo "current_user_name(): " . var_export(current_user_name(), true) . "<br>";
echo "current_user_role(): " . var_export(current_user_role(), true) . "<br>";
echo "is_logged_in(): " . var_export(is_logged_in(), true) . "<br>";
echo "is_admin(): " . var_export(is_admin(), true) . "<br>";
echo "is_teacher(): " . var_export(is_teacher(), true) . "<br>";

echo "<h3>Test 5: Проверка require_login()</h3>";
try {
    if (!is_logged_in()) {
        echo "⚠ Пользователь не залогинен - require_login() перенаправит на login.php<br>";
    } else {
        echo "✓ Пользователь залогинен<br>";
    }
} catch (Exception $e) {
    echo "✗ Ошибка: " . htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<h3>Test 6: Проверка доступа к администрации</h3>";
if (!is_admin() && !is_teacher()) {
    echo "⚠ Пользователь не является админом или учителем<br>";
} else {
    echo "✓ Пользователь имеет доступ к администрации<br>";
}

echo "<h3>Test 7: Проверка функций для учителя</h3>";
if (is_teacher()) {
    try {
        $teacherId = current_user_teacher_id();
        echo "current_user_teacher_id(): " . var_export($teacherId, true) . "<br>";
        
        if (!$teacherId && is_logged_in()) {
            $userId = current_user_id();
            echo "Попытка получить teacher_id из БД для user_id: " . $userId . "<br>";
            $teacherId = get_user_teacher_id($pdo, $userId);
            echo "get_user_teacher_id(): " . var_export($teacherId, true) . "<br>";
        }
        
        if ($teacherId) {
            echo "Попытка выполнить запрос к таблице teachers...<br>";
            $stmt = $pdo->prepare('SELECT code FROM teachers WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $teacherId]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($teacher) {
                echo "✓ Запрос выполнен успешно, код учителя: " . htmlspecialchars($teacher['code']) . "<br>";
            } else {
                echo "⚠ Учитель с id=$teacherId не найден в БД<br>";
            }
        } else {
            echo "⚠ teacher_id не найден<br>";
        }
    } catch (Exception $e) {
        echo "✗ Ошибка при работе с учителем: " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "Stack trace: <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
} else {
    echo "Пользователь не является учителем, пропускаем тест<br>";
}

echo "<h3>Test 8: Проверка функции h()</h3>";
try {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    $test = h('<script>alert("test")</script>');
    echo "✓ Функция h() работает: " . $test . "<br>";
} catch (Exception $e) {
    echo "✗ Ошибка: " . htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<br><h2>Все тесты завершены!</h2>";
echo "<p><a href='index.php'>Попробовать открыть admin/index.php</a></p>";
?>

