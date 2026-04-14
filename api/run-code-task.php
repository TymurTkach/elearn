<?php
// API: spustenie kódovej úlohy – volá Judge0, porovná výstup s očakávaným, uloží výsledok
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../gamification.php';

if (!is_logged_in()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Bad JSON']);
    exit;
}

$task_id = (int)($input['task_id'] ?? 0);
$source_code = (string)($input['source_code'] ?? '');
$language_id = isset($input['language_id']) ? (int)$input['language_id'] : 0;
$stdin = (string)($input['stdin'] ?? '');

if ($task_id <= 0 || trim($source_code) === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Missing task_id or source_code']);
    exit;
}

$pdo = require __DIR__ . '/../config.php';

$stmt = $pdo->prepare('
    SELECT ct.id, ct.lesson_id, ct.expected_output, ct.language_id AS default_language_id, ct.stdin AS task_stdin
    FROM code_tasks ct
    JOIN lessons l ON l.id = ct.lesson_id
    JOIN courses c ON c.id = l.course_id
    WHERE ct.id = :task_id
    LIMIT 1
');
$stmt->execute(['task_id' => $task_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Task not found']);
    exit;
}

$language_id = $language_id > 0 ? $language_id : (int)$task['default_language_id'];
$stdin = $stdin !== '' ? $stdin : (string)($task['task_stdin'] ?? '');

$JUDGE0 = 'http://127.0.0.1:2358';

$create = [
    'language_id' => $language_id,
    'source_code' => base64_encode($source_code),
    'stdin'       => base64_encode($stdin),
];

$ch = curl_init($JUDGE0 . '/submissions/?base64_encoded=true&wait=true');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($create),
    CURLOPT_TIMEOUT => 30,
]);
$res = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$output = '';
$statusDesc = 'unknown';

if ($res === false) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'output' => '[Chyba] Judge0 nedostupný: ' . $err,
        'passed' => false,
        'error' => true,
    ]);
    exit;
}

if ($code >= 400) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'output' => '[Chyba] Judge0 HTTP ' . $code,
        'passed' => false,
        'error' => true,
    ]);
    exit;
}

$data = json_decode($res, true);
if (!empty($data['stdout'])) {
    $output .= base64_decode($data['stdout']);
}
if (!empty($data['stderr'])) {
    $output .= "\n[stderr]\n" . base64_decode($data['stderr']);
}
if (!empty($data['compile_output'])) {
    $output .= "\n[compile]\n" . base64_decode($data['compile_output']);
}
$statusDesc = $data['status']['description'] ?? 'unknown';

// Normalizácia pre porovnanie: trim, jednotné konce riadkov
$normalize = function ($s) {
    $s = (string) $s;
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = trim($s);
    return $s;
};

$expected = $normalize($task['expected_output']);
$actual = $normalize($output);
$passed = ($expected === $actual);

// Uložíme výsledok do DB
$userId = current_user_id();
if ($userId) {
    $ins = $pdo->prepare('
        INSERT INTO code_task_results (user_id, code_task_id, source_code, output, passed, created_at)
        VALUES (:user_id, :code_task_id, :source_code, :output, :passed, NOW())
    ');
    $ins->execute([
        'user_id' => $userId,
        'code_task_id' => $task_id,
        'source_code' => $source_code,
        'output' => $output,
        'passed' => $passed ? 1 : 0,
    ]);
    if ($passed) {
        gamification_after_code_task_pass($pdo, $userId);
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'output' => $output,
    'passed' => $passed,
    'status' => $statusDesc,
]);
