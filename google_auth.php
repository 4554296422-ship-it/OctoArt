<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/app.php';

if (!GOOGLE_CLIENT_ID || !GOOGLE_CLIENT_SECRET) {
    $_SESSION['auth_flash'] = 'Login Google: configure GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET em config/app.php (Google Cloud Console).';
    header('Location: login.php');
    exit;
}

$redirect = google_redirect_uri();
$_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));
$_SESSION['google_redirect_uri'] = $redirect;

$logDir = __DIR__ . '/storage/mail_log';
if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
@file_put_contents($logDir . '/google_oauth_debug.log',
    '[' . date('Y-m-d H:i:s') . "] AUTH_START redirect_uri=$redirect\n",
    FILE_APPEND);

$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => $redirect,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'online',
    'include_granted_scopes' => 'true',
    'prompt' => 'select_account',
    'state' => $_SESSION['google_oauth_state'],
];
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;
