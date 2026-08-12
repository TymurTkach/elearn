<?php
$pdo = require __DIR__ . '/config.php';

echo "<h1>DB test</h1>";

try {
    $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM users');
    $row = $stmt->fetch();
    echo "<p>Connection OK. Users in table: <strong>" . (int)$row['cnt'] . "</strong></p>";
} catch (Throwable $e) {
    echo "<p style='color:red'>Query failed: "
        . htmlspecialchars($e->getMessage())
        . "</p>";
}
