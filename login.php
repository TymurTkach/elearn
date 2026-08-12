<?php
$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

// Funkcie pre získanie teacher_id (ak ešte nie sú načítané)
if (!function_exists('get_user_teacher_id')) {
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
}

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $userId = (int)$user['id'];
        $userRole = $user['role'] ?? 'student';

        // Очищаем сессию перед новым логином (предотвращает конфликты в разных вкладках)
        $_SESSION = [];
        session_regenerate_id(true);

        if (function_exists('login_user')) {
            login_user($userId, (string)$user['name']);
        } else {
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = (string)$user['name'];
        }
        $_SESSION['user_role'] = $userRole;

        // Ukladáme teacher_id ak je to učiteľ
        if ($userRole === 'teacher') {
            $teacherId = get_user_teacher_id($pdo, $userId);
            if ($teacherId) {
                $_SESSION['teacher_id'] = $teacherId;
            }
        } elseif ($userRole === 'student') {
            // Ukladáme teacher_id študenta
            $studentTeacherId = get_user_student_teacher_id($pdo, $userId);
            if ($studentTeacherId) {
                $_SESSION['student_teacher_id'] = $studentTeacherId;
            }
        }

        header('Location: index.php');
        exit;
    } else {
        $error = 'Nesprávny e-mail alebo heslo.';
    }
}

function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Prihlásenie – E-Learn</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body { margin:0; font-family:system-ui,-apple-system,"Segoe UI",sans-serif; background:#0f172a; color:#e5e7eb; }
        .wrap { max-width:520px; margin:60px auto; padding:0 16px; }
        .card { background:#020617; border:1px solid #1e293b; border-radius:16px; padding:24px; }
        h1 { margin:0 0 12px; font-size:24px; }
        .muted { margin-top:12px; color:#9ca3af; font-size:14px; }
        label { display:block; margin:12px 0 6px; color:#cbd5e1; font-size:14px; }
        input { width:100%; padding:10px 12px; border-radius:10px; border:1px solid #1e293b; background:#0b1220; color:#e5e7eb; }
        .btn { margin-top:16px; width:100%; background:#38bdf8; color:#020617; border:none; padding:10px 18px; border-radius:999px; font-weight:700; cursor:pointer; }
        .btn:hover { background:#0ea5e9; }
        .err { margin:12px 0; padding:10px 12px; border-radius:12px; background:rgba(220,38,38,.15); border:1px solid #ef4444; font-size:14px; }
        a { color:#e5e7eb; text-decoration:none; }
        a:hover { text-decoration:underline; }
        .links { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-top:14px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Prihlásenie</h1>

        <?php if ($error): ?>
            <div class="err">• <?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="<?= h($email) ?>" required>

            <label for="password">Heslo</label>
            <input id="password" type="password" name="password" required>

            <button class="btn" type="submit">Prihlásiť sa</button>
        </form>

        <div class="links">
            <div class="muted"><a href="index.php">← Späť na kurzy</a></div>
            <div class="muted">Nemáš účet? <a href="register.php">Registrácia</a></div>
        </div>
    </div>
</div>
</body>
</html>
