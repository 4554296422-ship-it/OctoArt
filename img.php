<?php
/**
 * Entrega uma imagem guardada no banco (tabela imagens_arquivos).
 * Uso: img.php?id=<32 caracteres hexadecimais>
 *
 * O conteúdo de um id nunca muda, então o navegador pode guardar em cache
 * por muito tempo e nem chega a consultar o banco nas visitas seguintes.
 */
require_once __DIR__ . '/config/db.php';

$id = $_GET['id'] ?? '';
if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) {
    http_response_code(404);
    exit;
}

$etag = '"' . $id . '"';
if (strpos($_SERVER['HTTP_IF_NONE_MATCH'] ?? '', $id) !== false) {
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=31536000, immutable');
    http_response_code(304);
    exit;
}

$row = null;
try {
    $st = db()->prepare('SELECT mime, dados FROM imagens_arquivos WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
} catch (Throwable $e) {
    $row = null;
}

$permitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!$row || !in_array($row['mime'], $permitidos, true)) {
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

$dados = (string)$row['dados'];
header('Content-Type: ' . $row['mime']);
header('Content-Length: ' . strlen($dados));
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
echo $dados;
