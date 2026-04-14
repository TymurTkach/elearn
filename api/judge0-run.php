<?php
// API: spustenie kódu cez Judge0 (iba pre prihlásených)
require_once __DIR__ . '/../auth.php';

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

$language_id = (int)($input['language_id'] ?? 0);
$source_code = (string)($input['source_code'] ?? '');
$stdin = (string)($input['stdin'] ?? '');

if ($language_id <= 0 || trim($source_code) === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

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

if ($res === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Judge0 unreachable', 'detail' => $err]);
    exit;
}
if ($code >= 400) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Judge0 error', 'http' => $code, 'detail' => $res]);
    exit;
}

$data = json_decode($res, true);

$output = '';
if (!empty($data['stdout'])) {
    $output .= base64_decode($data['stdout']);
}
if (!empty($data['stderr'])) {
    $output .= "\n[stderr]\n" . base64_decode($data['stderr']);
}
if (!empty($data['compile_output'])) {
    $output .= "\n[compile]\n" . base64_decode($data['compile_output']);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => $data['status']['description'] ?? 'unknown',
    'time' => $data['time'] ?? null,
    'memory' => $data['memory'] ?? null,
    'output' => $output,
]);
