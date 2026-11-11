<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
if (is_admin($user)) { header('Location: admin.php'); exit; }

$editId = (int)($_GET['id'] ?? 0);
$proj = null;
if ($editId) {
    $st = db()->prepare('SELECT * FROM projetos WHERE id_projeto=? AND id_usuario=?');
    $st->execute([$editId, $user['id_usuario']]);
    $proj = $st->fetch();
    if (!$proj) { header('Location: dashboard.php'); exit; }
}

$cats = db()->query('SELECT * FROM categorias ORDER BY nome')->fetchAll();
$flash = $erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $desc = trim($_POST['descricao'] ?? '');
    $id_cat = (int)($_POST['id_categoria'] ?? 0) ?: null;
    $tipo = ($_POST['tipo'] ?? 'portfolio') === 'briefing' ? 'briefing' : 'portfolio';
    $blocksJson = $_POST['blocks_json'] ?? '[]';
    $blocks = json_decode($blocksJson, true);
    if (!is_array($blocks)) $blocks = [];
    $capa = $proj['imagem_url'] ?? null;
    if (!empty($_POST['capa_url'])) $capa = trim($_POST['capa_url']);

    if ($titulo === '') {
        $erro = 'Informe o título do projeto.';
    } else {
        $conteudo = json_encode($blocks, JSON_UNESCAPED_UNICODE);
        if ($proj) {
            db()->prepare('UPDATE projetos SET id_categoria=?, tipo=?, titulo=?, descricao=?, conteudo=?, imagem_url=? WHERE id_projeto=? AND id_usuario=?')
                ->execute([$id_cat, $tipo, $titulo, $desc, $conteudo, $capa, $editId, $user['id_usuario']]);
            $pid = $editId;
            $flash = 'Projeto atualizado!';
        } else {
            db()->prepare('INSERT INTO projetos (id_usuario,id_categoria,tipo,titulo,descricao,conteudo,imagem_url,status,visivel) VALUES (?,?,?,?,?,?,?,?,1)')
                ->execute([$user['id_usuario'], $id_cat, $tipo, $titulo, $desc, $conteudo, $capa, $tipo==='portfolio'?'concluido':'aberto']);
            $pid = (int)db()->lastInsertId();
            $flash = 'Projeto publicado!';
        }
        // reindex imagens
        try {
            db()->prepare('DELETE FROM imagens_projeto WHERE id_projeto=?')->execute([$pid]);
            $ord = 0;
            foreach ($blocks as $b) {
                if (($b['type'] ?? '') === 'image' && !empty($b['url'])) {
                    db()->prepare('INSERT INTO imagens_projeto (id_projeto,storage_key,ordem) VALUES (?,?,?)')->execute([$pid, $b['url'], $ord++]);
                }
                if (($b['type'] ?? '') === 'grid' && !empty($b['images'])) {
                    foreach ($b['images'] as $img) {
                        if ($img) db()->prepare('INSERT INTO imagens_projeto (id_projeto,storage_key,ordem) VALUES (?,?,?)')->execute([$pid, $img, $ord++]);
                    }
                }
            }
        } catch (Throwable $e) {}
        header('Location: projeto.php?id=' . $pid);
        exit;
    }
}

$initialBlocks = $proj ? (json_decode($proj['conteudo'] ?? '[]', true) ?: []) : [];
$page_title = ($proj ? 'Editar' : 'Criar') . ' projeto — OctoArt';
$active = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/css/editor.css">
<div class="editor-page">
  <div class="editor-topbar">
    <a href="dashboard.php" class="btn btn-ghost btn-sm">← Voltar</a>
    <h1><?= $proj ? 'Editar projeto' : 'Novo projeto' ?></h1>
    <button type="submit" form="projForm" class="btn btn-primary btn-sm">Publicar</button>
  </div>

  <?php if ($erro): ?><div class="error-msg show"><?= h($erro) ?></div><?php endif; ?>
  <?php if ($flash): ?><div class="success-msg"><?= h($flash) ?></div><?php endif; ?>

  <form method="post" id="projForm" class="editor-layout">
    <aside class="editor-meta">
      <div class="field">
        <label>Tipo</label>
        <select name="tipo">
          <option value="portfolio" <?= ($proj['tipo'] ?? '')!=='briefing'?'selected':'' ?>>Portfólio (meu trabalho)</option>
          <option value="briefing" <?= ($proj['tipo'] ?? '')==='briefing'?'selected':'' ?>>Pedido / briefing</option>
        </select>
      </div>
      <div class="field">
        <label>Categoria</label>
        <select name="id_categoria">
          <option value="0">Geral</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id_categoria'] ?>" <?= (int)($proj['id_categoria']??0)===(int)$c['id_categoria']?'selected':'' ?>><?= h($c['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Título</label>
        <input type="text" name="titulo" id="titulo" required value="<?= h($proj['titulo'] ?? '') ?>" placeholder="Nome do projeto">
      </div>
      <div class="field">
        <label>Resumo curto</label>
        <textarea name="descricao" rows="3" placeholder="Uma linha sobre o projeto"><?= h($proj['descricao'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label>Capa</label>
        <div class="capa-box" id="capaBox">
          <?php if (!empty($proj['imagem_url'])): ?>
            <img src="<?= h($proj['imagem_url']) ?>" id="capaPreview" alt="">
          <?php else: ?>
            <img src="" id="capaPreview" alt="" style="display:none">
            <span id="capaPlaceholder">Clique para enviar capa</span>
          <?php endif; ?>
        </div>
        <input type="hidden" name="capa_url" id="capaUrl" value="<?= h($proj['imagem_url'] ?? '') ?>">
        <input type="file" id="capaFile" accept="image/*" style="display:none">
      </div>
      <p class="muted" style="font-size:.85rem">Monte o conteúdo com blocos à direita: texto, foto ou grade (2, 3 ou 4 fotos na mesma fileira).</p>
    </aside>

    <main class="editor-canvas">
      <div class="block-toolbar sticky">
        <span class="muted" style="font-size:.85rem;margin-right:auto">Adicionar bloco:</span>
        <button type="button" class="btn btn-outline btn-sm" data-add="text"> Texto</button>
        <button type="button" class="btn btn-outline btn-sm" data-add="image"> Foto</button>
        <button type="button" class="btn btn-outline btn-sm" data-add="grid2"> 2 fotos</button>
        <button type="button" class="btn btn-outline btn-sm" data-add="grid3"> 3 fotos</button>
        <button type="button" class="btn btn-outline btn-sm" data-add="grid4"> 4 fotos</button>
      </div>
      <div id="blocks" class="blocks-list"></div>
      <input type="hidden" name="blocks_json" id="blocksJson">
      <div class="editor-actions-bottom">
        <button type="submit" class="btn btn-primary">Salvar e publicar</button>
      </div>
    </main>
  </form>
</div>

<script>
const initialBlocks = <?= json_encode($initialBlocks, JSON_UNESCAPED_UNICODE) ?>;
const blocksEl = document.getElementById('blocks');
const blocksJson = document.getElementById('blocksJson');
let blocks = Array.isArray(initialBlocks) ? initialBlocks.map(normalizeBlock) : [];

function normalizeBlock(b) {
  if (!b || typeof b !== 'object') return { type: 'text', content: '' };
  if (b.type === 'text') return { type: 'text', content: b.content || '' };
  if (b.type === 'image') return { type: 'image', url: b.url || b.key || '' };
  if (b.type === 'grid') return { type: 'grid', cols: b.cols || (b.images||[]).length || 2, images: b.images || [] };
  // legado
  if (b.type === 'image' && b.key) return { type: 'image', url: b.key };
  return { type: 'text', content: b.content || '' };
}

async function uploadFile(file) {
  const fd = new FormData();
  fd.append('file', file);
  const res = await fetch('api_upload.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (!data.ok) throw new Error(data.erro || 'Upload falhou');
  return data.url;
}

function syncHidden() {
  blocksJson.value = JSON.stringify(blocks);
}

function moveBlock(i, dir) {
  const j = i + dir;
  if (j < 0 || j >= blocks.length) return;
  [blocks[i], blocks[j]] = [blocks[j], blocks[i]];
  render();
}

function removeBlock(i) {
  if (!confirm('Remover este bloco?')) return;
  blocks.splice(i, 1);
  render();
}

function render() {
  blocksEl.innerHTML = '';
  if (!blocks.length) {
    blocksEl.innerHTML = '<div class="empty-blocks">Adicione texto, fotos ou grades usando os botões acima.</div>';
  }
  blocks.forEach((b, i) => {
    const card = document.createElement('div');
    card.className = 'block-card';
    card.dataset.i = i;
    const head = document.createElement('div');
    head.className = 'block-head';
    head.innerHTML = `<strong>${labelOf(b)}</strong>
      <div class="block-moves">
        <button type="button" class="btn btn-ghost btn-sm" data-up>↑</button>
        <button type="button" class="btn btn-ghost btn-sm" data-down>↓</button>
        <button type="button" class="btn btn-ghost btn-sm" data-del style="color:var(--danger)">✕</button>
      </div>`;
    head.querySelector('[data-up]').onclick = () => moveBlock(i, -1);
    head.querySelector('[data-down]').onclick = () => moveBlock(i, 1);
    head.querySelector('[data-del]').onclick = () => removeBlock(i);
    card.appendChild(head);

    if (b.type === 'text') {
      const ta = document.createElement('textarea');
      ta.rows = 4;
      ta.placeholder = 'Escreva aqui...';
      ta.value = b.content || '';
      ta.oninput = () => { blocks[i].content = ta.value; syncHidden(); };
      card.appendChild(ta);
    } else if (b.type === 'image') {
      const box = document.createElement('div');
      box.className = 'img-drop';
      if (b.url) {
        box.innerHTML = `<img src="${esc(b.url)}" alt=""><button type="button" class="btn btn-outline btn-sm">Trocar foto</button>`;
      } else {
        box.innerHTML = `<span>Clique para enviar uma foto</span>`;
      }
      const inp = document.createElement('input');
      inp.type = 'file'; inp.accept = 'image/*'; inp.style.display = 'none';
      box.onclick = (e) => { if (e.target.tagName !== 'BUTTON' && !b.url) inp.click(); };
      box.querySelector('button')?.addEventListener('click', (e) => { e.stopPropagation(); inp.click(); });
      inp.onchange = async () => {
        if (!inp.files[0]) return;
        box.classList.add('uploading');
        try {
          const url = await uploadFile(inp.files[0]);
          blocks[i].url = url;
          render();
        } catch (err) { alert(err.message); box.classList.remove('uploading'); }
      };
      card.appendChild(box);
      card.appendChild(inp);
    } else if (b.type === 'grid') {
      const cols = b.cols || 2;
      const grid = document.createElement('div');
      grid.className = 'photo-grid cols-' + cols;
      for (let s = 0; s < cols; s++) {
        const cell = document.createElement('div');
        cell.className = 'grid-cell';
        const url = (b.images && b.images[s]) || '';
        if (url) {
          cell.innerHTML = `<img src="${esc(url)}" alt=""><button type="button" class="btn btn-ghost btn-sm cell-change">Trocar</button>`;
        } else {
          cell.innerHTML = `<span>+ Foto ${s+1}</span>`;
        }
        const inp = document.createElement('input');
        inp.type = 'file'; inp.accept = 'image/*'; inp.style.display = 'none';
        cell.onclick = (e) => { if (e.target.tagName === 'BUTTON') { e.stopPropagation(); inp.click(); return; } if (!url) inp.click(); };
        inp.onchange = async () => {
          if (!inp.files[0]) return;
          cell.classList.add('uploading');
          try {
            const u = await uploadFile(inp.files[0]);
            if (!blocks[i].images) blocks[i].images = [];
            blocks[i].images[s] = u;
            while (blocks[i].images.length < cols) blocks[i].images.push('');
            render();
          } catch (err) { alert(err.message); cell.classList.remove('uploading'); }
        };
        cell.appendChild(inp);
        grid.appendChild(cell);
      }
      card.appendChild(grid);
    }
    blocksEl.appendChild(card);
  });
  syncHidden();
}

function labelOf(b) {
  if (b.type === 'text') return 'Texto';
  if (b.type === 'image') return 'Foto única';
  return 'Grade de ' + (b.cols || 2) + ' fotos';
}
function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
}

document.querySelectorAll('[data-add]').forEach(btn => {
  btn.addEventListener('click', () => {
    const k = btn.dataset.add;
    if (k === 'text') blocks.push({ type: 'text', content: '' });
    else if (k === 'image') blocks.push({ type: 'image', url: '' });
    else if (k === 'grid2') blocks.push({ type: 'grid', cols: 2, images: ['',''] });
    else if (k === 'grid3') blocks.push({ type: 'grid', cols: 3, images: ['','',''] });
    else if (k === 'grid4') blocks.push({ type: 'grid', cols: 4, images: ['','','',''] });
    render();
    blocksEl.lastElementChild?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
});

document.getElementById('capaBox').onclick = () => document.getElementById('capaFile').click();
document.getElementById('capaFile').onchange = async (e) => {
  const f = e.target.files[0];
  if (!f) return;
  try {
    const url = await uploadFile(f);
    document.getElementById('capaUrl').value = url;
    const img = document.getElementById('capaPreview');
    img.src = url; img.style.display = 'block';
    const ph = document.getElementById('capaPlaceholder');
    if (ph) ph.style.display = 'none';
  } catch (err) { alert(err.message); }
};

document.getElementById('projForm').addEventListener('submit', () => syncHidden());
render();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
