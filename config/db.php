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
 * Baixa uma imagem remota (ex.: foto do Google) e guarda no banco de dados.
 * Retorna a referência da imagem (img.php?id=...) ou null em caso de falha.
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

    return store_image_binary($bin, $mime);
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

/* =========================================================
   Imagens guardadas no banco de dados (tabela imagens_arquivos)
   Nada é gravado na pasta do projeto: cada imagem é otimizada
   (redimensionada/comprimida) e servida por img.php.
   ========================================================= */

/** Maior lado (px) de uma imagem guardada; imagens maiores são reduzidas. */
const IMG_MAX_SIDE = 1600;
/** Tamanho máximo (bytes) de uma imagem depois de otimizada. */
const IMG_MAX_BYTES = 900 * 1024;
/** Limite de pixels aceito na entrada (protege a memória do servidor). */
const IMG_MAX_PIXELS = 50000000;

function ensure_image_table(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS imagens_arquivos (
            id CHAR(32) NOT NULL PRIMARY KEY,
            mime VARCHAR(40) NOT NULL,
            tamanho INT NOT NULL,
            dados MEDIUMBLOB NOT NULL,
            id_usuario INT NULL,
            data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_imagens_arquivos_usuario (id_usuario)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        // Tabela já existe ou o usuário do banco não pode criar: o INSERT mostra o erro real.
    }
}

/** Limite efetivo em bytes, respeitando o max_allowed_packet do MySQL (1 MB no XAMPP). */
function image_byte_limit(): int {
    static $limit = null;
    if ($limit !== null) return $limit;
    $limit = IMG_MAX_BYTES;
    try {
        $packet = (int)db()->query('SELECT @@max_allowed_packet')->fetchColumn();
        if ($packet > 0) $limit = max(100 * 1024, min($limit, $packet - 128 * 1024));
    } catch (Throwable $e) {}
    return $limit;
}

/** Converte valores como "128M" do php.ini em bytes. */
function ini_to_bytes(string $v): int {
    $v = trim($v);
    $n = (int)$v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024;
        case 'm': $n *= 1024;
        case 'k': $n *= 1024;
    }
    return $n;
}

/** Garante memória para processar uma imagem; tenta aumentar o limite do PHP se for preciso. */
function image_ensure_memory(int $needBytes): bool {
    $cur = (string)ini_get('memory_limit');
    if ($cur === '-1') return true;
    $total = $needBytes + memory_get_usage() + 16 * 1024 * 1024;
    if (ini_to_bytes($cur) >= $total) return true;
    if ($total > 1024 * 1024 * 1024) return false; // acima de 1 GB: recusa
    @ini_set('memory_limit', (string)((int)ceil($total / 1048576) + 16) . 'M');
    return ini_to_bytes((string)ini_get('memory_limit')) >= $total;
}

/**
 * Reduz e comprime a imagem até caber em $limit bytes.
 * Retorna [binário, mime] ou null se não for possível.
 */
function optimize_image(string $bin, string $mime, int $limit): ?array {
    $fits = strlen($bin) <= $limit;
    // GIF pequeno: guarda como está (preserva a animação).
    if ($mime === 'image/gif' && $fits) return [$bin, $mime];
    if (!function_exists('imagecreatefromstring')) return $fits ? [$bin, $mime] : null;

    $info = @getimagesizefromstring($bin);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > IMG_MAX_PIXELS) return null;

    // Fotos grandes (ex.: 24 MP do celular) precisam de bastante memória para serem decodificadas.
    if (!image_ensure_memory((int)($info[0] * $info[1] * 4.5))) return null;

    $src = @imagecreatefromstring($bin);
    if (!$src) return $fits ? [$bin, $mime] : null;

    // Corrige a rotação de fotos de celular (EXIF) antes de reduzir.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        try {
            $mem = fopen('php://memory', 'r+');
            fwrite($mem, $bin);
            rewind($mem);
            $exif = @exif_read_data($mem);
            fclose($mem);
            $rot = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
            if ($rot) {
                $r = imagerotate($src, $rot, 0);
                if ($r) $src = $r;
            }
        } catch (Throwable $e) {}
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1.0, IMG_MAX_SIDE / max($w, $h));
    $useWebp = function_exists('imagewebp');

    for ($round = 0; $round < 8; $round++) {
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        foreach ([85, 75, 65, 55] as $q) {
            ob_start();
            if ($useWebp) {
                imagewebp($dst, null, $q);
                $outMime = 'image/webp';
            } elseif ($mime === 'image/jpeg') {
                // JPEG não tem transparência: assenta sobre fundo branco.
                $flat = imagecreatetruecolor($nw, $nh);
                imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
                imagecopy($flat, $dst, 0, 0, 0, 0, $nw, $nh);
                imagejpeg($flat, null, $q);
                imagedestroy($flat);
                $outMime = 'image/jpeg';
            } else {
                imagepng($dst, null, 9);
                $outMime = 'image/png';
            }
            $out = (string)ob_get_clean();
            if ($out !== '' && strlen($out) <= $limit) {
                imagedestroy($dst);
                imagedestroy($src);
                return [$out, $outMime];
            }
            if (!$useWebp && $mime !== 'image/jpeg') break; // PNG não tem "qualidade": só reduz o tamanho
        }
        imagedestroy($dst);
        $scale *= 0.8;
    }
    imagedestroy($src);
    return null;
}

/**
 * Otimiza e grava a imagem no banco. Retorna a referência "img.php?id=..." ou null.
 */
function store_image_binary(string $bin, string $mime, ?int $userId = null): ?string {
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) return null;
    $opt = optimize_image($bin, $mime, image_byte_limit());
    if (!$opt) return null;
    [$data, $outMime] = $opt;
    try {
        ensure_image_table();
        $id = bin2hex(random_bytes(16));
        $uid = $userId ?? (isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null);
        $st = db()->prepare('INSERT INTO imagens_arquivos (id, mime, tamanho, dados, id_usuario) VALUES (?,?,?,?,?)');
        $st->bindValue(1, $id);
        $st->bindValue(2, $outMime);
        $st->bindValue(3, strlen($data), PDO::PARAM_INT);
        $st->bindValue(4, $data, PDO::PARAM_LOB);
        $st->bindValue(5, $uid ?: null, $uid ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $st->execute();
    } catch (Throwable $e) {
        return null;
    }
    return 'img.php?id=' . $id;
}

/** Recebe um arquivo de $_FILES, valida e guarda no banco. Retorna "img.php?id=..." ou null. */
function upload_image(array $file, string $subdir = ''): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) > 8 * 1024 * 1024) return null;
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $bin = @file_get_contents($file['tmp_name']);
    if ($bin === false || $bin === '') return null;
    return store_image_binary($bin, (string)$mime);
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
