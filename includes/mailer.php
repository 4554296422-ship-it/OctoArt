<?php
require_once __DIR__ . '/../config/app.php';

/**
 * Envia e-mail (tenta mail() e sempre grava log local para testes no XAMPP).
 */
function send_app_mail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
    if ($textBody === '') {
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
    }
    $dir = MAIL_LOG_DIR;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $log = sprintf(
        "[%s]\nTo: %s\nSubject: %s\n\n%s\n\n---\n",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        $textBody
    );
    @file_put_contents($dir . '/mail_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.txt', $log);

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . APP_FROM_NAME . ' <' . APP_FROM_EMAIL . '>',
        'Reply-To: ' . APP_FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion(),
    ];
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $headers));
    return $ok || is_dir($dir); // log conta como "enviado" no ambiente local
}

function ensure_security_columns(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $cols = [
        'email_verificado' => "TINYINT NOT NULL DEFAULT 0",
        'token_verificacao' => "VARCHAR(64) NULL",
        'token_reset' => "VARCHAR(64) NULL",
        'token_reset_exp' => "DATETIME NULL",
        'dois_fatores' => "TINYINT NOT NULL DEFAULT 0",
        'codigo_2fa' => "VARCHAR(10) NULL",
        'codigo_2fa_exp' => "DATETIME NULL",
        'google_id' => "VARCHAR(80) NULL",
    ];
    foreach ($cols as $name => $def) {
        try {
            db()->exec("ALTER TABLE usuarios ADD COLUMN $name $def");
        } catch (Throwable $e) { /* já existe */ }
    }
    try {
        db()->exec("ALTER TABLE perfis_artistas ADD COLUMN banner_url VARCHAR(255) NULL");
    } catch (Throwable $e) {}
    // Contas já existentes não ficam bloqueadas
    try {
        db()->exec("UPDATE usuarios SET email_verificado=1 WHERE email_verificado=0 AND (token_verificacao IS NULL OR token_verificacao='') AND (google_id IS NULL OR google_id='')");
    } catch (Throwable $e) {}
    migrate_plain_passwords();
}

function random_token(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}

function hash_password(string $plain): string {
    return password_hash($plain, PASSWORD_DEFAULT);
}

function verify_password(string $plain, string $stored): bool {
    if ($stored === '') return false;
    // Migração: senhas antigas em texto puro
    if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon2')) {
        return password_verify($plain, $stored);
    }
    return hash_equals($stored, $plain);
}

function password_needs_rehash_custom(string $stored): bool {
    if (!(str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon2'))) {
        return true;
    }
    return password_needs_rehash($stored, PASSWORD_DEFAULT);
}

function is_password_hashed(string $stored): bool {
    return str_starts_with($stored, '$2y$')
        || str_starts_with($stored, '$2a$')
        || str_starts_with($stored, '$2b$')
        || str_starts_with($stored, '$argon2');
}

/** Converte senhas em texto puro para hash bcrypt (ilegível no phpMyAdmin). */
function migrate_plain_passwords(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $rows = db()->query('SELECT id_usuario, senha FROM usuarios')->fetchAll();
        $upd = db()->prepare('UPDATE usuarios SET senha=? WHERE id_usuario=?');
        foreach ($rows as $r) {
            $s = (string)($r['senha'] ?? '');
            if ($s === '' || is_password_hashed($s)) continue;
            // A senha ainda está em texto puro no banco → gera hash dela
            $upd->execute([password_hash($s, PASSWORD_DEFAULT), $r['id_usuario']]);
        }
    } catch (Throwable $e) {}
}
