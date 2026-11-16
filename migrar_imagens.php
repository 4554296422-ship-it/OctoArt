<?php
/**
 * Migração manual das imagens de uploads/ para o banco (opcional).
 *
 * Normalmente você NÃO precisa abrir isto: a migração roda sozinha, em segundo plano,
 * nas primeiras visitas ao site (includes/migrar_uploads.php). Use este arquivo só se
 * quiser forçar tudo de uma vez e ver o resumo:
 *   - pelo navegador, logado como administrador: http://localhost/octoart/migrar_imagens.php
 *   - ou pelo terminal:                          php migrar_imagens.php
 *
 * Não apaga nada e pode rodar mais de uma vez.
 */
if (PHP_SAPI === 'cli') {
    require_once __DIR__ . '/includes/migrar_uploads.php';
} else {
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/migrar_uploads.php';
    $u = current_user();
    if (!$u || !is_admin($u)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Acesso restrito ao administrador.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}
@set_time_limit(0);
@ini_set('memory_limit', '512M');

$r = octoart_migrar_uploads(0);
if (!$r['erro_tabela'] && !$r['falhas']) octoart_migracao_concluir();

echo "==== Resumo ====\n";
echo "Imagens enviadas para o banco: {$r['migradas']}\n";
echo "Registros atualizados:         {$r['linhas']}\n";
foreach ($r['avisos'] as $a) echo "(aviso: $a)\n";
if ($r['faltando']) {
    echo "\nArquivos citados no banco mas que não existem em uploads/ (" . count($r['faltando']) . "):\n";
    foreach (array_keys($r['faltando']) as $f) echo "  - $f\n";
}
if ($r['falhas']) {
    echo "\nArquivos que não puderam ser convertidos (" . count($r['falhas']) . "):\n";
    foreach (array_keys($r['falhas']) as $f) echo "  - $f\n";
}
echo "\nConfira o site. Estando tudo certo, a pasta uploads/ já pode ser apagada.\n";
