<?php
/**
 * Diagnóstico do salvamento de imagens (só para administradores).
 * Abra http://localhost/octoart/diagnostico_imagens.php logado como administrador
 * e copie o resultado se algo estiver marcado com [ERRO].
 */
if (PHP_SAPI === 'cli') {
    require_once __DIR__ . '/config/db.php';
} else {
    require_once __DIR__ . '/includes/auth.php';
    $u = current_user();
    if (!$u || !is_admin($u)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Acesso restrito ao administrador.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

function linha(string $estado, string $texto): void { echo str_pad('[' . $estado . ']', 8) . $texto . "\n"; }
$erros = 0;
function ok(string $t): void { linha('OK', $t); }
function aviso(string $t): void { linha('AVISO', $t); }
function erro(string $t): void { global $erros; $erros++; linha('ERRO', $t); }

echo "=== Diagnóstico de imagens do OctoArt ===\n\n";

// PHP e extensões
version_compare(PHP_VERSION, '7.4.0', '>=') ? ok('PHP ' . PHP_VERSION) : erro('PHP ' . PHP_VERSION . ' é antigo: o projeto precisa do PHP 7.4 ou mais novo.');
foreach (['pdo_mysql' => 'banco de dados', 'fileinfo' => 'detectar o tipo do arquivo', 'gd' => 'reduzir/otimizar imagens'] as $ext => $para) {
    extension_loaded($ext) ? ok("extensão $ext ($para)") : erro("extensão $ext desativada ($para). No XAMPP: php.ini → remova o ';' de extension=$ext e reinicie o Apache.");
}
function_exists('imagewebp') ? ok('GD com suporte a WebP') : aviso('GD sem WebP: as imagens serão guardadas em JPEG/PNG (funciona, só fica um pouco maior).');
function_exists('exif_read_data') ? ok('exif (corrige a rotação de fotos de celular)') : aviso('extensão exif desativada: fotos de celular podem aparecer giradas (não impede de salvar).');

// Limites do PHP
echo "\n";
foreach (['upload_max_filesize', 'post_max_size', 'memory_limit', 'file_uploads'] as $k) echo "  $k = " . var_export(ini_get($k), true) . "\n";
if (ini_to_bytes((string)ini_get('upload_max_filesize')) < 8 * 1024 * 1024) aviso('upload_max_filesize menor que 8M: fotos grandes serão recusadas pelo PHP. Aumente no php.ini (ex.: 20M).');
if (ini_to_bytes((string)ini_get('post_max_size')) < 8 * 1024 * 1024) aviso('post_max_size menor que 8M: aumente no php.ini (ex.: 25M).');
echo "\n";

// Banco
try {
    $pdo = db();
    ok('conexão com o banco');
} catch (Throwable $e) {
    erro('não conectou ao banco: ' . $e->getMessage());
    exit("\nCorrija a conexão primeiro.\n");
}
try {
    $pacote = (int)$pdo->query('SELECT @@max_allowed_packet')->fetchColumn();
    echo '  max_allowed_packet = ' . round($pacote / 1024) . " KB\n";
    echo '  limite de cada imagem no banco = ' . round(image_byte_limit() / 1024) . " KB (ajusta sozinho)\n";
    if ($pacote > 0 && $pacote < 400 * 1024) aviso('max_allowed_packet muito baixo; imagens maiores serão reduzidas ao máximo.');
} catch (Throwable $e) { aviso('não consegui ler max_allowed_packet: ' . $e->getMessage()); }

// Tabela
ensure_image_table();
try {
    $n = (int)$pdo->query('SELECT COUNT(*) FROM imagens_arquivos')->fetchColumn();
    ok("tabela imagens_arquivos existe ($n imagem(ns) guardada(s))");
} catch (Throwable $e) {
    erro('tabela imagens_arquivos não existe e não consegui criar: ' . $e->getMessage() . '. Importe sql/update_v4_imagens_no_banco.sql no phpMyAdmin.');
}

// Teste real: grava, lê e apaga uma imagem de teste
if (extension_loaded('gd')) {
    $im = imagecreatetruecolor(300, 200);
    imagefill($im, 0, 0, imagecolorallocate($im, 90, 60, 200));
    ob_start(); imagepng($im); $png = (string)ob_get_clean(); imagedestroy($im);
    $ref = store_image_binary($png, 'image/png', null);
    if (!$ref) {
        erro('teste de gravação falhou: ' . (image_error() ?: 'motivo desconhecido'));
    } else {
        $id = substr($ref, strrpos($ref, '=') + 1);
        try {
            $r = $pdo->prepare('SELECT LENGTH(dados) AS n, mime FROM imagens_arquivos WHERE id = ?');
            $r->execute([$id]);
            $row = $r->fetch();
            $row && (int)$row['n'] > 0 ? ok('teste de gravação e leitura no banco (' . $row['mime'] . ', ' . $row['n'] . ' bytes)') : erro('a imagem de teste foi gravada mas não foi encontrada ao ler.');
            $pdo->prepare('DELETE FROM imagens_arquivos WHERE id = ?')->execute([$id]);
        } catch (Throwable $e) { erro('teste de leitura falhou: ' . $e->getMessage()); }
    }
}

// Pasta storage (usada pelo log e pela migração)
$st = __DIR__ . '/storage';
(is_dir($st) || @mkdir($st, 0775, true)) && is_writable($st) ? ok('pasta storage/ com permissão de escrita') : aviso('pasta storage/ sem permissão de escrita (o log de erros e a migração automática precisam dela).');

// Log
$log = $st . '/erros_imagem.log';
if (is_file($log) && filesize($log) > 0) {
    echo "\nÚltimos erros registrados ao salvar imagens (storage/erros_imagem.log):\n";
    foreach (array_slice(file($log, FILE_IGNORE_NEW_LINES), -8) as $l) echo '  ' . $l . "\n";
}

echo "\n" . ($erros ? "Resultado: $erros problema(s) marcado(s) com [ERRO]. Me mande este texto.\n" : "Resultado: tudo certo para salvar imagens.\n");
