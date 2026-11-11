<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
if (is_admin($user)) { header('Location: admin.php'); exit; }
$perfil = ensure_perfil((int)$user['id_usuario']);
$tab = $_GET['tab'] ?? 'visao';
if (in_array($tab, ['publicar','editar'], true)) {
    $idQ = isset($_GET['id']) ? ('?id='.(int)$_GET['id']) : '';
    header('Location: criar_projeto.php'.$idQ);
    exit;
}

$erro = '';

// Abas antigas (quando existia artista/cliente) continuam funcionando
$tabAlias = ['portfolio' => 'publicar', 'projetos' => 'publicar', 'briefing' => 'publicar',
             'editar_portfolio' => 'editar', 'editar_briefing' => 'editar'];
if (isset($tabAlias[$tab])) $tab = $tabAlias[$tab];

// Mensagem de sucesso guardada na sessão (evita reenvio do formulário ao atualizar a página)
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
function go(string $url, string $msg = ''): void {
    if ($msg !== '') $_SESSION['flash'] = $msg;
    header('Location: ' . $url);
    exit;
}

/** Envia várias imagens de um campo <input type="file" name="x[]" multiple> e devolve os caminhos salvos. */
function upload_many(string $field): array {
    $keys = [];
    if (empty($_FILES[$field]['name']) || !is_array($_FILES[$field]['name'])) return $keys;
    foreach ($_FILES[$field]['name'] as $i => $n) {
        if (!$n) continue;
        $key = upload_image([
            'name' => $_FILES[$field]['name'][$i],
            'type' => $_FILES[$field]['type'][$i],
            'tmp_name' => $_FILES[$field]['tmp_name'][$i],
            'error' => $_FILES[$field]['error'][$i],
            'size' => $_FILES[$field]['size'][$i],
        ]);
        if ($key) $keys[] = $key;
    }
    return $keys;
}

/** Recria a tabela imagens_projeto com base nos blocos de conteúdo. */
function sync_imagens(int $pid, array $conteudo): void {
    db()->prepare('DELETE FROM imagens_projeto WHERE id_projeto=?')->execute([$pid]);
    foreach ($conteudo as $i => $b) {
        if (($b['type'] ?? '') === 'image' && !empty($b['key'])) {
            db()->prepare('INSERT INTO imagens_projeto (id_projeto,storage_key,ordem) VALUES (?,?,?)')
                ->execute([$pid, $b['key'], $i]);
        }
    }
}

/** Normaliza @instagram e site (adiciona https:// se faltar). */
function clean_instagram(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    $v = preg_replace('~^https?://(www\.)?instagram\.com/~i', '', $v);
    $v = trim($v, "/@ \t");
    return $v === '' ? '' : '@' . mb_substr($v, 0, 60);
}
function clean_site(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    if (!preg_match('~^https?://~i', $v)) $v = 'https://' . $v;
    return filter_var($v, FILTER_VALIDATE_URL) ? mb_substr($v, 0, 150) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    /* ---------- Perfil ---------- */
    if ($acao === 'perfil') {
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            $erro = 'Informe seu nome.';
            $tab = 'perfil';
        } else {
            $foto = $user['foto_perfil'];
            if (!empty($_FILES['foto']['name'])) {
                $up = upload_image($_FILES['foto']);
                if ($up) $foto = $up; else $erro = 'Não foi possível enviar a foto (use JPG, PNG, WEBP ou GIF de até 8 MB).';
            }
            $banner = $perfil['banner'] ?? null;
            if (!empty($_FILES['banner']['name'])) {
                $upB = upload_image($_FILES['banner']);
                if ($upB) $banner = $upB; else $erro = $erro ?: 'Não foi possível enviar o banner (use JPG, PNG, WEBP ou GIF de até 8 MB).';
            }
            if (!empty($_POST['remover_banner'])) {
                $banner = null;
            }
            $tel = trim($_POST['telefone'] ?? '');
            db()->prepare('UPDATE usuarios SET nome=?, telefone=?, foto_perfil=? WHERE id_usuario=?')
                ->execute([mb_substr($nome, 0, 100), $tel ?: null, $foto, $user['id_usuario']]);
        try { $df = !empty($_POST['dois_fatores']) ? 1 : 0; db()->prepare('UPDATE usuarios SET dois_fatores=? WHERE id_usuario=?')->execute([$df, $user['id_usuario']]); } catch (Throwable $e) {}
            try {
                db()->prepare('UPDATE perfis_artistas SET bio=?, localizacao=?, instagram=?, site=?, banner=? WHERE id_perfil=?')
                    ->execute([
                        mb_substr(trim($_POST['bio'] ?? ''), 0, 500),
                        mb_substr(trim($_POST['cidade'] ?? ''), 0, 120),
                        clean_instagram($_POST['instagram'] ?? ''),
                        clean_site($_POST['site'] ?? ''),
                        $banner,
                        $perfil['id_perfil'],
                    ]);
            } catch (Throwable $e) {
                db()->prepare('UPDATE perfis_artistas SET bio=?, localizacao=?, instagram=?, site=? WHERE id_perfil=?')
                    ->execute([
                        mb_substr(trim($_POST['bio'] ?? ''), 0, 500),
                        mb_substr(trim($_POST['cidade'] ?? ''), 0, 120),
                        clean_instagram($_POST['instagram'] ?? ''),
                        clean_site($_POST['site'] ?? ''),
                        $perfil['id_perfil'],
                    ]);
            }
            if ($erro === '') go('dashboard.php?tab=visao', 'Perfil atualizado com sucesso!');
            $tab = 'perfil';
            $perfil = perfil_artista((int)$user['id_usuario']);
        }
    }

    /* ---------- Publicar ---------- */
    if ($acao === 'publicar') {
        $titulo = trim($_POST['titulo'] ?? '');
        $desc = trim($_POST['descricao'] ?? '');
        $id_cat = (int)($_POST['id_categoria'] ?? 0) ?: null;
        $procura = !empty($_POST['procura']);
        $min = ($_POST['min'] ?? '') !== '' ? (float)$_POST['min'] : null;
        $max = ($_POST['max'] ?? '') !== '' ? (float)$_POST['max'] : null;
        if ($titulo === '' || $desc === '') {
            $erro = 'Preencha o título e o resumo.';
        } elseif ($procura && $min !== null && $max !== null && $max < $min) {
            $erro = 'O orçamento máximo não pode ser menor que o mínimo.';
        }
        if ($erro !== '') {
            $tab = 'publicar';
        } else {
            $conteudo = [];
            foreach (($_POST['bloco_texto'] ?? []) as $tx) {
                $tx = trim($tx);
                if ($tx !== '') $conteudo[] = ['type' => 'text', 'content' => $tx];
            }
            foreach (upload_many('bloco_img') as $key) $conteudo[] = ['type' => 'image', 'key' => $key];
            $capa = !empty($_FILES['capa']['name']) ? upload_image($_FILES['capa']) : null;
            db()->prepare('INSERT INTO projetos (id_usuario,id_categoria,tipo,titulo,descricao,conteudo,imagem_url,orcamento_min,orcamento_max,status,visivel) VALUES (?,?,?,?,?,?,?,?,?,?,1)')
                ->execute([
                    $user['id_usuario'], $id_cat, $procura ? 'briefing' : 'portfolio', $titulo, $desc,
                    json_encode($conteudo, JSON_UNESCAPED_UNICODE), $capa,
                    $procura ? $min : null, $procura ? $max : null, $procura ? 'aberto' : 'concluido',
                ]);
            sync_imagens((int)db()->lastInsertId(), $conteudo);
            go('dashboard.php?tab=visao', 'Publicação criada!');
        }
    }

    /* ---------- Editar ---------- */
    if ($acao === 'editar') {
        $pid = (int)($_POST['id_projeto'] ?? 0);
        $st = db()->prepare('SELECT * FROM projetos WHERE id_projeto=? AND id_usuario=? LIMIT 1');
        $st->execute([$pid, $user['id_usuario']]);
        $projeto = $st->fetch();
        if (!$projeto) {
            go('dashboard.php?tab=visao', 'Publicação não encontrada.');
        }
        $titulo = trim($_POST['titulo'] ?? '');
        $desc = trim($_POST['descricao'] ?? '');
        $id_cat = (int)($_POST['id_categoria'] ?? 0) ?: null;
        $procura = !empty($_POST['procura']);
        $min = ($_POST['min'] ?? '') !== '' ? (float)$_POST['min'] : null;
        $max = ($_POST['max'] ?? '') !== '' ? (float)$_POST['max'] : null;
        if ($titulo === '' || $desc === '') {
            $erro = 'Preencha o título e o resumo.';
        } elseif ($procura && $min !== null && $max !== null && $max < $min) {
            $erro = 'O orçamento máximo não pode ser menor que o mínimo.';
        }
        if ($erro !== '') {
            $tab = 'editar';
            $_GET['id'] = $pid;
        } else {
            $conteudo = [];
            foreach (($_POST['bloco_texto'] ?? []) as $tx) {
                $tx = trim($tx);
                if ($tx !== '') $conteudo[] = ['type' => 'text', 'content' => $tx];
            }
            $remover = (array)($_POST['remover_img'] ?? []);
            $existente = json_decode($projeto['conteudo'] ?? '', true);
            if (is_array($existente)) {
                foreach ($existente as $b) {
                    if (($b['type'] ?? '') === 'image' && !empty($b['key']) && !in_array($b['key'], $remover, true)) {
                        $conteudo[] = ['type' => 'image', 'key' => $b['key']];
                    }
                }
            }
            foreach (upload_many('bloco_img') as $key) $conteudo[] = ['type' => 'image', 'key' => $key];
            $capa = $projeto['imagem_url'];
            if (!empty($_FILES['capa']['name'])) {
                $nova = upload_image($_FILES['capa']);
                if ($nova) $capa = $nova;
            }
            $tipo = $procura ? 'briefing' : 'portfolio';
            $status = $procura ? ($projeto['tipo'] === 'briefing' ? $projeto['status'] : 'aberto') : 'concluido';
            db()->prepare('UPDATE projetos SET id_categoria=?, tipo=?, titulo=?, descricao=?, conteudo=?, imagem_url=?, orcamento_min=?, orcamento_max=?, status=? WHERE id_projeto=? AND id_usuario=?')
                ->execute([$id_cat, $tipo, $titulo, $desc, json_encode($conteudo, JSON_UNESCAPED_UNICODE), $capa,
                           $procura ? $min : null, $procura ? $max : null, $status, $pid, $user['id_usuario']]);
            sync_imagens($pid, $conteudo);
            go('dashboard.php?tab=visao', 'Publicação atualizada com sucesso!');
        }
    }

    /* ---------- Excluir ---------- */
    if ($acao === 'excluir') {
        $pid = (int)($_POST['id_projeto'] ?? 0);
        db()->prepare('DELETE FROM projetos WHERE id_projeto=? AND id_usuario=?')->execute([$pid, $user['id_usuario']]);
        go('dashboard.php?tab=visao', 'Publicação removida.');
    }
}

$cats = db()->query('SELECT * FROM categorias ORDER BY nome')->fetchAll();
$st = db()->prepare("SELECT pr.*, c.nome AS cat_nome,
    COALESCE(NULLIF(pr.imagem_url,''),(SELECT ip.storage_key FROM imagens_projeto ip WHERE ip.id_projeto=pr.id_projeto ORDER BY ip.ordem,ip.id_imagem LIMIT 1)) AS capa
    FROM projetos pr LEFT JOIN categorias c ON c.id_categoria=pr.id_categoria
    WHERE pr.id_usuario=? ORDER BY pr.data_criacao DESC");
$st->execute([$user['id_usuario']]);
$minhas = $st->fetchAll();
$qtd_trabalhos = count(array_filter($minhas, fn($p) => $p['tipo'] === 'portfolio' && (int)$p['visivel'] === 1));
$qtd_pedidos = count(array_filter($minhas, fn($p) => $p['tipo'] === 'briefing' && (int)$p['visivel'] === 1));

$curtidas_recebidas = 0;
try {
    $st = db()->prepare('SELECT COUNT(*) FROM curtidas_projeto cp JOIN projetos pr ON pr.id_projeto=cp.id_projeto WHERE pr.id_usuario=?');
    $st->execute([$user['id_usuario']]);
    $curtidas_recebidas = (int)$st->fetchColumn();
} catch (Throwable $e) {}

$avisos_ativos = [];
try {
    $avisos_ativos = db()->query("SELECT * FROM avisos WHERE ativo=1 ORDER BY data_criacao DESC LIMIT 5")->fetchAll();
} catch (Throwable $e) {}

// Valor de campo: reaproveita o que a pessoa digitou se houve erro
function old(string $k, $default = '') { return $_POST[$k] ?? $default; }

$membro_desde = !empty($user['data_cadastro']) ? date('m/Y', strtotime($user['data_cadastro'])) : '—';
$perfil_incompleto = trim($perfil['bio'] ?? '') === '' || trim($perfil['localizacao'] ?? '') === '' || empty($user['foto_perfil']);

$page_title = 'Meu perfil — OctoArt';
$active = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap dash-page">
  <?php if ($flash): ?><div class="success-msg"><?= h($flash) ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="error-msg show"><?= h($erro) ?></div><?php endif; ?>

  <?php if ($tab === 'visao'): ?>
  <?php if (!empty($avisos_ativos)): ?>
  <div class="admin-avisos-banner">
    <h3 style="margin:0 0 10px;font-size:1rem"> Avisos da administração</h3>
    <?php foreach ($avisos_ativos as $av): ?>
      <div class="aviso-item">
        <strong><?= h($av['titulo']) ?></strong>
        <p style="margin:.3rem 0 0;font-size:.9rem"><?= nl2br(h($av['mensagem'])) ?></p>
        <span class="muted" style="font-size:.78rem"><?= fmt_date($av['data_criacao']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ========== PERFIL ========== -->
  <section class="me-profile">
    <?php if (!empty($perfil['banner'])): ?>
      <div class="me-cover me-cover--banner" style="background-image:url('<?= h(media_url($perfil['banner'])) ?>')"></div>
    <?php else: ?>
      <div class="me-cover"></div>
    <?php endif; ?>
    <div class="me-card">
      <div class="me-top">
        <div class="me-avatar-wrap">
          <?php if (!empty($user['foto_perfil'])): ?>
            <img class="me-avatar" src="<?= h(media_url($user['foto_perfil'])) ?>" alt="<?= h($user['nome']) ?>">
          <?php else: ?>
            <div class="me-avatar me-avatar-fallback"><?= h(initials($user['nome'])) ?></div>
          <?php endif; ?>
        </div>
        <div class="me-info">
          <div class="me-name-row">
            <h1 class="me-name"><?= h($user['nome']) ?></h1>
            <span class="me-badge">Membro desde <?= h($membro_desde) ?></span>
          </div>
          <p class="me-meta">
            <?php if (!empty($perfil['localizacao'])): ?><span>📍 <?= h($perfil['localizacao']) ?></span><?php else: ?><a href="dashboard.php?tab=perfil" class="muted">📍 Adicionar cidade</a><?php endif; ?>
            <?php if (!empty($perfil['instagram'])): ?><span> · <a href="https://instagram.com/<?= h(ltrim($perfil['instagram'], '@')) ?>" target="_blank" rel="noopener"><?= h($perfil['instagram']) ?></a></span><?php endif; ?>
            <?php if (!empty($perfil['site'])): ?><span> · <a href="<?= h($perfil['site']) ?>" target="_blank" rel="noopener"><?= h(preg_replace('~^https?://(www\.)?~', '', $perfil['site'])) ?></a></span><?php endif; ?>
          </p>
          <p class="me-bio">
            <?php $bio = trim($perfil['bio'] ?? ''); echo $bio !== '' ? nl2br(h($bio)) : '<span class="muted">Você ainda não escreveu uma bio. Clique em Editar perfil e conte um pouco sobre você.</span>'; ?>
          </p>
          <p class="me-private muted"> Só você vê: <?= h($user['email']) ?><?php if (!empty($user['telefone'])): ?> · <?= h($user['telefone']) ?><?php endif; ?></p>
          <div class="me-actions">
            <a href="dashboard.php?tab=perfil" class="btn btn-primary"> Editar perfil</a>
            <a href="artista.php?id=<?= (int)$perfil['id_perfil'] ?>" class="btn btn-outline">Ver perfil público</a>
            <a href="mensagens.php" class="btn btn-ghost"> Mensagens</a>
          </div>
        </div>
      </div>

      <div class="me-stats">
        <div class="me-stat"><strong><?= $qtd_trabalhos ?></strong><span>Trabalhos</span></div>
        <div class="me-stat"><strong><?= $qtd_pedidos ?></strong><span>Pedidos abertos</span></div>
        <div class="me-stat"><strong><?= $curtidas_recebidas ?></strong><span>Curtidas</span></div>
      </div>
    </div>
  </section>

  <?php if ($perfil_incompleto): ?>
  <div class="me-tip">
    <strong> Complete seu perfil</strong>
    <span>Perfis com foto, cidade e bio passam mais confiança e recebem mais contatos.</span>
    <a href="dashboard.php?tab=perfil" class="btn btn-outline btn-sm">Completar agora</a>
  </div>
  <?php endif; ?>

  <section class="me-section">
    <div class="me-section-head">
      <h2>Minhas publicações</h2>
      <a href="criar_projeto.php" class="btn btn-primary btn-sm">+ Nova publicação</a>
    </div>
    <?php if ($minhas): ?>
      <div class="grid grid-3">
        <?php foreach ($minhas as $p): ?>
          <div class="card-project me-project-card">
            <?php if (!empty($p['capa'])): ?>
              <img src="<?= h(media_url($p['capa'])) ?>" alt="" class="me-project-cover">
            <?php else: ?>
              <div class="me-project-cover me-project-placeholder"><?= $p['tipo']==='portfolio' ? '🎨' : '📌' ?></div>
            <?php endif; ?>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px">
              <span class="tag"><?= h($p['cat_nome'] ?: 'Geral') ?></span>
              <?php if ($p['tipo']==='briefing'): ?><span class="status-pill status-aberto">Procurando alguém</span><?php endif; ?>
              <?php if (!(int)$p['visivel']): ?><span class="status-pill status-cancelado"> Removida pela moderação</span><?php endif; ?>
            </div>
            <h4><?= h($p['titulo']) ?></h4>
            <p class="muted" style="font-size:.88rem;margin:0"><?= h(mb_substr($p['descricao'] ?? '', 0, 90)) ?><?= mb_strlen($p['descricao'] ?? '') > 90 ? '…' : '' ?></p>
            <?php if ($p['tipo']==='briefing' && ($p['orcamento_min'] !== null || $p['orcamento_max'] !== null)): ?>
              <span class="budget"><?= money($p['orcamento_min']) ?> – <?= money($p['orcamento_max']) ?></span>
            <?php endif; ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
              <a class="btn btn-ghost btn-sm" href="projeto.php?id=<?= (int)$p['id_projeto'] ?>">Ver</a>
              <a class="btn btn-outline btn-sm" href="criar_projeto.php?id=<?= (int)$p['id_projeto'] ?>"> Editar</a>
              <form method="post" onsubmit="return confirm('Remover esta publicação?')" style="margin:0">
                <input type="hidden" name="acao" value="excluir">
                <input type="hidden" name="id_projeto" value="<?= (int)$p['id_projeto'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit">Excluir</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="me-empty">
        <p>Você ainda não publicou nada.</p>
        <a href="criar_projeto.php" class="btn btn-primary btn-sm">Criar primeira publicação</a>
      </div>
    <?php endif; ?>
  </section>

  <?php elseif ($tab === 'perfil'): ?>
  <!-- ========== EDITAR PERFIL ========== -->
  <div class="me-edit-wrap">
    <div class="me-edit-head">
      <a href="dashboard.php?tab=visao" class="btn btn-ghost btn-sm">← Voltar ao perfil</a>
      <h2 style="margin:0">Editar perfil</h2>
    </div>
    <form method="post" enctype="multipart/form-data" class="me-edit-form">
      <input type="hidden" name="acao" value="perfil">
      <input type="hidden" name="remover_banner" id="removerBanner" value="">

      <div class="field me-banner-field">
        <label>Banner do perfil <span class="muted">(clique na imagem para trocar)</span></label>
        <div class="me-banner-preview-wrap">
          <label class="me-banner-click" for="bannerInput" title="Clique para escolher o banner">
            <?php if (!empty($perfil['banner'])): ?>
              <div class="me-banner-preview" id="bannerPreview" style="background-image:url('<?= h(media_url($perfil['banner'])) ?>')">
                <span class="me-banner-hint"> Clique para alterar</span>
              </div>
            <?php else: ?>
              <div class="me-banner-preview me-banner-preview--empty" id="bannerPreview">
                <span> Clique para escolher o banner</span>
              </div>
            <?php endif; ?>
            <input type="file" name="banner" id="bannerInput" accept="image/*" style="display:none" onchange="previewBanner(this)">
          </label>
          <button type="button" class="me-banner-remove<?= empty($perfil['banner']) ? ' is-hidden' : '' ?>" id="btnRemoverBanner" onclick="removerBannerClick()" title="Remover banner">✕ Remover</button>
        </div>
        <p class="muted" style="font-size:.8rem;margin:.4rem 0 0">Recomendado: imagem larga (ex.: 1500×500). JPG, PNG ou WEBP · até 8 MB</p>
      </div>

      <div class="me-edit-photo">
        <label class="me-photo-click" for="fotoInput" title="Clique para trocar a foto">
          <?php if (!empty($user['foto_perfil'])): ?>
            <img src="<?= h(media_url($user['foto_perfil'])) ?>" alt="" id="editPreview">
          <?php else: ?>
            <div class="me-avatar me-avatar-fallback" id="editPreviewFallback"><?= h(initials($user['nome'])) ?></div>
          <?php endif; ?>
          <span class="me-photo-hint">Trocar foto</span>
        </label>
        <input type="file" name="foto" id="fotoInput" accept="image/*" style="display:none" onchange="previewFoto(this)">
        <p class="muted" style="font-size:.8rem;margin:.4rem 0 0;text-align:center">JPG, PNG ou WEBP · até 8 MB</p>
      </div>

      <div class="field"><label>Nome</label><input type="text" name="nome" maxlength="100" value="<?= h(old('nome', $user['nome'])) ?>" required></div>
      <div class="field-row">
        <div class="field"><label>Cidade</label><input type="text" name="cidade" data-city maxlength="120" value="<?= h(old('cidade', $perfil['localizacao'] ?? '')) ?>" placeholder="Ex: Araranguá, SC"></div>
        <div class="field"><label>Telefone <span class="muted">(privado)</span></label><input type="tel" name="telefone" value="<?= h(old('telefone', $user['telefone'] ?? '')) ?>" placeholder="(48) 984490605" inputmode="numeric" autocomplete="tel" maxlength="15"></div>
      </div>
      <div class="field">
        <label>Bio <span class="muted" id="bioCount"></span></label>
        <textarea name="bio" id="bioField" rows="4" maxlength="500" placeholder="Conte quem você é, o que gosta de criar ou que tipo de projeto procura..."><?= h(old('bio', $perfil['bio'] ?? '')) ?></textarea>
      </div>
      <div class="field-row">
        <div class="field"><label>Instagram</label><input type="text" name="instagram" value="<?= h(old('instagram', $perfil['instagram'] ?? '')) ?>" placeholder="@seuuser"></div>
        <div class="field"><label>Site / portfólio</label><input type="text" name="site" value="<?= h(old('site', $perfil['site'] ?? '')) ?>" placeholder="seusite.com"></div>
      </div>
      <div class="me-edit-actions">
        <div class="field" style="margin-top:12px">
          <label style="display:flex;align-items:center;gap:8px;font-weight:600">
            <input type="checkbox" name="dois_fatores" value="1" <?= !empty($user['dois_fatores'])?'checked':'' ?>>
            Verificação em 2 etapas (código no e-mail a cada login)
          </label>
          <p class="muted" style="font-size:.82rem;margin:.3rem 0 0">Opcional. Você receberá um código por e-mail ao entrar.</p>
        </div>
        <button class="btn btn-primary" type="submit">Salvar alterações</button>
        <a href="dashboard.php?tab=visao" class="btn btn-ghost">Cancelar</a>
      </div>
    </form>
    <section class="me-delete-account" aria-labelledby="deleteAccountTitle">
      <div class="me-delete-heading">
        <div>
          <h3 id="deleteAccountTitle">Excluir conta</h3>
          <p>Se você excluir sua conta, seus dados e publicações associados serão removidos. Essa ação não pode ser desfeita.</p>
        </div>
      </div>
      <form method="post" action="excluir_conta.php" class="me-delete-form" onsubmit="return confirm('Tem certeza? A exclusão da conta é permanente e não pode ser desfeita.');">
        <div class="field">
          <label for="senhaConfirmacaoExcluir">Sua senha</label>
          <input type="password" id="senhaConfirmacaoExcluir" name="senha_confirmacao" autocomplete="current-password" placeholder="Digite sua senha" required>
        </div>
        <button class="btn me-delete-button" type="submit">Excluir minha conta</button>
      </form>
    </section>
  </div>
  <script>
  function previewFoto(input){
    if(!input.files||!input.files[0]) return;
    const url = URL.createObjectURL(input.files[0]);
    const img = document.getElementById('editPreview');
    const fb = document.getElementById('editPreviewFallback');
    if(fb){ fb.outerHTML = '<img src="'+url+'" alt="" id="editPreview">'; return; }
    if(img) img.src = url;
  }
  function previewBanner(input){
    if(!input.files||!input.files[0]) return;
    const url = URL.createObjectURL(input.files[0]);
    const el = document.getElementById('bannerPreview');
    const rm = document.getElementById('removerBanner');
    const btn = document.getElementById('btnRemoverBanner');
    if(el){
      el.classList.remove('me-banner-preview--empty');
      el.style.backgroundImage = 'url('+url+')';
      el.innerHTML = '<span class="me-banner-hint">Clique para alterar</span>';
    }
    if(rm) rm.value = '';
    if(btn) btn.classList.remove('is-hidden');
  }
  function removerBannerClick(){
    const el = document.getElementById('bannerPreview');
    const input = document.getElementById('bannerInput');
    const rm = document.getElementById('removerBanner');
    const btn = document.getElementById('btnRemoverBanner');
    if(el){
      el.classList.add('me-banner-preview--empty');
      el.style.backgroundImage = '';
      el.innerHTML = '<span>Clique para escolher o banner</span>';
    }
    if(input) input.value = '';
    if(rm) rm.value = '1';
    if(btn) btn.classList.add('is-hidden');
  }
  (function(){
    const f = document.getElementById('bioField'), c = document.getElementById('bioCount');
    if(f && c){
      const upd = () => c.textContent = '(' + f.value.length + '/500)';
      f.addEventListener('input', upd); upd();
    }
  })();
  </script>

  <?php elseif ($tab === 'publicar' || $tab === 'editar'): ?>
  <?php
    $editando = $tab === 'editar';
    $ep = null; $imgsAtuais = []; $textos = [''];
    if ($editando) {
        $edit_pid = (int)($_GET['id'] ?? 0);
        $stEdit = db()->prepare('SELECT * FROM projetos WHERE id_projeto=? AND id_usuario=? LIMIT 1');
        $stEdit->execute([$edit_pid, $user['id_usuario']]);
        $ep = $stEdit->fetch();
        if ($ep) {
            $cont = json_decode($ep['conteudo'] ?? '', true);
            $textos = [];
            if (is_array($cont)) foreach ($cont as $b) {
                if (($b['type'] ?? '') === 'text') $textos[] = $b['content'] ?? '';
                if (($b['type'] ?? '') === 'image' && !empty($b['key'])) $imgsAtuais[] = $b['key'];
            }
            if (!$textos) $textos = [''];
        }
    }
    $procuraMarcado = $_SERVER['REQUEST_METHOD'] === 'POST' ? !empty($_POST['procura']) : ($ep && $ep['tipo'] === 'briefing');
  ?>
  <?php if ($editando && !$ep): ?>
    <div class="me-empty"><p>Publicação não encontrada.</p><a href="dashboard.php?tab=visao" class="btn btn-primary">Voltar</a></div>
  <?php else: ?>
  <div class="me-section">
    <div class="me-section-head">
      <div>
        <a href="dashboard.php?tab=visao" class="btn btn-ghost btn-sm">← Voltar</a>
        <h2 style="margin:.5rem 0 0"><?= $editando ? 'Editar publicação' : 'Nova publicação' ?></h2>
      </div>
    </div>
    <form method="post" enctype="multipart/form-data" class="me-edit-form">
      <input type="hidden" name="acao" value="<?= $editando ? 'editar' : 'publicar' ?>">
      <?php if ($editando): ?><input type="hidden" name="id_projeto" value="<?= (int)$ep['id_projeto'] ?>"><?php endif; ?>

      <div class="field"><label>Categoria</label>
        <select name="id_categoria" required>
          <?php $catSel = (int)old('id_categoria', $ep['id_categoria'] ?? 0); foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id_categoria'] ?>" <?= $catSel===(int)$c['id_categoria']?'selected':'' ?>><?= h($c['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Título</label><input type="text" name="titulo" maxlength="150" required value="<?= h(old('titulo', $ep['titulo'] ?? '')) ?>" placeholder="Nome da publicação"></div>
      <div class="field"><label>Resumo</label><textarea name="descricao" required placeholder="Descreva em poucas linhas"><?= h(old('descricao', $ep['descricao'] ?? '')) ?></textarea></div>
      <div class="field"><label>Imagem de capa<?= $editando ? ' <span class="muted">(deixe vazio para manter a atual)</span>' : '' ?></label><input type="file" name="capa" accept="image/*"></div>

      <div class="field">
        <label>Texto <span class="muted">(opcional — processo, detalhes, o que você precisa...)</span></label>
        <?php foreach ($textos as $tx): ?><textarea name="bloco_texto[]" placeholder="Escreva aqui..."><?= h($tx) ?></textarea><?php endforeach; ?>
        <textarea name="bloco_texto[]" placeholder="Outro bloco (opcional)" style="margin-top:8px"></textarea>
      </div>

      <?php if ($imgsAtuais): ?>
      <div class="field">
        <label>Imagens atuais <span class="muted">(marque para remover)</span></label>
        <div class="img-remove-grid">
          <?php foreach ($imgsAtuais as $k): ?>
            <label class="img-remove"><img src="<?= h($k) ?>" alt=""><span><input type="checkbox" name="remover_img[]" value="<?= h($k) ?>"> Remover</span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="field"><label><?= $editando ? 'Adicionar mais imagens' : 'Mais imagens' ?> <span class="muted">(opcional)</span></label><input type="file" name="bloco_img[]" accept="image/*" multiple></div>

      <div class="field procura-box">
        <label class="check-line"><input type="checkbox" name="procura" value="1" id="procuraChk" <?= $procuraMarcado ? 'checked' : '' ?>> <span><strong>Estou procurando alguém para fazer este projeto</strong><br><small class="muted">Marque se quiser receber propostas de outras pessoas.</small></span></label>
        <div id="orcBox" style="display:<?= $procuraMarcado ? 'block' : 'none' ?>;margin-top:12px">
          <div class="field-row">
            <div class="field"><label>Orçamento mín. (R$)</label><input type="number" name="min" min="0" step="0.01" value="<?= h(old('min', $ep['orcamento_min'] ?? '')) ?>"></div>
            <div class="field"><label>Orçamento máx. (R$)</label><input type="number" name="max" min="0" step="0.01" value="<?= h(old('max', $ep['orcamento_max'] ?? '')) ?>"></div>
          </div>
        </div>
      </div>

      <button class="btn btn-primary" type="submit"><?= $editando ? 'Salvar alterações' : 'Publicar' ?></button>
    </form>
  </div>
  <script>
  document.getElementById('procuraChk').addEventListener('change', function(){
    document.getElementById('orcBox').style.display = this.checked ? 'block' : 'none';
  });
  </script>
  <?php endif; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
