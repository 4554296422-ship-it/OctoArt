<?php

define('APP_NAME', 'OctoArt');


define('APP_URL', 'http://localhost/octoart_php');


define('GOOGLE_REDIRECT_URI', 'http://localhost/octoart_php/google_callback.php'); 

// Remetente dos e-mails da plataforma (aparece como OctoArt)
define('APP_FROM_EMAIL', 'adrielicristine3@gmail.com');
define('APP_FROM_NAME', 'OctoArt');


define('GOOGLE_CLIENT_ID', '179150202497-r7cdrp678bqqbhdrq4ut83s737igs3rn.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');


define('GOOGLE_DEBUG', false);


define('REQUIRE_EMAIL_VERIFY', false);

define('MAIL_LOG_DIR', __DIR__ . '/../storage/mail_log');

/**
 * URL base do site (sem barra no final).
 */
function app_base_url(): string {
    if (defined('APP_URL') && APP_URL !== '') {
        return rtrim(APP_URL, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script = rtrim($script, '/');
    if ($script === '/' || $script === '\\') $script = '';
    return $scheme . '://' . $host . $script;
}

function google_redirect_uri(): string {
    if (defined('GOOGLE_REDIRECT_URI') && GOOGLE_REDIRECT_URI !== '') {
        return rtrim(GOOGLE_REDIRECT_URI, '/');
    }
    return app_base_url() . '/google_callback.php';
}

/** POST/GET HTTP simples (cURL ou file_get_contents). */
function http_request(string $url, array $opts = []): array {
    $method = strtoupper($opts['method'] ?? 'GET');
    $body = $opts['body'] ?? null;
    $headers = $opts['headers'] ?? [];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $h = [];
        foreach ($headers as $k => $v) {
            $h[] = is_int($k) ? $v : ($k . ': ' . $v);
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $h,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => $raw !== false, 'body' => $raw ?: '', 'code' => $code, 'error' => $err];
    }

    $hdr = '';
    foreach ($headers as $k => $v) {
        $hdr .= (is_int($k) ? $v : ($k . ': ' . $v)) . "\r\n";
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $hdr,
            'content' => $body ?? '',
            'timeout' => 20,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    return ['ok' => $raw !== false, 'body' => $raw ?: '', 'code' => 0, 'error' => $raw === false ? 'file_get_contents failed' : ''];
}
