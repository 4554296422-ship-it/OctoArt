<?php
/**
 * Ações do feed sem recarregar a página (curtir, comentar, denunciar).
 * Responde sempre em JSON.
 */
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json; charset=UTF-8');

function out(array $d, int $code = 200): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'msg' => 'Método inválido.'], 405);

$user = current_user();
if (!$user) out(['ok' => false, 'login' => true, 'msg' => 'Entre na sua conta para continuar.'], 401);

try {
    ensure_tables();
    $acao = $_POST['acao'] ?? '';
    $pid = (int)($_POST['id_projeto'] ?? 0);

    $st = db()->prepare('SELECT id_projeto, id_usuario, titulo, visivel FROM projetos WHERE id_projeto=?');
    $st->execute([$pid]);
    $p = $st->fetch();
    if (!$p || ((int)$p['visivel'] !== 1 && !is_admin($user))) {
        out(['ok' => false, 'msg' => 'Esta publicação não está mais disponível.'], 404);
    }
    $uid = (int)$user['id_usuario'];

    /* ---- Curtir / descurtir ---- */
    if ($acao === 'curtir') {
        $chk = db()->prepare('SELECT 1 FROM curtidas_projeto WHERE id_projeto=? AND id_usuario=?');
        $chk->execute([$pid, $uid]);
        if ($chk->fetch()) {
            db()->prepare('DELETE FROM curtidas_projeto WHERE id_projeto=? AND id_usuario=?')->execute([$pid, $uid]);
            $curtido = false;
        } else {
            db()->prepare('INSERT INTO curtidas_projeto (id_projeto,id_usuario) VALUES (?,?)')->execute([$pid, $uid]);
            $curtido = true;
            if ((int)$p['id_usuario'] !== $uid) {
                criar_notificacao((int)$p['id_usuario'], $uid, 'curtida', $user['nome'] . ' curtiu seu projeto “' . $p['titulo'] . '”.', 'projeto.php?id=' . $pid);
            }
        }
        $n = db()->prepare('SELECT COUNT(*) FROM curtidas_projeto WHERE id_projeto=?');
        $n->execute([$pid]);
        out(['ok' => true, 'curtido' => $curtido, 'total' => (int)$n->fetchColumn()]);
    }

    /* ---- Comentar ---- */
    if ($acao === 'comentario') {
        $texto = trim($_POST['comentario'] ?? '');
        if ($texto === '') out(['ok' => false, 'msg' => 'Escreva algo para comentar.'], 422);
        $texto = mb_substr($texto, 0, 500);
        db()->prepare('INSERT INTO comentarios_projeto (id_projeto,id_usuario,comentario) VALUES (?,?,?)')
            ->execute([$pid, $uid, $texto]);
        if ((int)$p['id_usuario'] !== $uid) {
            criar_notificacao((int)$p['id_usuario'], $uid, 'comentario', $user['nome'] . ' comentou no seu projeto “' . $p['titulo'] . '”.', 'projeto.php?id=' . $pid);
        }
        $n = db()->prepare('SELECT COUNT(*) FROM comentarios_projeto WHERE id_projeto=?');
        $n->execute([$pid]);
        out(['ok' => true, 'nome' => $user['nome'], 'comentario' => $texto, 'total' => (int)$n->fetchColumn()]);
    }

    /* ---- Denunciar ---- */
    if ($acao === 'denunciar') {
        if (is_admin($user)) out(['ok' => false, 'msg' => 'A administração gerencia denúncias pelo painel.'], 403);
        if ((int)$p['id_usuario'] === $uid) out(['ok' => false, 'msg' => 'Você não pode denunciar a sua própria publicação.'], 422);
        $motivo = $_POST['motivo'] ?? '';
        if (!array_key_exists($motivo, motivos_denuncia())) out(['ok' => false, 'msg' => 'Escolha o motivo da denúncia.'], 422);
        $detalhes = mb_substr(trim($_POST['detalhes'] ?? ''), 0, 1000);
        if ($motivo === 'outro' && $detalhes === '') out(['ok' => false, 'msg' => 'Explique brevemente o motivo.'], 422);

        $chk = db()->prepare('SELECT 1 FROM denuncias WHERE id_projeto=? AND id_denunciante=?');
        $chk->execute([$pid, $uid]);
        if ($chk->fetch()) out(['ok' => false, 'msg' => 'Você já denunciou esta publicação. Nossa equipe vai analisar.'], 409);

        db()->prepare('INSERT INTO denuncias (id_projeto,id_denunciante,motivo,detalhes,status,data_criacao) VALUES (?,?,?,?,?,?)')
            ->execute([$pid, $uid, $motivo, $detalhes ?: null, 'pendente', now_iso()]);
        out(['ok' => true, 'msg' => 'Denúncia enviada. Obrigado por ajudar a manter o OctoArt seguro!']);
    }

    out(['ok' => false, 'msg' => 'Ação desconhecida.'], 400);
} catch (Throwable $e) {
    out(['ok' => false, 'msg' => 'Não foi possível concluir agora. Tente novamente.'], 500);
}
