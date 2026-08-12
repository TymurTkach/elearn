<?php
$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

// ak je už prihlásený, pošleme ho preč
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$name = '';
$email = '';
$account_type = 'student';
$teacher_code = '';
$teacher_secret_key = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');
    $account_type = trim((string)($_POST['account_type'] ?? 'student'));
    $teacher_code = strtoupper(trim((string)($_POST['teacher_code'] ?? '')));
    $teacher_secret_key = trim((string)($_POST['teacher_secret_key'] ?? ''));

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Zadaj meno (min. 2 znaky).';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Zadaj platný e-mail.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Heslo musí mať aspoň 6 znakov.';
    }

    if ($password !== $password2) {
        $errors[] = 'Heslá sa nezhodujú.';
    }

    if ($account_type === 'student' && $teacher_code === '') {
        $errors[] = 'Pre registráciu ako študent musíte zadať kód učiteľa.';
    }

    if ($account_type === 'teacher') {
        if ($teacher_secret_key === '') {
            $errors[] = 'Pre registráciu ako učiteľ musíte zadať tajný kľúč.';
        } elseif (!defined('TEACHER_REGISTRATION_KEY') || $teacher_secret_key !== TEACHER_REGISTRATION_KEY) {
            $errors[] = 'Neplatný tajný kľúč pre registráciu učiteľa.';
        }
    }

    if (!$errors) {
        // či email už existuje
        $check = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $check->execute(['email' => $email]);
        if ($check->fetch()) {
            $errors[] = 'Tento e-mail je už zaregistrovaný.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = $account_type === 'teacher' ? 'teacher' : 'student';

            $ins = $pdo->prepare("
                INSERT INTO users (name, email, password_hash, role, created_at)
                VALUES (:name, :email, :hash, :role, NOW())
            ");
            $ins->execute([
                'name' => $name,
                'email' => $email,
                'hash' => $hash,
                'role' => $role,
            ]);

            $userId = (int)$pdo->lastInsertId();

            if ($account_type === 'teacher') {
                // Generujeme jedinečný kód učiteľa
                $code = '';
                do {
                    $code = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
                    $checkCode = $pdo->prepare("SELECT id FROM teachers WHERE code = :code LIMIT 1");
                    $checkCode->execute(['code' => $code]);
                } while ($checkCode->fetch());

                $insTeacher = $pdo->prepare("
                    INSERT INTO teachers (user_id, code, created_at)
                    VALUES (:user_id, :code, NOW())
                ");
                $insTeacher->execute([
                    'user_id' => $userId,
                    'code' => $code,
                ]);
                $teacherId = (int)$pdo->lastInsertId();
            } else {
                // Kontrolujeme kód učiteľa pre študenta
                $checkTeacher = $pdo->prepare("SELECT id FROM teachers WHERE code = :code LIMIT 1");
                $checkTeacher->execute(['code' => $teacher_code]);
                $teacher = $checkTeacher->fetch(PDO::FETCH_ASSOC);

                if (!$teacher) {
                    $errors[] = 'Neplatný kód učiteľa.';
                } else {
                    $insStudent = $pdo->prepare("
                        INSERT INTO students (user_id, teacher_id, created_at)
                        VALUES (:user_id, :teacher_id, NOW())
                    ");
                    $insStudent->execute([
                        'user_id' => $userId,
                        'teacher_id' => (int)$teacher['id'],
                    ]);
                }
            }

            if (!$errors) {
                // Очищаем сессию перед новым логином (предотвращает конфликты в разных вкладках)
                $_SESSION = [];
                session_regenerate_id(true);
                
                // auto login po registrácii
                login_user($userId, $name);
                $_SESSION['user_role'] = $role;

                header('Location: index.php');
                exit;
            }
        }
    }
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Registrácia – E-Learn</title>
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
        label { display:block; margin:12px 0 6px; color:#cbd5e1; font-size:14px; }
        input { width:100%; padding:10px 12px; border-radius:10px; border:1px solid #1e293b; background:#0b1220; color:#e5e7eb; }
        .btn { margin-top:16px; width:100%; background:#38bdf8; color:#020617; border:none; padding:10px 18px; border-radius:999px; font-weight:700; cursor:pointer; }
        .btn:hover { background:#0ea5e9; }
        .err { margin:12px 0; padding:10px 12px; border-radius:12px; background:rgba(220,38,38,.15); border:1px solid #ef4444; font-size:14px; }
        .muted { margin-top:12px; color:#9ca3af; font-size:14px; }
        a { color:#e5e7eb; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Registrácia</h1>

        <?php if ($errors): ?>
            <div class="err">
                <?php foreach ($errors as $e): ?>
                    <div>• <?= h($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <label for="name">Meno</label>
            <input id="name" name="name" value="<?= h($name) ?>" required>

            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="<?= h($email) ?>" required>

            <label for="account_type">Typ účtu</label>
            <select id="account_type" name="account_type" required style="width:100%;padding:10px 12px;border-radius:10px;border:1px solid #1e293b;background:#0b1220;color:#e5e7eb;margin-bottom:12px">
                <option value="student" <?= $account_type === 'student' ? 'selected' : '' ?>>Študent</option>
                <option value="teacher" <?= $account_type === 'teacher' ? 'selected' : '' ?>>Učiteľ</option>
            </select>

            <div id="teacher_code_block" style="<?= $account_type === 'teacher' ? 'display:none' : '' ?>">
                <label for="teacher_code">Kód učiteľa</label>
                <input id="teacher_code" name="teacher_code" value="<?= h($teacher_code) ?>" placeholder="Zadajte 6-miestny kód" maxlength="6" style="text-transform:uppercase">
                <div class="muted" style="font-size:12px;margin-top:4px">Pre registráciu ako študent musíte mať kód od svojho učiteľa.</div>
            </div>

            <div id="teacher_secret_key_block" style="<?= $account_type === 'student' ? 'display:none' : '' ?>">
                <label for="teacher_secret_key">Tajný kľúč pre učiteľa *</label>
                <input id="teacher_secret_key" name="teacher_secret_key" type="password" value="<?= h($teacher_secret_key) ?>" placeholder="Zadajte tajný kľúč" autocomplete="off">
                <div class="muted" style="font-size:12px;margin-top:4px">Tajný kľúč pre registráciu ako učiteľ. Kontaktujte administrátora pre získanie kľúča.</div>
            </div>

            <label for="password">Heslo</label>
            <input id="password" type="password" name="password" required>

            <label for="password2">Heslo znovu</label>
            <input id="password2" type="password" name="password2" required>

            <button class="btn" type="submit">Vytvoriť účet</button>
        </form>

        <script>
            function toggleFields() {
                const accountType = document.getElementById('account_type').value;
                const teacherCodeBlock = document.getElementById('teacher_code_block');
                const teacherCodeInput = document.getElementById('teacher_code');
                const teacherSecretBlock = document.getElementById('teacher_secret_key_block');
                const teacherSecretInput = document.getElementById('teacher_secret_key');
                
                if (accountType === 'student') {
                    // Показываем поле кода учителя для студента
                    teacherCodeBlock.style.display = 'block';
                    teacherCodeInput.required = true;
                    // Скрываем поле секретного ключа
                    teacherSecretBlock.style.display = 'none';
                    teacherSecretInput.required = false;
                    teacherSecretInput.value = '';
                } else {
                    // Скрываем поле кода учителя
                    teacherCodeBlock.style.display = 'none';
                    teacherCodeInput.required = false;
                    teacherCodeInput.value = '';
                    // Показываем поле секретного ключа для учителя
                    teacherSecretBlock.style.display = 'block';
                    teacherSecretInput.required = true;
                }
            }

            // Инициализация при загрузке страницы
            document.addEventListener('DOMContentLoaded', function() {
                toggleFields();
            });

            // Обработчик изменения типа аккаунта
            document.getElementById('account_type').addEventListener('change', toggleFields);
        </script>

        <div class="muted">
            Už máš účet? <a href="login.php">Prihlásiť sa</a>
        </div>
        <div class="muted">
            <a href="index.php">← Späť na hlavnú stránku</a>
        </div>

    </div>
</div>
</body>
</html>
