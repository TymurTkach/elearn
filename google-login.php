<?php
//Presmerovanie na Google OAuth
$pdo = require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$clientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$clientSecret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';
$redirectUri = defined('GOOGLE_REDIRECT_URI') && GOOGLE_REDIRECT_URI !== ''
    ? GOOGLE_REDIRECT_URI
    : (function () {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        return $protocol . '://' . $host . $path . '/google-callback.php';
    })();

if ($clientId === '' || $clientSecret === '') {
    header('Location: login.php?error=google_not_configured');
    exit;
}

require __DIR__ . '/vendor/autoload.php';

use League\OAuth2\Client\Provider\Google;

$provider = new Google([
    'clientId'     => $clientId,
    'clientSecret' => $clientSecret,
    'redirectUri'  => $redirectUri,
]);

$authUrl = $provider->getAuthorizationUrl([
    'scope' => ['email', 'profile'],
]);
$_SESSION['oauth2state'] = $provider->getState();

header('Location: ' . $authUrl);
exit;
