<?php
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Não autenticado']);
    exit;
}
if (empty($_FILES['file'])) {
    echo json_encode(['ok' => false, 'erro' => 'Arquivo ausente']);
    exit;
}
$path = upload_image($_FILES['file']);
if (!$path) {
    echo json_encode(['ok' => false, 'erro' => 'Falha no upload (use JPG/PNG/WebP até 8MB)']);
    exit;
}
echo json_encode(['ok' => true, 'url' => $path]);
