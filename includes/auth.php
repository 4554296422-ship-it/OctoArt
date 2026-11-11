<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/mailer.php';
ensure_security_columns();
ensure_tables();

function current_user(): ?array {
    if (empty($_SESSION['id_usuario'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    $st = db()->prepare('SELECT * FROM usuarios WHERE id_usuario = ? AND status = "ativo"');
    $st->execute([$_SESSION['id_usuario']]);
    $cache = $st->fetch() ?: null;
    if (!$cache) unset($_SESSION['id_usuario']);
    return $cache;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

function perfil_artista(?int $id_usuario = null): ?array {
    $id = $id_usuario ?? ($_SESSION['id_usuario'] ?? null);
    if (!$id) return null;
    $st = db()->prepare('SELECT * FROM perfis_artistas WHERE id_usuario = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function is_member(?array $u = null): bool {
    $u = $u ?? current_user();
    return $u && $u['tipo'] !== 'admin';
}
function is_artist(?array $u = null): bool { return is_member($u); }
function is_client(?array $u = null): bool { return is_member($u); }

function ensure_perfil(int $id_usuario): array {
    $p = perfil_artista($id_usuario);
    if ($p) return $p;
    db()->prepare('INSERT INTO perfis_artistas (id_usuario, bio, localizacao, experiencia_anos) VALUES (?,?,?,0)')
        ->execute([$id_usuario, '', '']);
    return perfil_artista($id_usuario) ?: [];
}

function is_admin(?array $u = null): bool {
    $u = $u ?? current_user();
    return $u && ($u['tipo'] === 'admin');
}

function require_admin(): array {
    $u = require_login();
    if (!is_admin($u)) {
        header('Location: dashboard.php');
        exit;
    }
    return $u;
}

function sync_perfis(): void {
    try {
        db()->exec("INSERT INTO perfis_artistas (id_usuario, bio, localizacao, experiencia_anos)
            SELECT u.id_usuario, '', '', 0 FROM usuarios u
            WHERE u.tipo <> 'admin' AND NOT EXISTS (SELECT 1 FROM perfis_artistas p WHERE p.id_usuario = u.id_usuario)");
    } catch (Throwable $e) {}
}

function finish_login(array $u, bool $skip2fa = false): array {
    // 2FA
    if (!$skip2fa && !empty($u['dois_fatores']) && (int)$u['dois_fatores'] === 1 && $u['tipo'] !== 'admin') {
        $code = (string)random_int(100000, 999999);
        db()->prepare('UPDATE usuarios SET codigo_2fa=?, codigo_2fa_exp=? WHERE id_usuario=?')
            ->execute([$code, date('Y-m-d H:i:s', time() + 600), $u['id_usuario']]);
        send_app_mail(
            $u['email'],
            'Código de verificação — OctoArt',
            '<p>Seu código de acesso é:</p><h2 style="letter-spacing:4px">' . h($code) . '</h2><p>Válido por 10 minutos.</p>',
            "Seu código OctoArt: $code (válido 10 min)"
        );
        $_SESSION['pending_2fa'] = (int)$u['id_usuario'];
        return ['ok' => true, 'need_2fa' => true];
    }
    $_SESSION['id_usuario'] = (int)$u['id_usuario'];
    unset($_SESSION['pending_2fa']);
    if ($u['tipo'] !== 'admin') ensure_perfil((int)$u['id_usuario']);
    db()->prepare('UPDATE usuarios SET ultimo_acesso = ? WHERE id_usuario = ?')
        ->execute([now_iso(), $u['id_usuario']]);
    return ['ok' => true, 'user' => $u, 'need_2fa' => false];
}

function login_user(string $email, string $senha): array {
    $st = db()->prepare('SELECT * FROM usuarios WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $st->execute([trim($email)]);
    $u = $st->fetch();
    if (!$u || !verify_password($senha, (string)$u['senha'])) {
        return ['ok' => false, 'msg' => 'E-mail ou senha incorretos.'];
    }
    if ($u['status'] !== 'ativo') {
        return ['ok' => false, 'msg' => 'Conta inativa.'];
    }
    // Rehash se senha antiga em texto puro
    if (password_needs_rehash_custom((string)$u['senha'])) {
        db()->prepare('UPDATE usuarios SET senha=? WHERE id_usuario=?')
            ->execute([hash_password($senha), $u['id_usuario']]);
        $u['senha'] = hash_password($senha);
    }
    // Verificação de e-mail só bloqueia se REQUIRE_EMAIL_VERIFY estiver ativo
    if (defined('REQUIRE_EMAIL_VERIFY') && REQUIRE_EMAIL_VERIFY
        && $u['tipo'] !== 'admin' && empty($u['google_id'])
        && isset($u['email_verificado']) && (int)$u['email_verificado'] === 0) {
        return ['ok' => false, 'msg' => 'Confirme seu e-mail antes de entrar. Verifique a caixa de entrada ou reenvie o link.', 'need_verify' => true, 'email' => $u['email']];
    }
    return finish_login($u);
}

function register_user(array $d): array {
    ensure_security_columns();
    $nome = trim($d['nome'] ?? '');
    $email = trim($d['email'] ?? '');
    $senha = $d['senha'] ?? '';
    $tipo = 'ambos';
    $tel = trim($d['telefone'] ?? '');
    $cidade = trim($d['cidade'] ?? '');
    if ($nome === '' || $email === '' || strlen($senha) < 6) {
        return ['ok' => false, 'msg' => 'Preencha nome, e-mail e senha (mín. 6).'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'msg' => 'E-mail inválido.'];
    }
    try {
        $chk = db()->prepare('SELECT 1 FROM usuarios WHERE LOWER(email) = LOWER(?)');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            return ['ok' => false, 'msg' => 'E-mail já cadastrado.'];
        }
        $token = random_token(24);
        $hash = hash_password($senha);

        // Tenta com colunas de verificação; se o banco antigo não tiver, usa INSERT simples
        try {
            $st = db()->prepare('INSERT INTO usuarios (nome, email, senha, tipo, telefone, data_cadastro, status, email_verificado, token_verificacao) VALUES (?,?,?,?,?,?,?,?,?)');
            // email_verificado=1 no XAMPP para não travar o TCC (e-mail real muitas vezes não chega)
            $st->execute([$nome, $email, $hash, $tipo, $tel !== '' ? $tel : null, now_iso(), 'ativo', 1, $token]);
        } catch (Throwable $e1) {
            $st = db()->prepare('INSERT INTO usuarios (nome, email, senha, tipo, telefone, data_cadastro, status) VALUES (?,?,?,?,?,?,?)');
            $st->execute([$nome, $email, $hash, $tipo, $tel !== '' ? $tel : null, now_iso(), 'ativo']);
        }

        $id = (int)db()->lastInsertId();
        if ($id <= 0) {
            return ['ok' => false, 'msg' => 'Não foi possível criar a conta (ID inválido).'];
        }

        try {
            db()->prepare('INSERT INTO perfis_artistas (id_usuario, bio, localizacao, experiencia_anos) VALUES (?,?,?,0)')
                ->execute([$id, '', $cidade]);
        } catch (Throwable $e2) {
            // perfil pode já existir via trigger/sync — não bloqueia cadastro
            try { ensure_perfil($id); } catch (Throwable $e3) {}
            if ($cidade !== '') {
                try {
                    db()->prepare('UPDATE perfis_artistas SET localizacao=? WHERE id_usuario=?')->execute([$cidade, $id]);
                } catch (Throwable $e4) {}
            }
        }

        // e-mail de boas-vindas (também grava em storage/mail_log/)
        $link = app_base_url() . '/verificar_email.php?token=' . urlencode($token);
        try {
            send_app_mail(
                $email,
                'Bem-vindo(a) ao OctoArt!',
                '<p>Olá, ' . h($nome) . '!</p><p>Sua conta foi criada com sucesso.</p><p>Acesse: <a href="' . h(app_base_url() . '/login.php') . '">' . h(app_base_url()) . '</a></p>',
                "Conta criada no OctoArt. Acesse: " . app_base_url() . '/login.php'
            );
        } catch (Throwable $eMail) {}

        // Auto-login após cadastro
        $_SESSION['id_usuario'] = $id;
        unset($_SESSION['pending_2fa']);
        $u = db()->prepare('SELECT * FROM usuarios WHERE id_usuario=?');
        $u->execute([$id]);
        $user = $u->fetch() ?: ['id_usuario' => $id, 'nome' => $nome, 'email' => $email, 'tipo' => $tipo];

        return ['ok' => true, 'user' => $user, 'msg' => 'Conta criada com sucesso!', 'auto_login' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'msg' => 'Erro ao cadastrar: ' . $e->getMessage()];
    }
}

function request_password_reset(string $email): array {
    $st = db()->prepare('SELECT * FROM usuarios WHERE LOWER(email)=LOWER(?) LIMIT 1');
    $st->execute([trim($email)]);
    $u = $st->fetch();
    // Sempre mensagem genérica (não revela se existe)
    $msg = 'Se o e-mail existir, enviamos um link de recuperação.';
    if (!$u) return ['ok' => true, 'msg' => $msg];
    $token = random_token(24);
    db()->prepare('UPDATE usuarios SET token_reset=?, token_reset_exp=? WHERE id_usuario=?')
        ->execute([$token, date('Y-m-d H:i:s', time() + 3600), $u['id_usuario']]);
    $link = app_base_url() . '/redefinir_senha.php?token=' . urlencode($token);
    send_app_mail(
        $u['email'],
        'Redefinir senha — OctoArt',
        '<p>Para redefinir sua senha, abra:</p><p><a href="' . h($link) . '">' . h($link) . '</a></p><p>Link válido por 1 hora.</p>',
        "Redefinir senha: $link"
    );
    return ['ok' => true, 'msg' => $msg];
}

function reset_password_with_token(string $token, string $nova): array {
    if (strlen($nova) < 6) return ['ok' => false, 'msg' => 'Senha mínima de 6 caracteres.'];
    $st = db()->prepare('SELECT * FROM usuarios WHERE token_reset=? AND token_reset_exp >= ? LIMIT 1');
    $st->execute([$token, now_iso()]);
    $u = $st->fetch();
    if (!$u) return ['ok' => false, 'msg' => 'Link inválido ou expirado.'];
    db()->prepare('UPDATE usuarios SET senha=?, token_reset=NULL, token_reset_exp=NULL WHERE id_usuario=?')
        ->execute([hash_password($nova), $u['id_usuario']]);
    return ['ok' => true, 'msg' => 'Senha atualizada! Faça login.'];
}

function verify_email_token(string $token): array {
    $st = db()->prepare('SELECT * FROM usuarios WHERE token_verificacao=? LIMIT 1');
    $st->execute([$token]);
    $u = $st->fetch();
    if (!$u) return ['ok' => false, 'msg' => 'Token inválido.'];
    db()->prepare('UPDATE usuarios SET email_verificado=1, token_verificacao=NULL WHERE id_usuario=?')
        ->execute([$u['id_usuario']]);
    return ['ok' => true, 'msg' => 'E-mail confirmado! Agora você pode entrar.'];
}

function resend_verification(string $email): array {
    $st = db()->prepare('SELECT * FROM usuarios WHERE LOWER(email)=LOWER(?) LIMIT 1');
    $st->execute([trim($email)]);
    $u = $st->fetch();
    if (!$u) return ['ok' => false, 'msg' => 'E-mail não encontrado.'];
    if ((int)($u['email_verificado'] ?? 0) === 1) return ['ok' => true, 'msg' => 'E-mail já verificado.'];
    $token = random_token(24);
    db()->prepare('UPDATE usuarios SET token_verificacao=? WHERE id_usuario=?')->execute([$token, $u['id_usuario']]);
    $link = app_base_url() . '/verificar_email.php?token=' . urlencode($token);
    send_app_mail($u['email'], 'Confirme seu e-mail — OctoArt', '<p><a href="' . h($link) . '">Confirmar e-mail</a></p>', $link);
    return ['ok' => true, 'msg' => 'Link reenviado. Confira storage/mail_log/ no XAMPP.'];
}

function verify_2fa_code(string $code): array {
    $uid = (int)($_SESSION['pending_2fa'] ?? 0);
    if (!$uid) return ['ok' => false, 'msg' => 'Sessão de 2FA expirada. Faça login de novo.'];
    $st = db()->prepare('SELECT * FROM usuarios WHERE id_usuario=? LIMIT 1');
    $st->execute([$uid]);
    $u = $st->fetch();
    if (!$u || (string)$u['codigo_2fa'] !== trim($code)) {
        return ['ok' => false, 'msg' => 'Código incorreto.'];
    }
    if ($u['codigo_2fa_exp'] && strtotime($u['codigo_2fa_exp']) < time()) {
        return ['ok' => false, 'msg' => 'Código expirado. Faça login de novo.'];
    }
    db()->prepare('UPDATE usuarios SET codigo_2fa=NULL, codigo_2fa_exp=NULL WHERE id_usuario=?')->execute([$uid]);
    return finish_login($u, true);
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
