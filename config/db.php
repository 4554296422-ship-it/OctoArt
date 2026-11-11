<?php
/**
 * OctoArt — conexão MySQL (XAMPP)
 * Ajuste usuário/senha se necessário.
 */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'octoart_bd');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP padrão: senha vazia
define('DB_CHARSET', 'utf8mb4');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function now_iso(): string {
    return date('Y-m-d H:i:s');
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Normaliza URL de mídia (uploads locais ou URLs absolutas, ex.: foto do Google).
 * Evita imagens quebradas quando o site está em subpasta (ex.: /octoart_php/).
 */
function media_url(?string $path): string {
    if ($path === null || $path === '') return '';
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') return '';
    // URL absoluta ou protocol-relative
    if (preg_match('~^(https?:)?//~i', $path)) return $path;
    // data URI
    if (str_starts_with($path, 'data:')) return $path;
    $path = ltrim($path, '/');
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($base === '/' || $base === '\\' || $base === '.') $base = '';
    return $base . '/' . $path;
}

/**
 * Baixa uma imagem remota (ex.: foto do Google) e salva em uploads/.
 * Retorna o caminho local (uploads/...) ou null em caso de falha.
 */
function download_remote_image(string $url, string $subdir = ''): ?string {
    $url = trim($url);
    if ($url === '' || !preg_match('~^https?://~i', $url)) return null;

    // Google: pedir tamanho estável e maior
    if (stripos($url, 'googleusercontent.com') !== false) {
        $url = preg_replace('/=s\d+(-c)?$/i', '', $url);
        $url .= '=s256-c';
    }

    $bin = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'OctoArt/1.0',
            CURLOPT_HTTPHEADER => ['Accept: image/*'],
        ]);
        $bin = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($bin === false || $code >= 400 || strlen($bin) < 100) return null;
    } else {
        $ctx = stream_context_create([
            'http' => ['timeout' => 15, 'header' => "Accept: image/*\r\nUser-Agent: OctoArt/1.0\r\n"],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $bin = @file_get_contents($url, false, $ctx);
        if ($bin === false || strlen($bin) < 100) return null;
        $ctype = '';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($bin) ?: ($ctype ?? '');
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    // content-type pode vir com charset
    $mime = strtolower(trim(explode(';', $mime)[0]));
    if (!isset($map[$mime])) {
        // tenta detectar por magic bytes
        if (strncmp($bin, "\xFF\xD8\xFF", 3) === 0) $mime = 'image/jpeg';
        elseif (strncmp($bin, "\x89PNG", 4) === 0) $mime = 'image/png';
        elseif (strncmp($bin, 'GIF8', 4) === 0) $mime = 'image/gif';
        elseif (strncmp($bin, 'RIFF', 4) === 0 && substr($bin, 8, 4) === 'WEBP') $mime = 'image/webp';
        else return null;
    }
    if (!isset($map[$mime])) return null;

    $name = 'img_' . bin2hex(random_bytes(8)) . '.' . $map[$mime];
    $dir = __DIR__ . '/../uploads' . ($subdir ? '/' . $subdir : '');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $dest = $dir . '/' . $name;
    if (@file_put_contents($dest, $bin) === false) return null;
    return 'uploads/' . ($subdir ? $subdir . '/' : '') . $name;
}

function money($v): string {
    if ($v === null || $v === '') return '—';
    return 'R$ ' . number_format((float)$v, 0, ',', '.');
}

function fmt_date(?string $iso): string {
    if (!$iso) return '—';
    $t = strtotime($iso);
    return $t ? date('d/m/Y', $t) : '—';
}

function initials(string $nome): string {
    $parts = preg_split('/\s+/', trim($nome));
    $i = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $i .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $i ?: '?';
}

function upload_image(array $file, string $subdir = ''): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) > 8 * 1024 * 1024) return null;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($map[$mime])) return null;
    $name = 'img_' . bin2hex(random_bytes(8)) . '.' . $map[$mime];
    $dir = __DIR__ . '/../uploads' . ($subdir ? '/' . $subdir : '');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    return 'uploads/' . ($subdir ? $subdir . '/' : '') . $name;
}


/** Motivos disponíveis para denunciar uma publicação. */
function motivos_denuncia(): array {
    return [
        'ofensivo'  => 'Conteúdo ofensivo ou discriminatório',
        'improprio' => 'Conteúdo sexual ou impróprio',
        'violento'  => 'Violência ou conteúdo perturbador',
        'plagio'    => 'Plágio / obra copiada de outra pessoa',
        'spam'      => 'Spam ou golpe',
        'outro'     => 'Outro motivo',
    ];
}

/** Cria (se ainda não existirem) as tabelas de curtidas, comentários e denúncias. */
function ensure_tables(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo = db();
    $defs = [
        "CREATE TABLE IF NOT EXISTS curtidas_projeto (
            id_curtida INT AUTO_INCREMENT PRIMARY KEY,
            id_projeto INT NOT NULL,
            id_usuario INT NOT NULL,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_curtida (id_projeto, id_usuario),
            FOREIGN KEY (id_projeto) REFERENCES projetos(id_projeto) ON DELETE CASCADE,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS comentarios_projeto (
            id_comentario INT AUTO_INCREMENT PRIMARY KEY,
            id_projeto INT NOT NULL,
            id_usuario INT NOT NULL,
            comentario TEXT NOT NULL,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_projeto) REFERENCES projetos(id_projeto) ON DELETE CASCADE,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS denuncias (
            id_denuncia INT AUTO_INCREMENT PRIMARY KEY,
            id_projeto INT NOT NULL,
            id_denunciante INT NOT NULL,
            motivo VARCHAR(40) NOT NULL,
            detalhes TEXT NULL,
            status ENUM('pendente','procedente','improcedente') NOT NULL DEFAULT 'pendente',
            id_admin INT NULL,
            resposta_admin TEXT NULL,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            data_resolucao DATETIME NULL,
            UNIQUE KEY uk_denuncia (id_projeto, id_denunciante),
            FOREIGN KEY (id_projeto) REFERENCES projetos(id_projeto) ON DELETE CASCADE,
            FOREIGN KEY (id_denunciante) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS notificacoes (
            id_notificacao INT AUTO_INCREMENT PRIMARY KEY,
            id_destinatario INT NOT NULL,
            id_remetente INT NULL,
            tipo VARCHAR(30) NOT NULL,
            mensagem VARCHAR(255) NOT NULL,
            link_destino VARCHAR(255) DEFAULT NULL,
            lida TINYINT(1) NOT NULL DEFAULT 0,
            data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_notificacoes_destinatario_lida (id_destinatario, lida),
            KEY idx_notificacoes_data (data_criacao),
            FOREIGN KEY (id_destinatario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
            FOREIGN KEY (id_remetente) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($defs as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // Se a chave estrangeira não for aceita, cria a tabela sem ela.
            try { $pdo->exec(preg_replace('/,\s*FOREIGN KEY[^,\)]*\([^)]*\)\s*REFERENCES[^,\)]*\([^)]*\)(\s*ON DELETE CASCADE)?/i', '', $sql)); } catch (Throwable $e2) {}
        }
    }
    // Banner de perfil (estilo capa YouTube)
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM perfis_artistas LIKE 'banner'")->fetchAll();
        if (!$cols) {
            $pdo->exec("ALTER TABLE perfis_artistas ADD COLUMN banner VARCHAR(255) DEFAULT NULL AFTER instagram");
        }
    } catch (Throwable $e) {}
    // foto_perfil precisa caber URLs longas do Google (fallback) e caminhos locais
    try {
        $col = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'foto_perfil'")->fetch(PDO::FETCH_ASSOC);
        if ($col && stripos((string)($col['Type'] ?? ''), 'varchar(255)') !== false) {
            $pdo->exec("ALTER TABLE usuarios MODIFY foto_perfil VARCHAR(512) DEFAULT NULL");
        }
    } catch (Throwable $e) {}
}


/** Registra uma notificação interna sem interromper a ação principal caso falhe. */
function criar_notificacao(int $destinatario, ?int $remetente, string $tipo, string $mensagem, ?string $link = null): void {
    if ($destinatario <= 0 || ($remetente !== null && $destinatario === $remetente)) return;
    try {
        ensure_tables();
        $mensagem = mb_substr(trim($mensagem), 0, 255);
        if ($mensagem === '') return;
        db()->prepare('INSERT INTO notificacoes (id_destinatario, id_remetente, tipo, mensagem, link_destino) VALUES (?,?,?,?,?)')
            ->execute([$destinatario, $remetente ?: null, mb_substr($tipo, 0, 30), $mensagem, $link ? mb_substr($link, 0, 255) : null]);
    } catch (Throwable $e) {
        // Notificações são auxiliares e não devem impedir curtidas, comentários ou mensagens.
    }
}
