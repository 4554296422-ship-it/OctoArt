<?php
$tipo = ($_GET['tipo'] ?? 'artistas') === 'projetos' ? 'projetos' : 'artistas';
$cat = isset($_GET['categoria']) ? (int)$_GET['categoria'] : 0;
$q = trim($_GET['q'] ?? '');
$page_title = $tipo === 'artistas' ? 'Comunidade — OctoArt' : 'Projetos — OctoArt';
$active = $tipo === 'artistas' ? 'artistas' : 'projetos';
require __DIR__ . '/includes/header.php';
sync_perfis(); // garante que TODA conta (inclusive as novas) tenha perfil e apareça aqui

$cats = db()->query('SELECT * FROM categorias ORDER BY nome')->fetchAll();

if ($tipo === 'artistas') {
    $sql = "SELECT p.id_perfil, p.localizacao, p.bio, p.banner,
                   u.nome, u.foto_perfil,
                   (SELECT COUNT(*) FROM projetos pr2 WHERE pr2.id_usuario=p.id_usuario AND pr2.visivel=1) AS obras,
                   (SELECT AVG(nota) FROM avaliacoes a WHERE a.id_avaliado=p.id_perfil) AS media
            FROM perfis_artistas p
            JOIN usuarios u ON u.id_usuario=p.id_usuario
            WHERE u.status='ativo' AND u.tipo!='admin'";
    $params = [];
    if ($cat) {
        $sql .= " AND EXISTS (
          SELECT 1 FROM projetos pr WHERE pr.id_usuario=p.id_usuario AND pr.tipo='portfolio' AND pr.id_categoria=?
        )";
        $params[] = $cat;
    }
    if ($q !== '') {
        $sql .= " AND (u.nome LIKE ? OR p.bio LIKE ? OR p.localizacao LIKE ?)";
        $like = '%'.$q.'%';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $sql .= " ORDER BY obras DESC, media DESC, u.nome";
    $st = db()->prepare($sql);
    $st->execute($params);
    $items = $st->fetchAll();
} else {
    $sql = "SELECT pr.*, c.nome AS cat_nome,
            COALESCE(NULLIF(pr.imagem_url,''),(SELECT ip.storage_key FROM imagens_projeto ip WHERE ip.id_projeto=pr.id_projeto ORDER BY ip.ordem,ip.id_imagem LIMIT 1)) AS capa
            FROM projetos pr
            LEFT JOIN categorias c ON c.id_categoria=pr.id_categoria
            WHERE pr.visivel=1 AND pr.status NOT IN ('cancelado','excluido')";
    $params = [];
    if ($cat) { $sql .= " AND pr.id_categoria=?"; $params[] = $cat; }
    if ($q !== '') {
        $sql .= " AND (pr.titulo LIKE ? OR pr.descricao LIKE ? OR EXISTS (SELECT 1 FROM usuarios ux WHERE ux.id_usuario=pr.id_usuario AND ux.nome LIKE ?))";
        $like = '%'.$q.'%'; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $sql .= " ORDER BY pr.data_criacao DESC";
    $st = db()->prepare($sql);
    $st->execute($params);
    $items = $st->fetchAll();
}
?>
<div class="wrap" style="padding:28px 24px 60px">
  <div class="page-head">
    <div class="crumb"><a href="index.php">Início</a> / <?= $tipo==='artistas'?'Comunidade':'Projetos' ?></div>
    <h1><?= $tipo==='artistas'?'Conheça a comunidade':'Explore projetos' ?></h1>
  </div>

  <div style="display:flex;gap:10px;margin:16px 0 22px;flex-wrap:wrap">
    <a class="btn btn-sm <?= $tipo==='artistas'?'btn-primary':'btn-outline' ?>" href="explorar.php?tipo=artistas">Comunidade</a>
    <a class="btn btn-sm <?= $tipo==='projetos'?'btn-primary':'btn-outline' ?>" href="explorar.php?tipo=projetos">Projetos</a>
  </div>

  <form method="get" class="search-bar" style="margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:end">
    <input type="hidden" name="tipo" value="<?= h($tipo) ?>">
    <div class="field" style="margin:0;flex:1;min-width:180px">
      <label>Buscar</label>
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="Nome, cidade, palavra-chave...">
    </div>
    <div class="field" style="margin:0;min-width:180px">
      <label>Categoria</label>
      <select name="categoria">
        <option value="0">Todas</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int)$c['id_categoria'] ?>" <?= $cat===(int)$c['id_categoria']?'selected':'' ?>><?= h($c['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">Filtrar</button>
  </form>

  <p class="muted" style="margin-bottom:14px"><?= count($items) ?> resultado(s)</p>

  <div class="grid grid-3">
    <?php if ($tipo === 'artistas'): ?>
      <?php foreach ($items as $a): ?>
        <a class="card-artist" href="artista.php?id=<?= (int)$a['id_perfil'] ?>">
          <?php if (!empty($a['banner'])): ?>
            <div class="cover cover--banner" style="background-image:url('<?= h(media_url($a['banner'])) ?>')"></div>
          <?php else: ?>
            <div class="cover"></div>
          <?php endif; ?>
          <div class="body">
            <?php if (!empty($a['foto_perfil'])): ?>
              <div class="avatar" style="background-image:url('<?= h(media_url($a['foto_perfil'])) ?>');background-size:cover;background-position:center;color:transparent"><?= h(initials($a['nome'])) ?></div>
            <?php else: ?>
              <div class="avatar"><?= h(initials($a['nome'])) ?></div>
            <?php endif; ?>
            <h4><?= h($a['nome']) ?></h4>
            <div class="loc">📍 <?= h($a['localizacao'] ?: 'Brasil') ?></div>
            <?php if (!empty($a['bio'])): ?><p class="muted" style="font-size:.85rem;margin:6px 0 0"><?= h(mb_substr($a['bio'],0,80)) ?><?= mb_strlen($a['bio'])>80?'…':'' ?></p><?php endif; ?>
            <div class="foot">
              <div class="rating">★ <?= $a['media'] ? number_format((float)$a['media'],1) : 'novo' ?></div>
              <span class="muted" style="font-size:.8rem"><?= (int)$a['obras'] ?> publicação(ões)</span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <?php foreach ($items as $p): ?>
        <a class="card-project card-project--cover" href="projeto.php?id=<?= (int)$p['id_projeto'] ?>">
          <?php if (!empty($p['capa'])): ?>
            <div class="card-project-cover">
              <img src="<?= h(media_url($p['capa'])) ?>" alt="<?= h($p['titulo']) ?>" loading="lazy">
            </div>
          <?php else: ?>
            <div class="card-project-cover card-project-cover--empty">
              <span><?= $p['tipo']==='portfolio' ? '🎨' : '📌' ?></span>
            </div>
          <?php endif; ?>
          <div class="card-project-body">
            <div class="row">
              <span class="tag"><?= h($p['cat_nome'] ?: 'Geral') ?></span>
              <span class="status-pill <?= $p['tipo']==='briefing' ? 'status-aberto' : 'status-concluido' ?>"><?= $p['tipo']==='briefing' ? 'Procurando alguém' : 'Trabalho' ?></span>
            </div>
            <h4><?= h($p['titulo']) ?></h4>
            <?php if (!empty($p['descricao'])): ?>
              <p class="muted card-project-desc"><?= h(mb_substr($p['descricao'],0,100)) ?><?= mb_strlen($p['descricao'])>100?'…':'' ?></p>
            <?php endif; ?>
            <?php if ($p['tipo']==='briefing'): ?>
              <span class="budget"><?= money($p['orcamento_min']) ?> – <?= money($p['orcamento_max']) ?></span>
            <?php else: ?>
              <span class="muted" style="font-size:.82rem">Trabalho publicado</span>
            <?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php if (!$items): ?><p class="muted center" style="padding:40px 0">Nada encontrado com esses filtros.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
