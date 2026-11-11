<?php
require_once __DIR__ . '/includes/auth.php';
$theme = $_COOKIE['octoart_theme'] ?? 'dark';
$page_title = 'OctoArt — Feed';
$active = 'inicio';
$user = current_user();

/* Tabelas sociais (curtidas, comentários, denúncias) + perfis de todos os usuários */
ensure_tables();
sync_perfis();

/* Avisos ativos da administração na página principal */
$avisos_principais = [];
try {
    $colDestino = db()->query("SHOW COLUMNS FROM avisos LIKE 'destino'")->fetch();
    $destinoUsuario = 'ambos';
    if ($user) {
        $tipoUsuario = strtolower((string)($user['tipo'] ?? ''));
        if (str_contains($tipoUsuario, 'artist')) $destinoUsuario = 'artistas';
        elseif (str_contains($tipoUsuario, 'client')) $destinoUsuario = 'clientes';
    }
    if ($colDestino) {
        if ($destinoUsuario === 'ambos') {
            $stAvisos = db()->query("SELECT titulo, mensagem, data_criacao FROM avisos WHERE ativo=1 AND destino='ambos' ORDER BY data_criacao DESC LIMIT 5");
        } else {
            $stAvisos = db()->prepare("SELECT titulo, mensagem, data_criacao FROM avisos WHERE ativo=1 AND (destino=? OR destino='ambos') ORDER BY data_criacao DESC LIMIT 5");
            $stAvisos->execute([$destinoUsuario]);
        }
    } else {
        $stAvisos = db()->query("SELECT titulo, mensagem, data_criacao FROM avisos WHERE ativo=1 ORDER BY data_criacao DESC LIMIT 5");
    }
    $avisos_principais = $stAvisos->fetchAll();
} catch (Throwable $e) { $avisos_principais = []; }

/* Curtir / descurtir */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $acao = $_POST['acao'] ?? '';
    $pid = (int)($_POST['id_projeto'] ?? 0);
    if ($pid > 0 && $acao === 'curtir') {
        try {
            $st = db()->prepare('SELECT 1 FROM curtidas_projeto WHERE id_projeto=? AND id_usuario=?');
            $st->execute([$pid, $user['id_usuario']]);
            if ($st->fetch()) {
                db()->prepare('DELETE FROM curtidas_projeto WHERE id_projeto=? AND id_usuario=?')->execute([$pid, $user['id_usuario']]);
            } else {
                db()->prepare('INSERT INTO curtidas_projeto (id_projeto,id_usuario) VALUES (?,?)')->execute([$pid, $user['id_usuario']]);
            }
        } catch (Throwable $e) {}
        header('Location: index.php#projeto-' . $pid);
        exit;
    }
    if ($pid > 0 && $acao === 'comentario') {
        $comentario = trim($_POST['comentario'] ?? '');
        if ($comentario !== '') {
            try {
                db()->prepare('INSERT INTO comentarios_projeto (id_projeto,id_usuario,comentario) VALUES (?,?,?)')
                    ->execute([$pid, $user['id_usuario'], $comentario]);
            } catch (Throwable $e) {}
        }
        header('Location: index.php#projeto-' . $pid);
        exit;
    }
}

$cats = [];
$projetos = [];
$erro_db = null;
try {
    $cats = db()->query('SELECT * FROM categorias ORDER BY nome')->fetchAll();
    $projetos = db()->query("SELECT pr.*, c.nome AS cat_nome, u.id_usuario AS autor_id, u.tipo AS autor_tipo, u.nome AS autor,
        u.foto_perfil, pa.id_perfil AS autor_perfil, pa.localizacao,
        COALESCE(NULLIF(pr.imagem_url,''),(SELECT ip.storage_key FROM imagens_projeto ip WHERE ip.id_projeto=pr.id_projeto ORDER BY ip.ordem,id_imagem LIMIT 1)) AS capa,
        (SELECT COUNT(*) FROM curtidas_projeto cp WHERE cp.id_projeto=pr.id_projeto) AS curtidas,
        (SELECT COUNT(*) FROM comentarios_projeto cc WHERE cc.id_projeto=pr.id_projeto) AS comentarios,
        " . ($user ? "EXISTS(SELECT 1 FROM curtidas_projeto me WHERE me.id_projeto=pr.id_projeto AND me.id_usuario=" . (int)$user['id_usuario'] . ")" : "0") . " AS curti
        FROM projetos pr
        LEFT JOIN categorias c ON c.id_categoria=pr.id_categoria
        JOIN usuarios u ON u.id_usuario=pr.id_usuario
        LEFT JOIN perfis_artistas pa ON pa.id_usuario=u.id_usuario
        WHERE pr.visivel=1 AND pr.status NOT IN ('cancelado','excluido')
        ORDER BY pr.data_criacao DESC")->fetchAll();
} catch (Throwable $e) {
    $erro_db = $e->getMessage();
}

/* Comentários recentes por projeto */
$comentarios = [];
if ($projetos) {
    try {
        $ids = array_map(fn($p) => (int)$p['id_projeto'], $projetos);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT cc.id_projeto,cc.comentario,cc.data_criacao,u.nome,u.foto_perfil
            FROM comentarios_projeto cc JOIN usuarios u ON u.id_usuario=cc.id_usuario
            WHERE cc.id_projeto IN ($ph) ORDER BY cc.data_criacao DESC");
        $st->execute($ids);
        foreach ($st->fetchAll() as $c) {
            $pid = (int)$c['id_projeto'];
            if (!isset($comentarios[$pid])) $comentarios[$pid] = [];
            if (count($comentarios[$pid]) < 3) $comentarios[$pid][] = $c;
        }
    } catch (Throwable $e) {}
}

require __DIR__ . '/includes/header.php';
?>

<?php if ($erro_db): ?>
<div class="wrap" style="padding:24px"><div class="error-msg show">Não foi possível carregar todo o feed agora. Verifique a conexão com o banco.</div></div>
<?php endif; ?>

<section class="social-top">
  <div class="wrap">
    <div class="social-top-main">
      <div class="hero-copy">
        <span class="eyebrow-hand">OctoArt</span>
        <h1>O que a comunidade está criando.</h1>
        <p>Descubra trabalhos, acompanhe pessoas criativas e encontre projetos publicados pela comunidade.</p>
        <div class="social-top-actions">
          <a class="btn btn-primary" href="<?= $user ? 'dashboard.php?tab=publicar' : 'login.php?cadastro=1' ?>"><?= $user ? '+ Nova publicação' : 'Criar conta grátis' ?></a>
          <a class="btn btn-outline" href="explorar.php?tipo=artistas">Conhecer a comunidade</a>
        </div>
      </div>
      <img src="<?= h($app_base) ?>assets/img/<?= $theme === 'dark' ? 'perfil-noite.png' : 'perfil.png' ?>" alt="Polvo mascote do OctoArt" class="mascot-img hero-mascot">
    </div>
  </div>
</section>

<?php if ($avisos_principais): ?>
<section class="wrap" aria-label="Avisos da administração" style="padding-top:20px;padding-bottom:4px">
  <div style="box-sizing:border-box;width:100%;border:2px solid #e5e0ff;border-radius:24px;padding:24px 28px;background:linear-gradient(115deg,#f0edff 0%,#e8e4fb 100%);color:#111827;box-shadow:none">
    <h2 style="margin:0 0 30px;font-size:1.35rem;font-weight:700;line-height:1.3;color:#111827">Avisos da administração</h2>
    <div style="display:grid;gap:18px">
      <?php foreach ($avisos_principais as $aviso): ?>
        <article style="padding:0;background:transparent;border:0;color:#111827">
          <h3 style="margin:0 0 12px;font-size:1.25rem;font-weight:700;line-height:1.35;color:#111827"><?= h($aviso['titulo']) ?></h3>
          <p style="margin:0;white-space:pre-line;line-height:1.5;font-size:1.1rem;color:#111827"><?= h($aviso['mensagem']) ?></p>
          <small style="display:block;margin-top:10px;font-size:1rem;color:#536078"><?= h(date('d/m/Y', strtotime($aviso['data_criacao'] ?? 'now'))) ?></small>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="wrap social-layout">
  <main class="social-feed" id="feed">
    <?php if ($user): ?>
      <div class="composer-card">
        <div class="composer-avatar">
          <?php if (!empty($user['foto_perfil'])): ?><span class="avatar-circle" style="width:44px;height:44px;background-image:url('<?= h(media_url($user['foto_perfil'])) ?>');background-size:cover;background-position:center"></span><?php else: ?><span class="avatar-circle" style="width:44px;height:44px"><?= h(initials($user['nome'])) ?></span><?php endif; ?>
        </div>
        <div class="composer-content">
          <strong>Compartilhe com a comunidade</strong>
          <span>Publique um trabalho ou peça ajuda para um projeto seu.</span>
          <div class="composer-actions">
            <?php if (is_member($user)): ?><a class="btn btn-sm btn-primary" href="dashboard.php?tab=publicar"> Nova publicação</a><?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="feed-toolbar">
      <div><h2>Feed</h2><span><?= count($projetos) ?> publicações</span></div>
      <div class="feed-tabs">
        <a class="active" href="#feed">Todos</a>
        <a href="explorar.php?tipo=projetos">Projetos</a>
        <a href="explorar.php?tipo=artistas">Comunidade</a>
      </div>
    </div>

    <?php if (!$projetos): ?>
      <div class="social-empty"><div class="social-empty-icon">🎨</div><h3>Ainda não há publicações</h3><p>Seja o primeiro a publicar um projeto no OctoArt.</p></div>
    <?php endif; ?>

    <?php foreach ($projetos as $p):
      $pid = (int)$p['id_projeto'];
      $meu = $user && (int)$user['id_usuario'] === (int)$p['autor_id'];
      $pode_msg = !$meu && ($p['autor_tipo'] ?? '') !== 'admin';
    ?>
      <article class="social-post" id="projeto-<?= $pid ?>">
        <header class="post-head">
          <a class="post-author" href="<?= $p['autor_perfil'] ? 'artista.php?id='.(int)$p['autor_perfil'] : 'javascript:void(0)' ?>">
            <?php if (!empty($p['foto_perfil'])): ?><span class="post-avatar" style="background-image:url('<?= h(media_url($p['foto_perfil'])) ?>')"></span><?php else: ?><span class="post-avatar"><?= h(initials($p['autor'])) ?></span><?php endif; ?>
            <span><strong><?= h($p['autor']) ?></strong><small><?= !empty($p['localizacao']) ? h($p['localizacao']).' · ' : '' ?><?= $p['tipo']==='portfolio' ? 'Publicou um trabalho' : 'Procura alguém para um projeto' ?> · <?= fmt_date($p['data_criacao']) ?></small></span>
          </a>
          <?php if ($user && !is_admin($user)): ?>
            <div class="post-menu">
              <button class="post-more" type="button" aria-label="Mais opções">•••</button>
              <div class="post-menu-list" hidden>
                <?php if ($meu): ?>
                  <a href="dashboard.php?tab=editar&id=<?= $pid ?>"> Editar publicação</a>
                <?php else: ?>
                  <button type="button" class="js-report danger" data-id="<?= $pid ?>"> Denunciar publicação</button>
                <?php endif; ?>
              </div>
            </div>
          <?php elseif (!$user): ?>
            <div class="post-menu">
              <button class="post-more" type="button" aria-label="Mais opções">•••</button>
              <div class="post-menu-list" hidden><a href="login.php" class="danger"> Entre para denunciar</a></div>
            </div>
          <?php endif; ?>
        </header>

        <div class="post-meta"><span class="tag"><?= h($p['cat_nome'] ?: 'Geral') ?></span><?php if ($p['tipo']==='briefing'): ?><span class="status-pill status-aberto">Aberto</span><?php else: ?><span class="post-kind">Trabalho</span><?php endif; ?></div>

        <a href="projeto.php?id=<?= $pid ?>" class="post-title-link"><h3><?= h($p['titulo']) ?></h3></a>
        <?php if (!empty($p['descricao'])): ?><p class="post-text"><?= nl2br(h($p['descricao'])) ?></p><?php endif; ?>

        <?php if (!empty($p['capa'])): ?>
          <a href="projeto.php?id=<?= $pid ?>" class="post-media"><img src="<?= h(media_url($p['capa'])) ?>" alt="<?= h($p['titulo']) ?>" loading="lazy"></a>
        <?php else: ?>
          <a href="projeto.php?id=<?= $pid ?>" class="post-media post-placeholder"><span><?= $p['tipo']==='portfolio' ? '🎨' : '📌' ?></span><b><?= $p['tipo']==='portfolio' ? 'Trabalho publicado' : 'Novo projeto aberto' ?></b></a>
        <?php endif; ?>

        <?php if ($p['tipo']==='briefing' && ($p['orcamento_min'] !== null || $p['orcamento_max'] !== null)): ?>
          <div class="post-budget"><span>Orçamento</span><strong><?= money($p['orcamento_min']) ?> – <?= money($p['orcamento_max']) ?></strong></div>
        <?php endif; ?>

        <div class="post-stats"><span>❤️ <b data-likes="<?= $pid ?>"><?= (int)$p['curtidas'] ?></b> curtidas</span><span>💬 <b data-comments="<?= $pid ?>"><?= (int)$p['comentarios'] ?></b> comentários</span></div>
        <div class="post-actions">
          <?php if ($user): ?>
            <form method="post" class="js-like-form"><input type="hidden" name="acao" value="curtir"><input type="hidden" name="id_projeto" value="<?= $pid ?>"><button class="social-action <?= !empty($p['curti']) ? 'liked' : '' ?>" type="submit">♥ <span><?= !empty($p['curti']) ? 'Curtido' : 'Curtir' ?></span></button></form>
          <?php else: ?><a class="social-action" href="login.php">♥ <span>Curtir</span></a><?php endif; ?>
          <button class="social-action js-focus-comment" type="button" data-id="<?= $pid ?>">💬 <span>Comentar</span></button>
          <?php if ($pode_msg): ?>
            <a class="social-action" href="<?= $user ? 'mensagens.php?com='.(int)$p['autor_id'] : 'login.php' ?>">✉️ <span>Mensagem</span></a>
          <?php endif; ?>
          <button class="social-action share-btn" type="button" data-url="<?= h('projeto.php?id='.$pid) ?>">↗ <span>Compartilhar</span></button>
        </div>

        <div class="post-comments" id="comentarios-<?= $pid ?>" data-limit="3"><?php foreach (($comentarios[$pid] ?? []) as $c): ?><div class="mini-comment"><strong><?= h($c['nome']) ?></strong><span><?= h($c['comentario']) ?></span></div><?php endforeach; ?></div>
        <?php if ($user): ?>
          <form method="post" class="comment-form js-comment-form"><input type="hidden" name="acao" value="comentario"><input type="hidden" name="id_projeto" value="<?= $pid ?>"><input name="comentario" maxlength="500" placeholder="Escreva um comentário..." autocomplete="off"><button type="submit">Enviar</button></form>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </main>

  <aside class="social-sidebar">
    <section class="side-card">
      <div class="side-head"><h3>Categorias</h3></div>
      <div class="side-tags">
        <?php foreach ($cats as $c): ?><a href="explorar.php?tipo=projetos&categoria=<?= (int)$c['id_categoria'] ?>">#<?= h($c['nome']) ?></a><?php endforeach; ?>
      </div>
    </section>


</div>

<?php require __DIR__ . '/includes/report_modal.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
