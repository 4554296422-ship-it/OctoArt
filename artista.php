<?php
require_once __DIR__ . '/includes/auth.php';
sync_perfis();
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("
  SELECT p.*, u.nome, u.foto_perfil, u.verificado, u.data_cadastro, u.tipo AS tipo_usuario, u.id_usuario AS uid
  FROM perfis_artistas p JOIN usuarios u ON u.id_usuario=p.id_usuario
  WHERE p.id_perfil=?
");
$st->execute([$id]);
$a = $st->fetch();
if (!$a || $a['tipo_usuario'] === 'admin') { http_response_code(404); echo 'Perfil não encontrado'; exit; }

$msg_ok = $msg_err = '';
$user = current_user();

// Favoritar
if ($user && isset($_GET['fav'])) {
    $chk = db()->prepare('SELECT id_favorito FROM favoritos WHERE id_cliente=? AND id_artista=?');
    $chk->execute([$user['id_usuario'], $id]);
    $f = $chk->fetch();
    if ($f) db()->prepare('DELETE FROM favoritos WHERE id_favorito=?')->execute([$f['id_favorito']]);
    else db()->prepare('INSERT INTO favoritos (id_cliente,id_artista) VALUES (?,?)')->execute([$user['id_usuario'], $id]);
    header('Location: artista.php?id='.$id);
    exit;
}

// Mensagem simples
if ($user && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['acao']??'')==='msg') {
    $texto = trim($_POST['mensagem'] ?? '');
    if (is_admin($user)) {
        $msg_err = 'A administração não envia mensagens por aqui.';
    } elseif ((int)$user['id_usuario'] === (int)$a['uid']) {
        $msg_err = 'Você não pode enviar mensagem para si mesmo.';
    } elseif ($texto !== '') {
        db()->prepare('INSERT INTO mensagens (id_remetente,id_destinatario,mensagem,data_envio) VALUES (?,?,?,?)')
            ->execute([$user['id_usuario'], $a['uid'], $texto, now_iso()]);
        header('Location: mensagens.php?com='.$a['uid']);
        exit;
    } else {
        $msg_err = 'Escreva uma mensagem.';
    }
}

// Pedido + mensagem obrigatória
if ($user && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['acao']??'')==='pedido') {
    if (!is_member($user) || (int)$user['id_usuario'] === (int)$a['uid']) {
        $msg_err = 'Não é possível enviar este pedido.';
    } else {
        $titulo = trim($_POST['titulo'] ?? '');
        $desc = trim($_POST['descricao'] ?? '');
        $mensagem = trim($_POST['mensagem'] ?? '');
        $min = ($_POST['min'] ?? '') !== '' ? (float)$_POST['min'] : null;
        $max = ($_POST['max'] ?? '') !== '' ? (float)$_POST['max'] : null;
        if ($titulo==='' || $desc==='' || $mensagem==='') {
            $msg_err = 'Preencha título, descrição e a mensagem obrigatória.';
        } else {
            $cat = db()->prepare("SELECT id_categoria FROM projetos WHERE id_usuario=? AND id_categoria IS NOT NULL LIMIT 1");
            $cat->execute([$a['uid']]);
            $id_cat = $cat->fetchColumn() ?: null;
            db()->prepare('INSERT INTO projetos (id_usuario,id_categoria,tipo,titulo,descricao,orcamento_min,orcamento_max,status,visivel) VALUES (?,?,?,?,?,?,?,?,1)')
                ->execute([$user['id_usuario'], $id_cat, 'briefing', $titulo, $desc, $min, $max, 'aberto']);
            $pid = (int)db()->lastInsertId();
            db()->prepare('INSERT INTO propostas (id_projeto,id_artista,valor,mensagem,status) VALUES (?,?,?,?,?)')
                ->execute([$pid, $id, $max ?: 0, $mensagem, 'pendente']);
            $chat = "📬 Novo pedido: \"$titulo\"\n\n$mensagem\n\nOrçamento: ".money($min).' – '.money($max);
            db()->prepare('INSERT INTO mensagens (id_remetente,id_destinatario,id_projeto,mensagem,data_envio) VALUES (?,?,?,?,?)')
                ->execute([$user['id_usuario'], $a['uid'], $pid, $chat, now_iso()]);
            header('Location: mensagens.php?com='.$a['uid']);
            exit;
        }
    }
}

$portfolio = db()->prepare("SELECT * FROM projetos WHERE id_usuario=? AND visivel=1 AND status NOT IN ('cancelado','excluido') ORDER BY data_criacao DESC");
$portfolio->execute([$a['uid']]);
$portfolio = $portfolio->fetchAll();

$avs = db()->prepare('SELECT av.*, u.nome AS cliente FROM avaliacoes av JOIN usuarios u ON u.id_usuario=av.id_avaliador WHERE av.id_avaliado=? ORDER BY data_avaliacao DESC');
$avs->execute([$id]);
$avs = $avs->fetchAll();
$media = $avs ? array_sum(array_column($avs,'nota'))/count($avs) : 0;

$isFav = false;
if ($user) {
    $f = db()->prepare('SELECT 1 FROM favoritos WHERE id_cliente=? AND id_artista=?');
    $f->execute([$user['id_usuario'], $id]);
    $isFav = (bool)$f->fetch();
}

$page_title = $a['nome'].' — OctoArt';
$active = 'artistas';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap" style="padding:24px 24px 60px">
  <div class="crumb"><a href="index.php">Início</a> / <a href="explorar.php?tipo=artistas">Comunidade</a> / <?= h($a['nome']) ?></div>

  <?php if ($msg_err): ?><div class="error-msg show"><?= h($msg_err) ?></div><?php endif; ?>

  <section class="artist-profile-hero">
    <?php if (!empty($a['banner'])): ?>
      <div class="artist-banner" style="background-image:url('<?= h(media_url($a['banner'])) ?>')" role="img" aria-label="Banner de <?= h($a['nome']) ?>"></div>
    <?php else: ?>
      <div class="artist-banner artist-banner--default" aria-hidden="true"></div>
    <?php endif; ?>
    <div class="profile-head artist-profile-head">
    <?php if ($a['foto_perfil']): ?>
      <img src="<?= h($a['foto_perfil']) ?>" alt="" style="width:78px;height:78px;border-radius:50%;object-fit:cover;border:3px solid var(--blue)">
    <?php else: ?>
      <div class="avatar" style="width:78px;height:78px;font-size:1.6rem"><?= h(initials($a['nome'])) ?></div>
    <?php endif; ?>
    <div style="flex:1">
      <h1 style="margin:0"><?= h($a['nome']) ?></h1>
      <div class="profile-meta" style="display:flex;gap:16px;flex-wrap:wrap;color:var(--grey-600);margin-top:6px">
        <span>📍 <?= h($a['localizacao'] ?: 'Cidade não informada') ?></span>
        <span>★ <?= $media ? number_format($media,1) : 'sem avaliações' ?> (<?= count($avs) ?>)</span>
        <span>Membro desde <?= h(date('m/Y', strtotime($a['data_cadastro'] ?? 'now'))) ?></span>
      </div>
      <?php if (!empty($a['instagram']) || !empty($a['site'])): ?>
      <div class="profile-links" style="display:flex;gap:14px;flex-wrap:wrap;margin-top:8px;font-size:.92rem">
        <?php if (!empty($a['instagram'])): ?><a href="https://instagram.com/<?= h(ltrim($a['instagram'],'@')) ?>" target="_blank" rel="noopener">📸 <?= h($a['instagram']) ?></a><?php endif; ?>
        <?php if (!empty($a['site'])): ?><a href="<?= h($a['site']) ?>" target="_blank" rel="noopener">🔗 <?= h(preg_replace('~^https?://(www\.)?~','',$a['site'])) ?></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($user && (int)$user['id_usuario'] !== (int)$a['uid']): ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-primary" type="button" onclick="document.getElementById('pedidoModal').classList.add('show')">Enviar pedido</button>
        <button class="btn btn-outline" type="button" onclick="document.getElementById('msgModal').classList.add('show')">Mensagem</button>
        <a class="btn btn-ghost" href="artista.php?id=<?= $id ?>&fav=1"><?= $isFav ? '★ Favorito' : '☆ Favoritar' ?></a>
      </div>
    <?php elseif ($user && (int)$user['id_usuario'] === (int)$a['uid']): ?>
      <a class="btn btn-primary" href="dashboard.php?tab=perfil">✏️ Editar meu perfil</a>
    <?php elseif (!$user): ?>
      <a class="btn btn-primary" href="login.php">Entre para contatar</a>
    <?php endif; ?>
    </div>
  </section>

  <section class="artist-about-section">
    <h3>Sobre</h3>
  <p class="muted"><?= nl2br(h($a['bio'] ?: 'Esta pessoa ainda não escreveu uma bio.')) ?></p>

  </section>

  <section class="artist-content-section">
  <div class="artist-section-heading"><h3>Publicações</h3><span><?= count($portfolio) ?> <?= count($portfolio) === 1 ? 'publicação' : 'publicações' ?></span></div>
  <div class="grid grid-3" style="margin-top:12px">
    <?php foreach ($portfolio as $p):
      // capa: imagem_url ou primeira imagem do conteúdo
      $capa = $p['imagem_url'] ?? '';
      if (!$capa) {
        $blocosTmp = json_decode($p['conteudo'] ?? '[]', true) ?: [];
        foreach ($blocosTmp as $b) {
          if (($b['type'] ?? '') === 'image' && !empty($b['url'] ?? $b['key'] ?? '')) {
            $capa = $b['url'] ?? $b['key'];
            break;
          }
          if (($b['type'] ?? '') === 'grid' && !empty($b['images'][0])) {
            $capa = $b['images'][0];
            break;
          }
        }
      }
      $resumo = mb_substr($p['descricao'] ?? '', 0, 90);
      if (mb_strlen($p['descricao'] ?? '') > 90) $resumo .= '…';
    ?>
      <a class="card-project me-project-card" href="projeto.php?id=<?= (int)$p['id_projeto'] ?>" style="text-decoration:none;color:inherit;display:block">
        <?php if ($capa): ?>
          <img src="<?= h($capa) ?>" alt="" class="me-project-cover">
        <?php else: ?>
          <div class="me-project-cover me-project-placeholder"><?= ($p['tipo'] ?? '') === 'portfolio' ? '🎨' : '📌' ?></div>
        <?php endif; ?>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px">
          <?php if ($p['tipo']==='briefing'): ?><span class="status-pill status-aberto">Procurando alguém</span><?php endif; ?>
        </div>
        <h4 style="margin:0 0 6px"><?= h($p['titulo']) ?></h4>
        <p class="muted" style="font-size:.88rem;margin:0"><?= h($resumo) ?></p>
      </a>
    <?php endforeach; ?>
    <?php if (!$portfolio): ?><p class="muted">Ainda sem publicações.</p><?php endif; ?>
  </div>

  <div class="artist-section-heading artist-reviews-heading"><h3>Avaliações</h3><span><?= count($avs) ?> <?= count($avs) === 1 ? 'avaliação' : 'avaliações' ?></span></div>
  <?php foreach ($avs as $r): ?>
    <div class="card" style="padding:14px;margin-bottom:10px;background:var(--white);border-radius:12px;border:1px solid var(--grey-300)">
      <strong><?= h($r['cliente']) ?></strong> · ★ <?= (int)$r['nota'] ?>
      <p style="margin:.4rem 0 0"><?= h($r['comentario']) ?></p>
    </div>
  <?php endforeach; ?>
  <?php if (!$avs): ?><p class="muted artist-empty-state">Sem avaliações ainda.</p><?php endif; ?>
  </section>
</div>

<!-- Modal mensagem -->
<div class="modal-overlay" id="msgModal" onclick="if(event.target===this)this.classList.remove('show')">
  <div class="modal">
    <div class="modal-head"><h3 style="margin:0">Enviar mensagem</h3><button class="modal-close" type="button" onclick="document.getElementById('msgModal').classList.remove('show')">✕</button></div>
    <form method="post">
      <input type="hidden" name="acao" value="msg">
      <div class="field"><label>Mensagem</label><textarea name="mensagem" required></textarea></div>
      <button class="btn btn-primary btn-block" type="submit">Enviar</button>
    </form>
  </div>
</div>

<!-- Modal pedido -->
<div class="modal-overlay" id="pedidoModal" onclick="if(event.target===this)this.classList.remove('show')">
  <div class="modal">
    <div class="modal-head"><h3 style="margin:0">Enviar pedido</h3><button class="modal-close" type="button" onclick="document.getElementById('pedidoModal').classList.remove('show')">✕</button></div>
    <form method="post">
      <input type="hidden" name="acao" value="pedido">
      <div class="field"><label>Título</label><input type="text" name="titulo" required></div>
      <div class="field"><label>Descrição</label><textarea name="descricao" required></textarea></div>
      <div class="field-row">
        <div class="field"><label>Orçamento mín.</label><input type="number" name="min" min="0"></div>
        <div class="field"><label>Orçamento máx.</label><input type="number" name="max" min="0"></div>
      </div>
      <div class="field"><label>Mensagem (obrigatória)</label><textarea name="mensagem" required placeholder="Olá! Gostaria de..."></textarea></div>
      <button class="btn btn-primary btn-block" type="submit">Enviar pedido + mensagem</button>
      <p class="form-note">O pedido só é enviado junto com a mensagem no chat.</p>
    </form>
  </div>
</div>
<style>
.artist-profile-hero{margin:18px 0 26px;border:1px solid var(--grey-300);border-radius:18px;overflow:hidden;background:var(--white);box-shadow:0 8px 24px rgba(30,20,90,.06)}
.artist-banner{height:105px;width:100%;background-size:cover;background-position:center;position:relative;background-color:var(--blue,#251078)}
.artist-banner:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(20,8,70,.01),rgba(20,8,70,.10));pointer-events:none}
.artist-banner--default{background:linear-gradient(135deg,var(--blue,#251078) 0%,var(--blue-600,#3020a0) 55%,#5B4BDB 100%)}
.artist-profile-head{gap:16px;padding:16px 22px 18px}
.artist-profile-head h1{font-size:clamp(1.45rem,2.6vw,2rem)}
.artist-profile-head{display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin:0!important;padding:16px 22px 18px}
.artist-profile-head>img,.artist-profile-head>.avatar{flex:none;box-shadow:0 5px 18px rgba(25,10,80,.14)}
.artist-profile-head h1{font-size:clamp(1.25rem,2vw,1.7rem);line-height:1.2;overflow-wrap:anywhere}
.artist-profile-head .btn{white-space:nowrap}
.artist-about-section{padding:22px 26px;margin:0 0 30px;border-radius:18px;background:var(--white);border:1px solid var(--grey-300)}
.artist-about-section h3,.artist-content-section h3{margin:0 0 12px}
.artist-about-section p{line-height:1.75;margin-bottom:0;overflow-wrap:anywhere}
.artist-content-section{margin-top:10px}
.artist-section-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:28px 0 12px}
.artist-section-heading h3{margin:0}
.artist-section-heading>span{font-size:.88rem;color:var(--grey-600);background:var(--grey-100);border-radius:999px;padding:6px 12px}
.artist-reviews-heading{margin-top:34px}
.artist-empty-state{padding:18px;border:1px dashed var(--grey-300);border-radius:14px;text-align:center}
@media(max-width:640px){.artist-profile-head{padding:14px 16px;gap:12px}.artist-profile-head>div[style*="flex:1"]{min-width:100%}.artist-profile-head>div[style*="display:flex"]{width:100%}.artist-profile-head .btn{flex:1;text-align:center}.artist-about-section{padding:18px}.artist-banner{height:90px}}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);display:none;align-items:center;justify-content:center;z-index:100;padding:16px}
.modal-overlay.show{display:flex}
.modal{background:var(--white,#fff);color:var(--black);border-radius:18px;padding:22px;width:100%;max-width:480px;max-height:90vh;overflow:auto}
.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.modal-close{border:none;background:none;font-size:1.2rem;cursor:pointer}
</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
