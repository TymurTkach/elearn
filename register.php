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

    $passwordHash = trim((string)($_POST['password_hash'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    $passwordToHash = $passwordHash ?: $password;
    $account_type = trim((string)($_POST['account_type'] ?? 'student'));
    $teacher_code = strtoupper(trim((string)($_POST['teacher_code'] ?? '')));
    $teacher_secret_key = trim((string)($_POST['teacher_secret_key'] ?? ''));

    if ($name === '' || mb_strlen($name) < 2) {
        $errors[] = 'Zadaj meno (min. 2 znaky).';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Zadaj platný e-mail.';
    }

    if (!$passwordHash) {
        if (strlen($password) < 6) {
            $errors[] = 'Heslo musí mať aspoň 6 znakov.';
        }
        if ($password !== $password2) {
            $errors[] = 'Heslá sa nezhodujú.';
        }
    } else {
        if (strlen($passwordHash) !== 64 || !ctype_xdigit($passwordHash)) {
            $errors[] = 'Neplatný formát hesla.';
        }
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
            $hash = password_hash($passwordToHash, PASSWORD_DEFAULT);
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
                    $checkCode = $pdo->prepare("SELECT id FROM users WHERE teacher_code = :code LIMIT 1");
                    $checkCode->execute(['code' => $code]);
                } while ($checkCode->fetch());

                $updTeacher = $pdo->prepare("
                    UPDATE users
                    SET teacher_code = :code
                    WHERE id = :user_id
                ");
                $updTeacher->execute([
                    'user_id' => $userId,
                    'code' => $code,
                ]);
                $teacherId = $userId;
            } else {
                // Kontrolujeme kód učiteľa pre študenta – hľadáme učiteľa v users
                $checkTeacher = $pdo->prepare("
                    SELECT id FROM users
                    WHERE teacher_code = :code AND role = 'teacher'
                    LIMIT 1
                ");
                $checkTeacher->execute(['code' => $teacher_code]);
                $teacher = $checkTeacher->fetch(PDO::FETCH_ASSOC);

                if (!$teacher) {
                    $errors[] = 'Neplatný kód učiteľa.';
                } else {
                    // Priradíme študenta k učiteľovi cez users.teacher_user_id
                    $updStudent = $pdo->prepare("
                        UPDATE users
                        SET teacher_user_id = :teacher_id
                        WHERE id = :user_id
                    ");
                    $updStudent->execute([
                        'user_id' => $userId,
                        'teacher_id' => (int)$teacher['id'],
                    ]);
                }
            }

            if (!$errors) {
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
    <link rel="stylesheet" href="styles.css">
    <script src="theme.js" defer></script>
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
            <select id="account_type" name="account_type" required>
                <option value="student" <?= $account_type === 'student' ? 'selected' : '' ?>>Študent</option>
                <option value="teacher" <?= $account_type === 'teacher' ? 'selected' : '' ?>>Učiteľ</option>
            </select>

            <div id="teacher_code_block" class="<?= $account_type === 'teacher' ? 'hidden' : '' ?>">
                <label for="teacher_code">Kód učiteľa</label>
                <input id="teacher_code" name="teacher_code" value="<?= h($teacher_code) ?>" placeholder="Zadajte 6-miestny kód" maxlength="6" class="uppercase">
                <div class="muted inline-text-sm mt-4">Pre registráciu ako študent musíte mať kód od svojho učiteľa.</div>
            </div>

            <div id="teacher_secret_key_block" class="<?= $account_type === 'student' ? 'hidden' : '' ?>">
                <label for="teacher_secret_key">Tajný kľúč pre učiteľa *</label>
                <input id="teacher_secret_key" name="teacher_secret_key" type="password" value="<?= h($teacher_secret_key) ?>" placeholder="Zadajte tajný kľúč" autocomplete="off">
                <div class="muted inline-text-sm mt-4">Tajný kľúč pre registráciu ako učiteľ. Kontaktujte administrátora pre získanie kľúča.</div>
            </div>

            <label for="password">Heslo</label>
            <input id="password" type="password" name="password" required>
            <input type="hidden" name="password_hash" id="password_hash">

            <label for="password2">Heslo znovu</label>
            <input id="password2" type="password" name="password2" required>

            <button class="btn" type="submit">Vytvoriť účet</button>
        </form>

        <div class="mt-16" style="text-align:center;">
            <span class="muted inline-text-sm">— alebo —</span>
        </div>
        <div class="mt-12" style="text-align:center;">
            <a href="google-login.php" class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M16.51 8H8.98v3h4.3c-.18 1-.74 1.48-1.6 2.04v2.01h2.6a7.8 7.8 0 0 0 2.38-5.88c0-.57-.05-.66-.15-1.18z"/><path fill="#34A853" d="M8.98 17c2.16 0 3.97-.72 5.3-1.94l-2.6-2a4.8 4.8 0 0 1-7.4-2.54H1.83v2.07A8 8 0 0 0 8.98 17z"/><path fill="#FBBC05" d="M4.5 10.52a4.8 4.8 0 0 1 0-3.04V5.41H1.83a8 8 0 0 0 0 7.18l2.67-2.07z"/><path fill="#EA4335" d="M8.98 4.18c1.17 0 2.23.4 3.06 1.2l2.3-2.3A8 8 0 0 0 1.83 5.4L4.5 7.49a4.77 4.77 0 0 1 4.48-3.3z"/></svg>
                Registrovať sa cez Google
            </a>
        </div>
        <p class="muted inline-text-sm mt-8" style="text-align:center;">Pri registrácii cez Google sa vytvorí študentský účet. Kód učiteľa môžete pridať neskôr.</p>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
        <script>
            document.querySelector('form').addEventListener('submit', function(e) {
                const passwordInput = document.getElementById('password');
                const passwordHashInput = document.getElementById('password_hash');
                const password2Input = document.getElementById('password2');

                if (passwordInput.value !== password2Input.value) {
                    return;
                }

                if (passwordInput.value && typeof CryptoJS !== 'undefined') {
                    try {
                        const hash = CryptoJS.SHA256(passwordInput.value).toString();
                        passwordHashInput.value = hash;
                    } catch (err) {
                        console.error('Hash error:', err);
                    }
                }
            });
        </script>

        <script>
            function toggleFields() {
                const accountType = document.getElementById('account_type').value;
                const teacherCodeBlock = document.getElementById('teacher_code_block');
                const teacherCodeInput = document.getElementById('teacher_code');
                const teacherSecretBlock = document.getElementById('teacher_secret_key_block');
                const teacherSecretInput = document.getElementById('teacher_secret_key');

                if (accountType === 'student') {
                    teacherCodeBlock.style.display = 'block';
                    teacherCodeInput.required = true;
                    teacherSecretBlock.style.display = 'none';
                    teacherSecretInput.required = false;
                    teacherSecretInput.value = '';
                } else {
                    teacherCodeBlock.style.display = 'none';
                    teacherCodeInput.required = false;
                    teacherCodeInput.value = '';
                    teacherSecretBlock.style.display = 'block';
                    teacherSecretInput.required = true;
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                toggleFields();
            });
            
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
