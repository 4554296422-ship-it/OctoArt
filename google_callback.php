<?php
/**
 * Callback OAuth Google — OctoArt
 * Debug: google_callback.php?debug=1  (ou define GOOGLE_DEBUG true em config/app.php)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/mailer.php';

$DEBUG = !empty($_GET['debug']) || (defined('GOOGLE_DEBUG') && GOOGLE_DEBUG);
$logFile = __DIR__ . '/storage/mail_log/google_oauth_debug.log';
if (!is_dir(dirname($logFile))) {
    @mkdir(dirname($logFile), 0775, true);
}

function gdebug_log(string $step, $data = null): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . "] $step";
    if ($data !== null) {
        $line .= ' | ' . (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    $line .= "\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
}

function google_debug_page(string $title, array $rows, bool $ok = false): void {
    global $logFile, $DEBUG;
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . ' — Debug Google</title>';
    echo '<style>
      body{font-family:system-ui,sans-serif;max-width:720px;margin:32px auto;padding:0 16px;line-height:1.45;background:#0c0a14;color:#eee}
      h1{font-size:1.25rem;color:#c4b5fd}
      .box{background:#161322;border:1px solid #2a2740;border-radius:12px;padding:14px 16px;margin:12px 0}
      .k{color:#a78bfa;font-size:.8rem;text-transform:uppercase;letter-spacing:.04em}
      .v{word-break:break-all;margin-top:4px}
      pre{background:#0a0812;padding:12px;border-radius:8px;overflow:auto;font-size:.82rem}
      .ok{color:#4ade80}.bad{color:#f87171}
      a{color:#a78bfa}
    </style></head><body>';
    echo '<h1 class="' . ($ok ? 'ok' : 'bad') . '">' . htmlspecialchars($title) . '</h1>';
    foreach ($rows as $k => $v) {
        echo '<div class="box"><div class="k">' . htmlspecialchars((string)$k) . '</div><div class="v">';
        if (is_array($v) || is_object($v)) {
            echo '<pre>' . htmlspecialchars(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
        } else {
            echo htmlspecialchars((string)$v);
        }
        echo '</div></div>';
    }
    echo '<div class="box"><div class="k">Log em disco</div><div class="v"><code>storage/mail_log/google_oauth_debug.log</code></div></div>';
    echo '<p><a href="login.php">← Login</a> · <a href="google_uri.php">Diagnóstico URI</a> · <a href="google_auth.php">Tentar Google de novo</a></p>';
    echo '</body></html>';
    exit;
}

function google_fail(string $msg, array $extra = []): void {
    global $DEBUG;
    gdebug_log('FAIL', $msg);
    if ($extra) gdebug_log('FAIL_EXTRA', $extra);
    if ($DEBUG) {
        google_debug_page('Falha no callback Google', array_merge(['Erro' => $msg], $extra), false);
    }
    $_SESSION['auth_flash'] = $msg;
    header('Location: login.php');
    exit;
}

gdebug_log('=== CALLBACK START ===');
gdebug_log('GET', $_GET);
gdebug_log('SESSION_keys', array_keys($_SESSION ?? []));

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$err = $_GET['error'] ?? '';
$errDesc = $_GET['error_description'] ?? '';

$redirect = $_SESSION['google_redirect_uri'] ?? google_redirect_uri();
$expectedState = $_SESSION['google_oauth_state'] ?? '';

gdebug_log('config', [
    'redirect_uri' => $redirect,
    'app_url' => app_base_url(),
    'has_client_id' => GOOGLE_CLIENT_ID !== '',
    'has_secret' => GOOGLE_CLIENT_SECRET !== '',
    'client_id_prefix' => GOOGLE_CLIENT_ID !== '' ? substr(GOOGLE_CLIENT_ID, 0, 20) . '…' : '(vazio)',
    'session_state' => $expectedState ? substr($expectedState, 0, 8) . '…' : '(vazio)',
    'got_state' => $state ? substr($state, 0, 8) . '…' : '(vazio)',
]);

if ($err) {
    google_fail('Google retornou erro: ' . $err . ($errDesc ? " — $errDesc" : ''), [
        'error' => $err,
        'error_description' => $errDesc,
        'redirect_uri_usada' => $redirect,
        'dica' => 'Se for redirect_uri_mismatch, cadastre exatamente esta URI no Google Cloud.',
    ]);
}

if ($code === '') {
    // Acesso direto sem code — mostra diagnóstico
    if ($DEBUG || empty($_GET)) {
        google_debug_page('Callback sem código OAuth', [
            'O que isso significa' => 'O Google ainda não redirecionou com ?code=... Você abriu o callback direto ou o login falhou antes.',
            'redirect_uri que o app usa' => $redirect,
            'Cadastre no Google Cloud' => $redirect,
            'Client ID configurado?' => GOOGLE_CLIENT_ID !== '' ? 'sim' : 'NÃO — preencha config/app.php',
            'Client Secret configurado?' => GOOGLE_CLIENT_SECRET !== '' ? 'sim' : 'NÃO',
            'Próximo passo' => '1) Cadastre a redirect_uri no Console  2) Abra google_auth.php  3) Se falhar, use google_callback.php?debug=1 na URL após o redirect',
            'GET recebido' => $_GET,
        ], false);
    }
    google_fail('Código Google ausente. Tente o login com Google de novo.');
}

if (!GOOGLE_CLIENT_ID || !GOOGLE_CLIENT_SECRET) {
    google_fail('Credenciais Google vazias em config/app.php (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET).');
}

if ($expectedState !== '' && !hash_equals($expectedState, (string)$state)) {
    google_fail('Estado OAuth inválido (possível sessão perdida ou cookie bloqueado). Tente de novo.', [
        'esperado' => $expectedState ? 'presente na sessão' : 'ausente',
        'recebido' => $state ? 'presente no GET' : 'ausente',
        'dica' => 'Use o mesmo host (localhost vs 127.0.0.1) e permita cookies.',
    ]);
}

// 1) Trocar code por access_token
$tokenBody = http_build_query([
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => $redirect,
    'grant_type' => 'authorization_code',
]);
gdebug_log('token_request', ['url' => 'https://oauth2.googleapis.com/token', 'redirect_uri' => $redirect]);

$tokenRes = http_request('https://oauth2.googleapis.com/token', [
    'method' => 'POST',
    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
    'body' => $tokenBody,
]);
$tokenData = json_decode($tokenRes['body'] ?: '{}', true);
gdebug_log('token_response', [
    'http_ok' => $tokenRes['ok'],
    'http_code' => $tokenRes['code'] ?? null,
    'error' => $tokenRes['error'] ?? null,
    'body' => $tokenData,
]);

$access = $tokenData['access_token'] ?? '';
if (!$access) {
    google_fail(
        'Falha ao obter token do Google: ' . ($tokenData['error_description'] ?? $tokenData['error'] ?? $tokenRes['error'] ?? 'sem access_token'),
        [
            'redirect_uri_enviada' => $redirect,
            'resposta_google' => $tokenData,
            'http' => $tokenRes,
            'dica' => 'Confira Client Secret e se a redirect_uri é IDÊNTICA à do Console.',
        ]
    );
}

// 2) Perfil do usuário
$infoRes = http_request('https://www.googleapis.com/oauth2/v2/userinfo', [
    'method' => 'GET',
    'headers' => ['Authorization: Bearer ' . $access],
]);
$info = json_decode($infoRes['body'] ?: '{}', true);
gdebug_log('userinfo', $info);

$email = trim($info['email'] ?? '');
$nome = trim($info['name'] ?? '') ?: 'Usuário Google';
$gid = (string)($info['id'] ?? '');
$fotoRemota = trim((string)($info['picture'] ?? ''));
if ($fotoRemota === '') $fotoRemota = null;

if ($email === '' || $gid === '') {
    google_fail('Não foi possível ler e-mail/ID da conta Google.', ['userinfo' => $info]);
}

// Baixa a foto do Google e guarda no banco (evita URL longa/intermitente que some)
$foto = null;
if ($fotoRemota) {
    $local = download_remote_image($fotoRemota);
    $foto = $local ?: $fotoRemota; // fallback: URL remota se o download falhar
    gdebug_log('foto_perfil', ['remota' => $fotoRemota, 'salva' => $foto, 'local' => (bool)$local]);
}

// 3) Criar / vincular usuário
try {
    $st = db()->prepare('SELECT * FROM usuarios WHERE google_id = ? OR LOWER(email) = LOWER(?) LIMIT 1');
    $st->execute([$gid, $email]);
    $u = $st->fetch();
    $isNew = false;

    if (!$u) {
        $isNew = true;
        $hash = hash_password(bin2hex(random_bytes(32)));
        db()->prepare('INSERT INTO usuarios (nome, email, senha, tipo, data_cadastro, status, email_verificado, google_id, foto_perfil) VALUES (?,?,?,?,?,?,1,?,?)')
            ->execute([$nome, $email, $hash, 'ambos', now_iso(), 'ativo', $gid, $foto]);
        $id = (int)db()->lastInsertId();
        try {
            db()->prepare('INSERT INTO perfis_artistas (id_usuario, bio, localizacao, experiencia_anos) VALUES (?,?,?,0)')
                ->execute([$id, '', '']);
        } catch (Throwable $e) {
            gdebug_log('perfil_insert_warn', $e->getMessage());
        }
        $st = db()->prepare('SELECT * FROM usuarios WHERE id_usuario=?');
        $st->execute([$id]);
        $u = $st->fetch();
        gdebug_log('user_created', ['id' => $id, 'email' => $email, 'foto' => $foto]);
    } else {
        $senha = (string)$u['senha'];
        if (!is_password_hashed($senha)) {
            $senha = hash_password($senha);
        }
        // Atualiza foto se: vazia, ainda é URL externa do Google, ou arquivo local sumiu
        $atual = trim((string)($u['foto_perfil'] ?? ''));
        $precisaFoto = ($atual === '');
        if (!$precisaFoto && $atual !== '') {
            if (preg_match('~^https?://~i', $atual) && stripos($atual, 'googleusercontent.com') !== false) {
                $precisaFoto = true; // trocar URL frágil por arquivo local
            } elseif (!preg_match('~^https?://~i', $atual)) {
                $path = __DIR__ . '/' . ltrim(str_replace('\\', '/', $atual), '/');
                if (!is_file($path)) $precisaFoto = true;
            }
        }
        $novaFoto = ($precisaFoto && $foto) ? $foto : ($atual !== '' ? $atual : $foto);

        db()->prepare('UPDATE usuarios SET google_id=?, email_verificado=1, senha=?, nome=COALESCE(NULLIF(nome,\'\'), ?), foto_perfil=? WHERE id_usuario=?')
            ->execute([$gid, $senha, $nome, $novaFoto, $u['id_usuario']]);
        $st = db()->prepare('SELECT * FROM usuarios WHERE id_usuario=?');
        $st->execute([$u['id_usuario']]);
        $u = $st->fetch();
        gdebug_log('user_linked', ['id' => $u['id_usuario'], 'email' => $email, 'foto' => $u['foto_perfil'] ?? null, 'atualizou_foto' => $precisaFoto]);
    }
} catch (Throwable $e) {
    google_fail('Erro no banco ao criar/vincular usuário: ' . $e->getMessage(), [
        'exception' => $e->getMessage(),
        'email' => $email,
    ]);
}

// 4) E-mail OctoArt → e-mail Google
$assunto = $isNew ? 'Bem-vindo(a) ao OctoArt!' : 'Novo acesso com Google — OctoArt';
$html = $isNew
    ? '<div style="font-family:sans-serif;max-width:520px"><h2 style="color:#1A0071">Olá, ' . h($nome) . '!</h2><p>Sua conta no <strong>OctoArt</strong> foi criada com o Google.</p><p>E-mail: <strong>' . h($email) . '</strong></p></div>'
    : '<div style="font-family:sans-serif;max-width:520px"><h2 style="color:#1A0071">Olá, ' . h($nome) . '!</h2><p>Login no OctoArt com Google.</p><p>E-mail: <strong>' . h($email) . '</strong></p></div>';
$mailOk = send_app_mail($email, $assunto, $html, strip_tags($html));
gdebug_log('mail', ['to' => $email, 'ok' => $mailOk, 'isNew' => $isNew]);

unset($_SESSION['google_oauth_state'], $_SESSION['google_redirect_uri']);

if ($DEBUG) {
    google_debug_page('Callback Google OK', [
        'status' => $isNew ? 'Conta criada' : 'Conta vinculada / login',
        'email' => $email,
        'nome' => $nome,
        'google_id' => $gid,
        'id_usuario' => $u['id_usuario'] ?? null,
        'email_enviado' => $mailOk ? 'sim (ou log em storage/mail_log/)' : 'falhou',
        'redirect_uri' => $redirect,
        'próximo' => 'Remova ?debug=1 da URL e faça login normal. Clique em Continuar para o painel.',
        'painel' => app_base_url() . '/dashboard.php',
    ], true);
}

$res = finish_login($u, true);
if (!empty($res['need_2fa'])) {
    header('Location: verificar_2fa.php');
    exit;
}
header('Location: ' . (is_admin($res['user'] ?? $u) ? 'admin.php' : 'index.php'));
exit;
