<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Nastavenie dvojfaktorovej autentifikácie (TOTP)
use OTPHP\TOTP;

$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
require_login();

$userId = current_user_id();

// Načítame používateľa
$stmt = $pdo->prepare('SELECT id, name, email, totp_secret, totp_enabled FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo 'Používateľ nenájdený.';
    exit;
}

$errors = [];
$success = null;

// Vypnutie 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'disable') {
    $upd = $pdo->prepare('UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = :id');
    $upd->execute(['id' => $userId]);

    unset($_SESSION['2fa_setup_secret']);

    $user['totp_secret'] = null;
    $user['totp_enabled'] = 0;
    $success = 'Dvojfaktorová autentifikácia bola vypnutá.';
}

// Zapnutie / potvrdenie 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    $code = trim((string)($_POST['code'] ?? ''));

    if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
        $errors[] = 'Zadajte platný 6-miestny kód z aplikácie.';
    } elseif (empty($_SESSION['2fa_setup_secret'])) {
        $errors[] = 'Chýba tajný kľúč pre 2FA. Skúste stránku obnoviť.';
    } else {
        $secret = (string)$_SESSION['2fa_setup_secret'];
        $totp = TOTP::create($secret);
        $isValid = $totp->verify($code);

        if ($isValid) {
            // Uložíme do DB a zapneme 2FA
            $upd = $pdo->prepare('UPDATE users SET totp_secret = :secret, totp_enabled = 1 WHERE id = :id');
            $upd->execute([
                'secret' => $secret,
                'id' => $userId,
            ]);

            unset($_SESSION['2fa_setup_secret']);

            $user['totp_secret'] = $secret;
            $user['totp_enabled'] = 1;
            $success = 'Dvojfaktorová autentifikácia bola úspešne zapnutá.';
        } else {
            $errors[] = 'Nesprávny kód. Skontrolujte aplikáciu a skúste znova.';
        }
    }
}

// Príprava údajov pre zobrazenie QR kódu (iba ak 2FA nie je zapnutá)
$qrUrl = null;
$plainSecret = null;

if ((int)$user['totp_enabled'] === 0) {
    // Vygenerujeme alebo použijeme tajný kľúč v session, aby sa pri refreshoch nemenil
    if (empty($_SESSION['2fa_setup_secret'])) {
        $totp = TOTP::create(); // predvolené: 30s, SHA1, 6 číslic
        $_SESSION['2fa_setup_secret'] = $totp->getSecret();
    }

    $plainSecret = (string)$_SESSION['2fa_setup_secret'];

    // Znova vytvoríme TOTP objekt pre otpauth URI
    $totp = TOTP::create($plainSecret);
    $label = $user['email'] ?: ($user['name'] ?? ('user-' . $userId));
    $totp->setLabel($label);
    $totp->setIssuer('E-Learn');

    $otpauthUri = $totp->getProvisioningUri();

    // Jednoduchý QR kód cez externú službu
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($otpauthUri);
}

// Pomocná funkcia na escapovanie HTML
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <title>Nastavenie 2FA – E-Learn</title>
    <link rel="stylesheet" href="styles.css">
    <script src="theme.js" defer></script>
</head>
<body>
<header class="page-header">
    <div class="logo">E-<span>Learn</span></div>
    <div class="user-info">
        Prihlásený používateľ: <strong><?= h($user['name'] ?? 'Používateľ') ?></strong>
        · <a href="index.php">Kurzy</a>
        <a href="dashboard.php" class="ml-12">Moje výsledky</a>
        <?php if (is_admin() || is_teacher()): ?>
            · <a href="admin/index.php">Administrácia</a>
        <?php endif; ?>
        · <a href="logout.php">Odhlásiť sa</a>
    </div>
</header>

<main class="container">
    <h1>Dvojfaktorová autentifikácia (2FA)</h1>
    <p class="muted">
        2FA pridáva druhý bezpečnostný krok pri prihlásení. Po zadaní hesla musíš ešte zadať 6-miestny kód z aplikácie
        (napr. Google Authenticator, Authy, Microsoft Authenticator).
    </p>

    <?php if ($success): ?>
        <div class="success mt-12"><?= h($success) ?></div>
    <?php endif; ?>

    <?php foreach ($errors as $e): ?>
        <div class="error mt-8"><?= h($e) ?></div>
    <?php endforeach; ?>

    <?php if ((int)$user['totp_enabled'] === 1): ?>
        <section class="admin-card mt-16">
            <h2>2FA je zapnutá</h2>
            <p class="muted">
                Pri každom prihlásení budeš po zadaní hesla vyzvaný na zadanie 6-miestneho kódu z tvojej
                autentifikačnej aplikácie.
            </p>

            <form method="post" class="mt-12">
                <input type="hidden" name="action" value="disable">
                <button type="submit" class="btn btn-secondary">
                    Vypnúť 2FA (neodporúča sa)
                </button>
            </form>
        </section>
    <?php else: ?>
        <section class="admin-card mt-16">
            <h2>Zapnutie 2FA</h2>
            <ol class="muted" style="margin-left:20px;">
                <li>Otvori si aplikáciu Authenticator v mobile.</li>
                <li>Pridaj nový účet pomocou skenovania QR kódu alebo zadaním tajného kľúča.</li>
                <li>Potom zadaj aktuálny 6-miestny kód nižšie a potvrď.</li>
            </ol>

            <div class="mt-16" style="display:flex; flex-wrap:wrap; gap:20px; align-items:flex-start;">
                <?php if ($qrUrl): ?>
                    <div>
                        <div class="muted inline-text-sm mb-4">QR kód pre aplikáciu:</div>
                        <img src="<?= h($qrUrl) ?>" alt="QR kód pre 2FA">
                    </div>
                <?php endif; ?>

                <div style="flex:1; min-width:220px;">
                    <label class="mt-8">
                        Tajný kľúč (ak nemôžeš načítať QR kód):
                    </label>
                    <div class="code-task-output" style="margin-top:4px;">
                        <?= h($plainSecret ?? '') ?>
                    </div>

                    <form method="post" class="mt-12">
                        <input type="hidden" name="action" value="verify">
                        <label for="code">Kód z aplikácie (6 číslic)</label>
                        <input type="text" id="code" name="code" maxlength="6" pattern="\d{6}" required
                               placeholder="123456" style="max-width:160px;">

                        <div class="form-actions mt-12">
                            <button type="submit" class="btn">Zapnúť 2FA</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>