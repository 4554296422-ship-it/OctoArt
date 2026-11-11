<?php
require_once __DIR__ . '/includes/auth.php';
$id = (int)($_GET['id'] ?? 0);
ensure_tables();
$st = db()->prepare("SELECT pr.*, c.nome AS cat_nome, u.nome AS autor, u.tipo AS autor_tipo FROM projetos pr
  LEFT JOIN categorias c ON c.id_categoria=pr.id_categoria
  JOIN usuarios u ON u.id_usuario=pr.id_usuario WHERE pr.id_projeto=?");
$st->execute([$id]);
$p = $st->fetch();
if (!$p) { echo 'Projeto não encontrado'; exit; }

$user = current_user();
$e_dono = $user && (int)$user['id_usuario'] === (int)$p['id_usuario'];
$oculto = (int)$p['visivel'] !== 1;
if ($oculto && !$e_dono && !($user && is_admin($user))) {
    http_response_code(404);
    $page_title = 'Publicação indisponível — OctoArt';
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap" style="padding:60px 24px;text-align:center"><h2>Esta publicação não está disponível.</h2><p class="muted">Ela pode ter sido removida pela moderação ou pelo autor.</p><a class="btn btn-primary" href="index.php">Voltar ao início</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$flash = '';
$stAp = db()->prepare('SELECT id_perfil FROM perfis_artistas WHERE id_usuario=?');
$stAp->execute([$p['id_usuario']]);
$autor_perfil = $stAp->fetchColumn();

if ($user && is_member($user) && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['valor']) && (int)$user['id_usuario'] !== (int)$p['id_usuario']) {
    $perfil = ensure_perfil((int)$user['id_usuario']);
    if ($perfil && $p['tipo']==='briefing' && $p['status']==='aberto') {
        db()->prepare('INSERT INTO propostas (id_projeto,id_artista,valor,mensagem,status) VALUES (?,?,?,?,?)')
            ->execute([$id, $perfil['id_perfil'], (float)$_POST['valor'], trim($_POST['mensagem']??''), 'pendente']);
        // mensagem automática
        db()->prepare('INSERT INTO mensagens (id_remetente,id_destinatario,id_projeto,mensagem,data_envio) VALUES (?,?,?,?,?)')
            ->execute([
                $user['id_usuario'], $p['id_usuario'], $id,
                '💡 Proposta enviada: R$ '.number_format((float)$_POST['valor'],2,',','.')."\n".trim($_POST['mensagem']??''),
                now_iso()
            ]);
        $flash = 'Proposta enviada e mensagem registrada no chat!';
    }
}

$propostas = db()->prepare('SELECT pp.*, u.nome AS artista FROM propostas pp
  JOIN perfis_artistas pa ON pa.id_perfil=pp.id_artista
  JOIN usuarios u ON u.id_usuario=pa.id_usuario WHERE pp.id_projeto=?');
$propostas->execute([$id]);
$propostas = $propostas->fetchAll();

$n_curtidas = (int)db()->query('SELECT COUNT(*) FROM curtidas_projeto WHERE id_projeto=' . (int)$p['id_projeto'])->fetchColumn();
$curti = false;
if ($user) {
    $stc = db()->prepare('SELECT 1 FROM curtidas_projeto WHERE id_projeto=? AND id_usuario=?');
    $stc->execute([$p['id_projeto'], $user['id_usuario']]);
    $curti = (bool)$stc->fetch();
}
$stc = db()->prepare('SELECT cc.comentario, cc.data_criacao, u.nome FROM comentarios_projeto cc JOIN usuarios u ON u.id_usuario=cc.id_usuario WHERE cc.id_projeto=? ORDER BY cc.data_criacao DESC');
$stc->execute([$p['id_projeto']]);
$lista_coment = $stc->fetchAll();

$page_title = $p['titulo'].' — OctoArt';
$active = 'projetos';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap" style="padding:24px 24px 60px;max-width:760px">
  <div class="crumb"><a href="index.php">Início</a> / <a href="explorar.php?tipo=projetos">Projetos</a> / <?= h($p['titulo']) ?></div>
  <?php if ($flash): ?><div class="success-msg"><?= h($flash) ?></div><?php endif; ?>
  <?php if ($oculto): ?><div class="error-msg show"> Esta publicação está oculta (removida pela moderação). Só você e a administração conseguem vê-la.</div><?php endif; ?>
  <div style="margin-top:12px;display:flex;gap:10px;align-items:center">
    <span class="tag"><?= h($p['cat_nome'] ?: 'Geral') ?></span>
    <span class="status-pill status-<?= h($p['status']) ?>"><?= h($p['status']) ?></span>
  </div>
  <h1><?= h($p['titulo']) ?></h1>
  <p class="muted">Publicado por <a href="<?= $autor_perfil ? 'artista.php?id='.(int)$autor_perfil : '#' ?>"><?= h($p['autor']) ?></a> · <?= fmt_date($p['data_criacao']) ?></p>
  <?php if (!empty($p['imagem_url'])): ?><img src="<?= h(media_url($p['imagem_url'])) ?>" alt="" style="width:100%;max-height:420px;object-fit:cover;border-radius:16px;margin:8px 0 16px;background:var(--grey-100)"><?php endif; ?>
  <p><?= nl2br(h($p['descricao'])) ?></p>
  <link rel="stylesheet" href="assets/css/editor.css">
  <?php foreach ((json_decode($p['conteudo'] ?? '[]', true) ?: []) as $b): ?>
    <?php if (($b['type'] ?? '') === 'text'): ?>
      <p style="line-height:1.65;margin:14px 0"><?= nl2br(h($b['content'] ?? '')) ?></p>
    <?php elseif (($b['type'] ?? '') === 'image'): ?>
      <?php $img = $b['url'] ?? $b['key'] ?? ''; if ($img): ?>
        <img src="<?= h(media_url($img)) ?>" alt="" style="width:100%;max-height:640px;object-fit:contain;border-radius:14px;margin:12px 0;background:var(--grey-100)">
      <?php endif; ?>
    <?php elseif (($b['type'] ?? '') === 'grid'): ?>
      <?php $cols = (int)($b['cols'] ?? count($b['images'] ?? []) ?: 2); $cols = max(2, min(4, $cols)); ?>
      <div class="project-photo-grid cols-<?= $cols ?>">
        <?php foreach (($b['images'] ?? []) as $img): if (!$img) continue; ?>
          <img src="<?= h(media_url($img)) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if ($p['tipo']==='briefing'): ?>
    <p><strong>Orçamento:</strong> <?= money($p['orcamento_min']) ?> – <?= money($p['orcamento_max']) ?></p>
  <?php endif; ?>

  <?php if (!$oculto): ?>
  <div class="social-post" id="projeto-<?= (int)$p['id_projeto'] ?>" style="margin:18px 0;padding:0 16px 14px">
    <div class="post-stats"><span>❤️ <b data-likes="<?= (int)$p['id_projeto'] ?>"><?= $n_curtidas ?></b> curtidas</span><span>💬 <b data-comments="<?= (int)$p['id_projeto'] ?>"><?= count($lista_coment) ?></b> comentários</span></div>
    <div class="post-actions">
      <?php if ($user): ?>
        <form method="post" class="js-like-form"><input type="hidden" name="acao" value="curtir"><input type="hidden" name="id_projeto" value="<?= (int)$p['id_projeto'] ?>"><button class="social-action <?= $curti ? 'liked' : '' ?>" type="submit">♥ <span><?= $curti ? 'Curtido' : 'Curtir' ?></span></button></form>
      <?php else: ?><a class="social-action" href="login.php">♥ <span>Curtir</span></a><?php endif; ?>
      <?php if (!$e_dono && ($p['autor_tipo'] ?? '') !== 'admin'): ?>
        <a class="social-action" href="<?= $user ? 'mensagens.php?com='.(int)$p['id_usuario'] : 'login.php' ?>">✉️ <span>Mensagem</span></a>
      <?php endif; ?>
      <?php if ($user && !is_admin($user) && !$e_dono): ?>
        <button class="social-action js-report" type="button" data-id="<?= (int)$p['id_projeto'] ?>">🚩 <span>Denunciar</span></button>
      <?php endif; ?>
    </div>
    <h3 style="margin:16px 0 6px" id="comentarios">Comentários</h3>
    <?php if ($user): ?>
      <form method="post" class="comment-form js-comment-form" style="margin-bottom:10px"><input type="hidden" name="acao" value="comentario"><input type="hidden" name="id_projeto" value="<?= (int)$p['id_projeto'] ?>"><input name="comentario" maxlength="500" placeholder="Escreva um comentário..." autocomplete="off"><button type="submit">Enviar</button></form>
    <?php else: ?><p class="muted"><a href="login.php">Entre</a> para curtir e comentar.</p><?php endif; ?>
    <div class="post-comments" id="comentarios-<?= (int)$p['id_projeto'] ?>"><?php foreach ($lista_coment as $c): ?><div class="mini-comment"><strong><?= h($c['nome']) ?></strong><span><?= h($c['comentario']) ?></span></div><?php endforeach; ?></div>
  </div>
  <?php endif; ?>

  <?php if ($user && (int)$user['id_usuario'] === (int)$p['id_usuario']): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:16px 0">
      <a class="btn btn-outline btn-sm" href="criar_projeto.php?id=<?= (int)$p['id_projeto'] ?>">✏️ Editar publicação</a>
    </div>
  <?php endif; ?>

  <?php if ($user && is_member($user) && $p['tipo']==='briefing' && $p['status']==='aberto' && (int)$user['id_usuario'] !== (int)$p['id_usuario']): ?>
    <div class="table-card" style="padding:20px;margin-top:24px">
      <h3>Enviar proposta</h3>
      <form method="post">
        <div class="field"><label>Valor (R$)</label><input type="number" name="valor" min="0" step="10" required></div>
        <div class="field"><label>Mensagem</label><textarea name="mensagem" required></textarea></div>
        <button class="btn btn-primary" type="submit">Enviar proposta + mensagem</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($user && (int)$user['id_usuario'] === (int)$p['id_usuario']): ?>
    <h3 style="margin-top:28px">Propostas recebidas</h3>
    <?php foreach ($propostas as $pp): ?>
      <div class="card" style="padding:14px;margin-bottom:8px;border:1px solid var(--grey-300);border-radius:12px">
        <strong><?= h($pp['artista']) ?></strong> · <?= money($pp['valor']) ?> · <?= h($pp['status']) ?>
        <p><?= h($pp['mensagem']) ?></p>
      </div>
    <?php endforeach; ?>
    <?php if (!$propostas): ?><p class="muted">Nenhuma proposta ainda.</p><?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/report_modal.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
