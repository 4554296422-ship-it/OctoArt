<?php
/**
 * Migração automática das imagens da pasta uploads/ para o banco de dados.
 *
 * Roda sozinha, em segundo plano, nas primeiras visitas ao site depois da atualização:
 * a cada visita leva um lote pequeno de imagens (para não travar a página) e, quando
 * não sobra mais nada para migrar, grava storage/.uploads_migrados e nunca mais faz nada.
 *
 * Nada é apagado: os arquivos de uploads/ continuam lá até você decidir removê-los.
 */
require_once __DIR__ . '/../config/db.php';

const MIGRACAO_LOTE = 12;

function migracao_flag_path(): string { return __DIR__ . '/../storage/.uploads_migrados'; }
function migracao_mapa_path(): string { return __DIR__ . '/../storage/.uploads_mapa.json'; }

/** Lembra "uploads/x.jpg" => "img.php?id=..." entre um lote e outro, para a mesma foto nunca entrar duas vezes. */
function migracao_mapa_carregar(): array {
    $j = @file_get_contents(migracao_mapa_path());
    $m = $j ? json_decode($j, true) : null;
    return is_array($m) ? $m : [];
}
function migracao_mapa_salvar(array $cache): void {
    $ok = array_filter($cache, fn($v) => is_string($v) && strpos($v, 'img.php?id=') === 0);
    $dir = dirname(migracao_mapa_path());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents(migracao_mapa_path(), json_encode($ok), LOCK_EX);
}

/**
 * Migra imagens de uploads/ para o banco.
 * $limite = máximo de imagens novas nesta chamada (0 = sem limite).
 * Retorna: migradas, linhas, faltando[], falhas[], parcial (ainda há o que migrar), erro_tabela, avisos[].
 */
function octoart_migrar_uploads(int $limite = 0): array {
    $r = ['migradas' => 0, 'linhas' => 0, 'faltando' => [], 'falhas' => [], 'parcial' => false, 'erro_tabela' => false, 'avisos' => []];
    $raiz = realpath(__DIR__ . '/../uploads') ?: null;
    $cache = migracao_mapa_carregar();

    $caminho = function (string $valor) use (&$cache, &$r, $raiz, $limite): string {
        $t = trim(str_replace('\\', '/', $valor));
        if (!preg_match('~^uploads/.+~i', $t)) return $valor;
        if (isset($cache[$t])) return $cache[$t];

        $arquivo = $raiz ? realpath(__DIR__ . '/../' . $t) : false;
        if (!$arquivo || strpos($arquivo, $raiz . DIRECTORY_SEPARATOR) !== 0 || !is_file($arquivo)) {
            $r['faltando'][$t] = true;
            return $cache[$t] = $valor;
        }
        if ($limite > 0 && $r['migradas'] >= $limite) { $r['parcial'] = true; return $valor; }

        $bin = @file_get_contents($arquivo);
        $mime = ($bin === false || $bin === '') ? '' : (string)(new finfo(FILEINFO_MIME_TYPE))->buffer($bin);
        $nova = $mime !== '' ? store_image_binary($bin, $mime, null) : null;
        if (!$nova) {
            $r['falhas'][$t] = true;
            return $cache[$t] = $valor;
        }
        $r['migradas']++;
        return $cache[$t] = $nova;
    };

    $json = function ($dado) use (&$json, $caminho) {
        if (is_string($dado)) return $caminho($dado);
        if (is_array($dado)) foreach ($dado as $k => $v) $dado[$k] = $json($v);
        return $dado;
    };

    $falhaTabela = function (Throwable $e) use (&$r): void {
        $m = $e->getMessage();
        if (preg_match('/table|tabela/i', $m) && preg_match('/exist|found|no such/i', $m)) $r['erro_tabela'] = true;
        $r['avisos'][] = $m;
    };

    // Colunas com um caminho de imagem: [tabela, chave primária, coluna]
    $colunas = [
        ['usuarios',        'id_usuario', 'foto_perfil'],
        ['perfis_artistas', 'id_perfil',  'banner'],
        ['perfis_artistas', 'id_perfil',  'banner_url'],
        ['projetos',        'id_projeto', 'imagem_url'],
        ['imagens_projeto', 'id_imagem',  'storage_key'],
    ];
    foreach ($colunas as [$tabela, $pk, $col]) {
        try {
            $rows = db()->query("SELECT `$pk` AS pk, `$col` AS v FROM `$tabela` WHERE `$col` LIKE 'uploads/%'")->fetchAll();
            $up = db()->prepare("UPDATE `$tabela` SET `$col` = ? WHERE `$pk` = ?");
            foreach ($rows as $row) {
                $novo = $caminho((string)$row['v']);
                if ($novo !== $row['v']) { $up->execute([$novo, $row['pk']]); $r['linhas']++; }
            }
        } catch (Throwable $e) { $falhaTabela($e); }
    }

    // Blocos de conteúdo dos projetos (JSON)
    try {
        $rows = db()->query("SELECT id_projeto, conteudo FROM projetos WHERE conteudo LIKE '%uploads%'")->fetchAll();
        $up = db()->prepare('UPDATE projetos SET conteudo = ? WHERE id_projeto = ?');
        foreach ($rows as $row) {
            $dec = json_decode((string)$row['conteudo'], true);
            if (is_array($dec)) {
                $novo = json_encode($json($dec), JSON_UNESCAPED_UNICODE);
            } else {
                // Conteúdo que não é um JSON válido: troca os caminhos direto no texto.
                $novo = preg_replace_callback('~uploads\\\\?/[\w.\-/]+~', function ($m) use ($caminho) {
                    return $caminho(str_replace('\\', '', $m[0]));
                }, (string)$row['conteudo']);
            }
            if ($novo !== false && $novo !== $row['conteudo']) { $up->execute([$novo, $row['id_projeto']]); $r['linhas']++; }
        }
    } catch (Throwable $e) { $falhaTabela($e); }

    migracao_mapa_salvar($cache);
    return $r;
}

/** Marca a migração como concluída (não roda mais). */
function octoart_migracao_concluir(): void {
    $dir = dirname(migracao_flag_path());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents(migracao_flag_path(), date('c') . "\n");
}

/** Roda um lote com trava (uma visita por vez). Conclui sozinha quando não resta nada. */
function octoart_migrar_uploads_lote(): void {
    if (is_file(migracao_flag_path())) return;
    $dir = dirname(migracao_flag_path());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $lock = @fopen($dir . '/.migracao.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return;   // outra visita já está migrando
    try {
        if (is_file(migracao_flag_path())) return;
        @set_time_limit(120);
        $r = octoart_migrar_uploads(MIGRACAO_LOTE);
        // Só conclui se leu todas as tabelas, não ficou nada pela metade e nada falhou de vez.
        if (!$r['parcial'] && !$r['erro_tabela'] && !$r['falhas']) octoart_migracao_concluir();
        elseif (!$r['parcial'] && !$r['erro_tabela'] && $r['falhas'] && $r['migradas'] === 0) octoart_migracao_concluir();
    } catch (Throwable $e) {
        // nunca derruba o site por causa da migração
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Agenda o lote para depois que a página já foi enviada ao visitante. */
function octoart_agendar_migracao_uploads(): void {
    static $agendado = false;
    if ($agendado || is_file(migracao_flag_path())) return;
    $agendado = true;
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) @ob_end_flush();
            @flush();
        }
        octoart_migrar_uploads_lote();
    });
}
