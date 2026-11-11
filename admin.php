<?php
require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();
$tab = $_GET['tab'] ?? 'dashboard';
$flash = '';
$erro = '';

// Garante tabela avisos 
try {
    db()->exec("CREATE TABLE IF NOT EXISTS avisos (
      id_aviso INT AUTO_INCREMENT PRIMARY KEY,
      titulo VARCHAR(150) NOT NULL,
      mensagem TEXT NOT NULL,
      id_admin INT NOT NULL,
      data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
      ativo TINYINT DEFAULT 1,
      destino ENUM('artistas','clientes','ambos') NOT NULL DEFAULT 'ambos'
    )");
} catch (Throwable $e) { /* fds */ }

ensure_tables();

/** Envia mensagem (somente leitura para o destinatário) em nome da administração. */
function msg_admin(int $de, int $para, string $texto): void {
    try {
        db()->prepare('INSERT INTO mensagens (id_remetente, id_destinatario, mensagem, data_envio) VALUES (?,?,?,?)')
            ->execute([$de, $para, $texto, now_iso()]);
    } catch (Throwable $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar_categoria') {
        $nome = trim($_POST['nome'] ?? '');
        $desc = trim($_POST['descricao'] ?? '');
        if ($nome === '') {
            $erro = 'Informe o nome da categoria.';
        } else {
            try {
                db()->prepare('INSERT INTO categorias (nome, descricao) VALUES (?,?)')->execute([$nome, $desc ?: null]);
                $flash = 'Categoria criada: ' . $nome;
            } catch (Throwable $e) {
                $erro = 'Não foi possível criar. Nome já existe?';
            }
        }
        $tab = 'categorias';
    }

    if ($acao === 'apagar_categoria') {
        $id = (int)($_POST['id_categoria'] ?? 0);
        // desvincula projetos antes
        db()->prepare('UPDATE projetos SET id_categoria = NULL WHERE id_categoria = ?')->execute([$id]);
        db()->prepare('DELETE FROM categorias WHERE id_categoria = ?')->execute([$id]);
        $flash = 'Categoria removida.';
        $tab = 'categorias';
    }

    if ($acao === 'apagar_usuario') {
        $id = (int)($_POST['id_usuario'] ?? 0);
        if ($id === (int)$admin['id_usuario']) {
            $erro = 'Você não pode apagar a própria conta de administrador.';
        } else {
            try {
                $pdo = db();
                $pdo->beginTransaction();

                // 1) Perfis do usuário (artista)
                $st = $pdo->prepare('SELECT id_perfil FROM perfis_artistas WHERE id_usuario = ?');
                $st->execute([$id]);
                $perfis = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

                // 2) Projetos do usuário
                $st = $pdo->prepare('SELECT id_projeto FROM projetos WHERE id_usuario = ?');
                $st->execute([$id]);
                $projetos = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

                // 3) Pedidos onde é cliente ou artista
                $pedidoIds = [];
                $st = $pdo->prepare('SELECT id_pedido FROM pedidos WHERE id_cliente = ?');
                $st->execute([$id]);
                $pedidoIds = array_merge($pedidoIds, array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
                if ($perfis) {
                    $inP = implode(',', $perfis);
                    $rows = $pdo->query("SELECT id_pedido FROM pedidos WHERE id_artista IN ($inP)")->fetchAll(PDO::FETCH_COLUMN);
                    $pedidoIds = array_merge($pedidoIds, array_map('intval', $rows));
                }
                $pedidoIds = array_values(array_unique($pedidoIds));

                // 4) Pagamentos e avaliações dos pedidos
                if ($pedidoIds) {
                    $inPed = implode(',', $pedidoIds);
                    try { $pdo->exec("DELETE FROM pagamentos WHERE id_pedido IN ($inPed)"); } catch (Throwable $e) {}
                    try { $pdo->exec("DELETE FROM avaliacoes WHERE id_pedido IN ($inPed)"); } catch (Throwable $e) {}
                    $pdo->exec("DELETE FROM pedidos WHERE id_pedido IN ($inPed)");
                }

                // 5) Avaliações onde é avaliador ou avaliado
                try { $pdo->prepare('DELETE FROM avaliacoes WHERE id_avaliador = ?')->execute([$id]); } catch (Throwable $e) {}
                if ($perfis) {
                    $inP = implode(',', $perfis);
                    try { $pdo->exec("DELETE FROM avaliacoes WHERE id_avaliado IN ($inP)"); } catch (Throwable $e) {}
                }

                // 6) Propostas: como artista OU em projetos dele
                if ($perfis) {
                    $inP = implode(',', $perfis);
                    $pdo->exec("DELETE FROM propostas WHERE id_artista IN ($inP)");
                }
                if ($projetos) {
                    $inPr = implode(',', $projetos);
                    $pdo->exec("DELETE FROM propostas WHERE id_projeto IN ($inPr)");
                    try { $pdo->exec("DELETE FROM imagens_projeto WHERE id_projeto IN ($inPr)"); } catch (Throwable $e) {}
                }

                // 7) Favoritos
                try { $pdo->prepare('DELETE FROM favoritos WHERE id_cliente = ?')->execute([$id]); } catch (Throwable $e) {}
                if ($perfis) {
                    $inP = implode(',', $perfis);
                    try { $pdo->exec("DELETE FROM favoritos WHERE id_artista IN ($inP)"); } catch (Throwable $e) {}
                }

                // 8) Mensagens e projetos
                $pdo->prepare('DELETE FROM mensagens WHERE id_remetente = ? OR id_destinatario = ?')->execute([$id, $id]);
                $pdo->prepare('DELETE FROM projetos WHERE id_usuario = ?')->execute([$id]);

                // 9) Perfil e usuário
                $pdo->prepare('DELETE FROM perfis_artistas WHERE id_usuario = ?')->execute([$id]);
                $pdo->prepare('DELETE FROM usuarios WHERE id_usuario = ? AND tipo != "admin"')->execute([$id]);

                $pdo->commit();
                $flash = 'Usuário removido com sucesso.';
            } catch (Throwable $e) {
                if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                $erro = 'Erro ao remover usuário: ' . $e->getMessage();
            }
        }
        $tab = 'usuarios';
    }

    if ($acao === 'aviso') {
        $titulo = trim($_POST['titulo'] ?? '');
        $mensagem = trim($_POST['mensagem'] ?? '');
        $destino = 'ambos'; // não há mais separação entre artistas e clientes
        if ($titulo === '' || $mensagem === '') {
            $erro = 'Preencha título e mensagem do aviso.';
        } else {
            // Garante compatibilidade com bancos antigos que ainda não possuem a coluna destino.
            try {
                $temDestino = db()->query("SHOW COLUMNS FROM avisos LIKE 'destino'")->fetch();
                if (!$temDestino) {
                    db()->exec("ALTER TABLE avisos ADD COLUMN destino ENUM('artistas','clientes','ambos') NOT NULL DEFAULT 'artistas'");
                }
            } catch (Throwable $e) {
                // O SQL de instalação/atualização também cria essa coluna.
            }

            db()->prepare('INSERT INTO avisos (titulo, mensagem, id_admin, data_criacao, ativo, destino) VALUES (?,?,?,?,1,?)')
                ->execute([$titulo, $mensagem, $admin['id_usuario'], now_iso(), $destino]);

            $usuariosAviso = db()->query("SELECT id_usuario FROM usuarios WHERE tipo!='admin' AND status='ativo'")->fetchAll();
            foreach ($usuariosAviso as $destinatario) {
                $texto = "Aviso da administração: $titulo\n\n$mensagem";
                db()->prepare('INSERT INTO mensagens (id_remetente, id_destinatario, mensagem, data_envio) VALUES (?,?,?,?)')
                    ->execute([$admin['id_usuario'], $destinatario['id_usuario'], $texto, now_iso()]);
            }
            $flash = 'Aviso publicado e enviado para ' . count($usuariosAviso) . ' usuário(s).';
        }
        $tab = 'avisos';
    }

    if ($acao === 'desativar_aviso') {
        $id = (int)($_POST['id_aviso'] ?? 0);
        db()->prepare('UPDATE avisos SET ativo=0 WHERE id_aviso=?')->execute([$id]);
        $flash = 'Aviso desativado.';
        $tab = 'avisos';
    }

    if ($acao === 'apagar_aviso') {
        $id = (int)($_POST['id_aviso'] ?? 0);
        db()->prepare('DELETE FROM avisos WHERE id_aviso=?')->execute([$id]);
        $flash = 'Aviso excluído.';
        $tab = 'avisos';
    }

    /* ---------- Denúncias ---------- */
    if (in_array($acao, ['denuncia_procedente', 'denuncia_improcedente', 'restaurar_publicacao', 'apagar_publicacao'], true)) {
        $tab = 'denuncias';
        $idd = (int)($_POST['id_denuncia'] ?? 0);
        $idp = (int)($_POST['id_projeto'] ?? 0);
        $resposta = trim($_POST['resposta'] ?? '');
        $motivos = motivos_denuncia();

        if ($acao === 'denuncia_procedente' || $acao === 'denuncia_improcedente') {
            $st = db()->prepare('SELECT d.*, pr.titulo, pr.id_usuario AS autor_id FROM denuncias d JOIN projetos pr ON pr.id_projeto=d.id_projeto WHERE d.id_denuncia=?');
            $st->execute([$idd]);
            $d = $st->fetch();
            if (!$d) {
                $erro = 'Denúncia não encontrada.';
            } elseif ($acao === 'denuncia_procedente') {
                // Oculta a publicação e resolve TODAS as denúncias pendentes dela
                db()->prepare('UPDATE projetos SET visivel=0 WHERE id_projeto=?')->execute([$d['id_projeto']]);
                $lista = db()->prepare("SELECT id_denunciante FROM denuncias WHERE id_projeto=? AND status='pendente'");
                $lista->execute([$d['id_projeto']]);
                $denunciantes = $lista->fetchAll(PDO::FETCH_COLUMN);
                db()->prepare("UPDATE denuncias SET status='procedente', id_admin=?, resposta_admin=?, data_resolucao=? WHERE id_projeto=? AND status='pendente'")
                    ->execute([$admin['id_usuario'], $resposta ?: null, now_iso(), $d['id_projeto']]);
                $motivoTxt = $motivos[$d['motivo']] ?? $d['motivo'];
                msg_admin((int)$admin['id_usuario'], (int)$d['autor_id'],
                    " Sua publicação \"{$d['titulo']}\" foi removida pela moderação por violar as regras da comunidade (motivo: $motivoTxt)." . ($resposta ? "\n\nObservação: $resposta" : ''));
                foreach ($denunciantes as $den) {
                    msg_admin((int)$admin['id_usuario'], (int)$den, " Analisamos sua denúncia sobre \"{$d['titulo']}\" e a publicação foi removida. Obrigado por ajudar!");
                }
                $flash = 'Denúncia procedente: a publicação foi ocultada e o autor foi avisado.';
            } else {
                db()->prepare("UPDATE denuncias SET status='improcedente', id_admin=?, resposta_admin=?, data_resolucao=? WHERE id_denuncia=?")
                    ->execute([$admin['id_usuario'], $resposta ?: null, now_iso(), $idd]);
                msg_admin((int)$admin['id_usuario'], (int)$d['id_denunciante'],
                    " Analisamos sua denúncia sobre \"{$d['titulo']}\" e não encontramos violação das regras. A publicação continua no ar." . ($resposta ? "\n\nObservação: $resposta" : ''));
                $flash = 'Denúncia marcada como improcedente. A publicação continua visível.';
            }
        }

        if ($acao === 'restaurar_publicacao') {
            db()->prepare('UPDATE projetos SET visivel=1 WHERE id_projeto=?')->execute([$idp]);
            $flash = 'Publicação restaurada.';
        }

        if ($acao === 'apagar_publicacao') {
            $st = db()->prepare('SELECT titulo, id_usuario FROM projetos WHERE id_projeto=?');
            $st->execute([$idp]);
            if ($pr = $st->fetch()) {
                msg_admin((int)$admin['id_usuario'], (int)$pr['id_usuario'], "🗑️ Sua publicação \"{$pr['titulo']}\" foi excluída pela moderação por violar as regras da comunidade." . ($resposta ? "\n\nObservação: $resposta" : ''));
                db()->prepare('DELETE FROM denuncias WHERE id_projeto=?')->execute([$idp]);
                db()->prepare('DELETE FROM propostas WHERE id_projeto=?')->execute([$idp]);
                db()->prepare('DELETE FROM projetos WHERE id_projeto=?')->execute([$idp]);
                $flash = 'Publicação excluída definitivamente e autor avisado.';
            }
        }
    }

    if ($acao === 'toggle_status') {
        $id = (int)($_POST['id_usuario'] ?? 0);
        $novo = ($_POST['status'] ?? '') === 'ativo' ? 'inativo' : 'ativo';
        if ($id !== (int)$admin['id_usuario']) {
            db()->prepare('UPDATE usuarios SET status=? WHERE id_usuario=? AND tipo!="admin"')->execute([$novo, $id]);
            $flash = 'Status atualizado.';
        }
        $tab = 'usuarios';
    }
}

// Métricas
$stats = [
    'usuarios' => (int)db()->query("SELECT COUNT(*) FROM usuarios WHERE tipo!='admin'")->fetchColumn(),
    'categorias' => (int)db()->query('SELECT COUNT(*) FROM categorias')->fetchColumn(),
    'projetos' => (int)db()->query("SELECT COUNT(*) FROM projetos WHERE tipo='portfolio'")->fetchColumn(),
    'briefings' => (int)db()->query("SELECT COUNT(*) FROM projetos WHERE tipo='briefing'")->fetchColumn(),
    'mensagens' => (int)db()->query('SELECT COUNT(*) FROM mensagens')->fetchColumn(),
];

$stats['denuncias'] = (int)db()->query("SELECT COUNT(*) FROM denuncias WHERE status='pendente'")->fetchColumn();
$filtro = $_GET['status'] ?? 'pendente';
if (!in_array($filtro, ['pendente', 'procedente', 'improcedente', 'todas'], true)) $filtro = 'pendente';
$denuncias = [];
if ($tab === 'denuncias') {
    $sqlD = "SELECT d.*, pr.titulo, pr.visivel, pr.tipo AS ptipo, pr.id_usuario AS autor_id, ua.nome AS autor, ud.nome AS denunciante,
        (SELECT COUNT(*) FROM denuncias d2 WHERE d2.id_projeto=d.id_projeto) AS total_projeto,
        COALESCE(NULLIF(pr.imagem_url,''),(SELECT ip.storage_key FROM imagens_projeto ip WHERE ip.id_projeto=pr.id_projeto ORDER BY ip.ordem,ip.id_imagem LIMIT 1)) AS capa
        FROM denuncias d
        JOIN projetos pr ON pr.id_projeto=d.id_projeto
        JOIN usuarios ua ON ua.id_usuario=pr.id_usuario
        JOIN usuarios ud ON ud.id_usuario=d.id_denunciante";
    if ($filtro !== 'todas') $sqlD .= " WHERE d.status=" . db()->quote($filtro);
    $sqlD .= " ORDER BY (d.status='pendente') DESC, d.data_criacao DESC LIMIT 100";
    $denuncias = db()->query($sqlD)->fetchAll();
}

$categorias = db()->query('SELECT c.*, (SELECT COUNT(*) FROM projetos p WHERE p.id_categoria=c.id_categoria) AS qtd FROM categorias c ORDER BY c.nome')->fetchAll();
$usuarios = db()->query("SELECT * FROM usuarios WHERE tipo!='admin' ORDER BY data_cadastro DESC")->fetchAll();
$avisos = [];
try {
    $avisos = db()->query('SELECT * FROM avisos ORDER BY data_criacao DESC LIMIT 30')->fetchAll();
} catch (Throwable $e) {}

$page_title = 'Administração — OctoArt';
$active = 'admin';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap admin-page">
  <div class="admin-header">
    <div>
      <p class="eyebrow-hand" style="margin:0">painel admin</p>
      <h1 style="margin:0">Administração</h1>
      <p class="muted" style="margin:.3rem 0 0">Olá, <?= h(explode(' ', $admin['nome'])[0]) ?> · controle da plataforma</p>
    </div>
  </div>

  <?php if ($flash): ?><div class="success-msg"><?= h($flash) ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="error-msg show"><?= h($erro) ?></div><?php endif; ?>

  <nav class="admin-tabs">
    <a class="<?= $tab==='dashboard'?'active':'' ?>" href="admin.php?tab=dashboard">Dashboard</a>
    <a class="<?= $tab==='categorias'?'active':'' ?>" href="admin.php?tab=categorias">Categorias</a>
    <a class="<?= $tab==='usuarios'?'active':'' ?>" href="admin.php?tab=usuarios">Usuários</a>
    <a class="<?= $tab==='denuncias'?'active':'' ?>" href="admin.php?tab=denuncias">🚩 Denúncias<?= $stats['denuncias'] ? ' <span class="admin-badge">'.(int)$stats['denuncias'].'</span>' : '' ?></a>
    <a class="<?= $tab==='avisos'?'active':'' ?>" href="admin.php?tab=avisos">Avisos</a>
  </nav>

  <?php if ($tab === 'dashboard'): ?>
  <div class="admin-stats">
    <div class="admin-stat"><strong><?= $stats['usuarios'] ?></strong><span>Usuários</span></div>
    <div class="admin-stat"><strong><?= $stats['categorias'] ?></strong><span>Categorias</span></div>
    <div class="admin-stat"><strong><?= $stats['projetos'] ?></strong><span>Trabalhos</span></div>
    <div class="admin-stat"><strong><?= $stats['briefings'] ?></strong><span>Pedidos</span></div>
    <div class="admin-stat"><strong><?= $stats['mensagens'] ?></strong><span>Mensagens</span></div>
    <div class="admin-stat <?= $stats['denuncias'] ? 'admin-stat-alert' : '' ?>"><strong><?= $stats['denuncias'] ?></strong><span>Denúncias pendentes</span></div>
  </div>

  <div class="admin-grid-2">
    <div class="admin-panel">
      <h3>Ações rápidas</h3>
      <div class="admin-quick">
        <a class="btn btn-primary btn-sm" href="admin.php?tab=categorias">+ Categoria</a>
        <a class="btn btn-outline btn-sm" href="admin.php?tab=usuarios">Gerenciar usuários</a>
        <a class="btn btn-outline btn-sm" href="admin.php?tab=denuncias">Ver denúncias<?= $stats['denuncias'] ? ' ('.(int)$stats['denuncias'].')' : '' ?></a>
        <a class="btn btn-outline btn-sm" href="admin.php?tab=avisos">Enviar aviso</a>
        <a class="btn btn-ghost btn-sm" href="explorar.php?tipo=artistas">Ver site</a>
      </div>
    </div>
    <div class="admin-panel">
      <h3>Últimos avisos</h3>
      <?php if (!$avisos): ?>
        <p class="muted">Nenhum aviso ainda.</p>
      <?php else: ?>
        <?php foreach (array_slice($avisos, 0, 4) as $av): ?>
          <div class="admin-aviso-mini">
            <strong><?= h($av['titulo']) ?></strong>
            <span class="muted"><?= fmt_date($av['data_criacao']) ?> · <?= $av['ativo'] ? 'ativo' : 'inativo' ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php elseif ($tab === 'categorias'): ?>
  <div class="admin-grid-2">
    <div class="admin-panel">
      <h3>Nova categoria</h3>
      <form method="post">
        <input type="hidden" name="acao" value="criar_categoria">
        <div class="field"><label>Nome</label><input type="text" name="nome" required placeholder="Ex: Animação"></div>
        <div class="field"><label>Descrição</label><textarea name="descricao" placeholder="Breve descrição da categoria"></textarea></div>
        <button class="btn btn-primary" type="submit">Criar categoria</button>
      </form>
    </div>
    <div class="admin-panel">
      <h3>Categorias (<?= count($categorias) ?>)</h3>
      <div class="admin-table">
        <?php foreach ($categorias as $c): ?>
          <div class="admin-row">
            <div>
              <strong><?= h($c['nome']) ?></strong>
              <div class="muted" style="font-size:.85rem"><?= h($c['descricao'] ?? '') ?> · <?= (int)$c['qtd'] ?> projeto(s)</div>
            </div>
            <form method="post" onsubmit="return confirm('Apagar categoria <?= h($c['nome']) ?>?')">
              <input type="hidden" name="acao" value="apagar_categoria">
              <input type="hidden" name="id_categoria" value="<?= (int)$c['id_categoria'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--danger)">Apagar</button>
            </form>
          </div>
        <?php endforeach; ?>
        <?php if (!$categorias): ?><p class="muted">Nenhuma categoria.</p><?php endif; ?>
      </div>
    </div>
  </div>

  <?php elseif ($tab === 'usuarios'): ?>
  <div class="admin-panel">
    <h3>Usuários (<?= count($usuarios) ?>)</h3>
    <div class="admin-table">
      <?php foreach ($usuarios as $u): ?>
        <div class="admin-row admin-user-row">
          <div class="admin-user-info">
            <?php if ($u['foto_perfil']): ?>
              <img src="<?= h(media_url($u['foto_perfil'])) ?>" alt="" class="admin-avatar">
            <?php else: ?>
              <div class="admin-avatar admin-avatar-fb"><?= h(initials($u['nome'])) ?></div>
            <?php endif; ?>
            <div>
              <strong><?= h($u['nome']) ?></strong>
              <div class="muted" style="font-size:.85rem"><?= h($u['email']) ?></div>
              <div style="margin-top:4px">
                <span class="status-pill status-<?= h($u['status']) ?>"><?= h($u['status']) ?></span>
              </div>
            </div>
          </div>
          <div class="admin-user-actions">
            <form method="post" style="display:inline">
              <input type="hidden" name="acao" value="toggle_status">
              <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
              <input type="hidden" name="status" value="<?= h($u['status']) ?>">
              <button class="btn btn-outline btn-sm" type="submit"><?= $u['status']==='ativo'?'Desativar':'Ativar' ?></button>
            </form>
            <form method="post" style="display:inline" onsubmit="return confirm('Apagar permanentemente <?= h($u['nome']) ?>?')">
              <input type="hidden" name="acao" value="apagar_usuario">
              <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--danger)">Apagar</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$usuarios): ?><p class="muted">Nenhum usuário cadastrado.</p><?php endif; ?>
    </div>
  </div>

  <?php elseif ($tab === 'denuncias'): ?>
  <div class="admin-panel">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
      <h3 style="margin:0">Denúncias de publicações</h3>
      <div class="admin-filter">
        <?php foreach (['pendente'=>'Pendentes','procedente'=>'Procedentes','improcedente'=>'Improcedentes','todas'=>'Todas'] as $k=>$lbl): ?>
          <a class="<?= $filtro===$k?'active':'' ?>" href="admin.php?tab=denuncias&status=<?= $k ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php $motivosMap = motivos_denuncia(); ?>
    <?php if (!$denuncias): ?><p class="muted">Nenhuma denúncia <?= $filtro==='todas' ? '' : h($filtro).'' ?> por aqui. 🎉</p><?php endif; ?>
    <?php foreach ($denuncias as $d): ?>
      <div class="report-card status-<?= h($d['status']) ?>">
        <div class="report-card-top">
          <?php if (!empty($d['capa'])): ?><img src="<?= h($d['capa']) ?>" alt="" class="report-thumb"><?php else: ?><div class="report-thumb report-thumb-ph"><?= $d['ptipo']==='portfolio' ? '🎨' : '📌' ?></div><?php endif; ?>
          <div style="flex:1;min-width:200px">
            <strong><a href="projeto.php?id=<?= (int)$d['id_projeto'] ?>" target="_blank" rel="noopener"><?= h($d['titulo']) ?></a></strong>
            <div class="muted" style="font-size:.85rem">Autor: <?= h($d['autor']) ?> · Denunciado por <?= h($d['denunciante']) ?> · <?= fmt_date($d['data_criacao']) ?></div>
            <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
              <span class="tag"><?= h($motivosMap[$d['motivo']] ?? $d['motivo']) ?></span>
              <span class="status-pill <?= $d['status']==='pendente' ? 'status-aberto' : ($d['status']==='procedente' ? 'status-cancelado' : 'status-concluido') ?>"><?= h($d['status']) ?></span>
              <?php if ((int)$d['total_projeto'] > 1): ?><span class="tag">⚠️ <?= (int)$d['total_projeto'] ?> denúncias nesta publicação</span><?php endif; ?>
              <?php if (!(int)$d['visivel']): ?><span class="tag" style="background:#fdecea;color:#b3261e">Oculta</span><?php endif; ?>
            </div>
            <?php if (!empty($d['detalhes'])): ?><p class="report-details">“<?= nl2br(h($d['detalhes'])) ?>”</p><?php endif; ?>
            <?php if (!empty($d['resposta_admin'])): ?><p class="muted" style="font-size:.85rem;margin:6px 0 0"><strong>Sua observação:</strong> <?= h($d['resposta_admin']) ?></p><?php endif; ?>
          </div>
        </div>

        <?php if ($d['status'] === 'pendente'): ?>
        <form method="post" class="report-actions">
          <input type="hidden" name="id_denuncia" value="<?= (int)$d['id_denuncia'] ?>">
          <input type="hidden" name="id_projeto" value="<?= (int)$d['id_projeto'] ?>">
          <input type="text" name="resposta" maxlength="300" placeholder="Observação para o autor/denunciante (opcional)">
          <button class="btn btn-primary btn-sm" type="submit" name="acao" value="denuncia_procedente" onclick="return confirm('Ocultar esta publicação e avisar o autor?')">Remover publicação</button>
          <button class="btn btn-outline btn-sm" type="submit" name="acao" value="denuncia_improcedente">Manter publicação</button>
        </form>
        <?php else: ?>
        <form method="post" class="report-actions">
          <input type="hidden" name="id_projeto" value="<?= (int)$d['id_projeto'] ?>">
          <?php if (!(int)$d['visivel']): ?>
            <button class="btn btn-outline btn-sm" type="submit" name="acao" value="restaurar_publicacao">Restaurar publicação</button>
          <?php endif; ?>
          <button class="btn btn-ghost btn-sm" type="submit" name="acao" value="apagar_publicacao" style="color:var(--danger)" onclick="return confirm('Excluir DEFINITIVAMENTE esta publicação?')">Excluir definitivamente</button>
        </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php elseif ($tab === 'avisos'): ?>
  <div class="admin-grid-2">
    <div class="admin-panel">
      <h3>Novo aviso</h3>
      <p class="muted" style="font-size:.9rem">O aviso vai para todos os usuários: aparece no perfil de cada um e também é enviado como mensagem (somente leitura — ninguém pode responder à administração).</p>
      <form method="post">
        <input type="hidden" name="acao" value="aviso">
        <div class="field"><label>Título</label><input type="text" name="titulo" required placeholder="Ex: Manutenção programada"></div>
        <div class="field"><label>Mensagem</label><textarea name="mensagem" required rows="5" placeholder="Texto do aviso..."></textarea></div>
        <button class="btn btn-primary" type="submit">Publicar e enviar</button>
      </form>
    </div>
    <div class="admin-panel">
      <h3>Avisos publicados</h3>
      <?php foreach ($avisos as $av): ?>
        <div class="admin-row" style="flex-direction:column;align-items:stretch;gap:8px">
          <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
            <strong><?= h($av['titulo']) ?></strong>
            <span class="muted"><?= fmt_date($av['data_criacao']) ?> · <?= $av['ativo'] ? 'ativo' : 'inativo' ?></span>
          </div>
          <p style="margin:0;font-size:.92rem"><?= nl2br(h($av['mensagem'])) ?></p>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php if ($av['ativo']): ?>
            <form method="post">
              <input type="hidden" name="acao" value="desativar_aviso">
              <input type="hidden" name="id_aviso" value="<?= (int)$av['id_aviso'] ?>">
              <button class="btn btn-outline btn-sm" type="submit">Desativar</button>
            </form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('Excluir este aviso permanentemente?')">
              <input type="hidden" name="acao" value="apagar_aviso">
              <input type="hidden" name="id_aviso" value="<?= (int)$av['id_aviso'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit" style="color:var(--danger)">Excluir</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$avisos): ?><p class="muted">Nenhum aviso.</p><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<style>
.admin-page{padding:28px 24px 70px}
.admin-header{margin-bottom:20px}
.admin-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px}
.admin-tabs a{
  padding:8px 16px;border-radius:999px;font-weight:600;font-size:.9rem;
  border:1.5px solid var(--grey-300);color:var(--grey-600);background:var(--white);
}
.admin-tabs a.active,.admin-tabs a:hover{background:var(--blue);color:#fff;border-color:var(--blue)}
.admin-stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px;margin-bottom:22px}
.admin-stat{
  background:var(--white);border:1.5px solid var(--grey-300);border-radius:16px;
  padding:16px;text-align:center;box-shadow:var(--shadow);
}
.admin-stat strong{display:block;font-family:var(--font-display);font-size:1.5rem;color:var(--blue)}
.admin-stat span{font-size:.78rem;color:var(--grey-600);font-weight:600}
.admin-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:800px){.admin-grid-2{grid-template-columns:1fr}}
.admin-panel{
  background:var(--white);border:1.5px solid var(--grey-300);border-radius:18px;
  padding:20px;box-shadow:var(--shadow);
}
.admin-panel h3{margin:0 0 14px;font-size:1.1rem}
.admin-quick{display:flex;flex-wrap:wrap;gap:8px}
.admin-table{display:flex;flex-direction:column;gap:0}
.admin-row{
  display:flex;justify-content:space-between;align-items:center;gap:12px;
  padding:12px 0;border-bottom:1px solid var(--grey-100);
}
.admin-row:last-child{border-bottom:none}
.admin-user-row{flex-wrap:wrap}
.admin-user-info{display:flex;gap:12px;align-items:center;flex:1}
.admin-avatar{width:44px;height:44px;border-radius:50%;object-fit:cover}
.admin-avatar-fb{
  display:flex;align-items:center;justify-content:center;background:var(--blue-100);
  color:var(--blue);font-weight:700;font-size:.9rem;
}
.admin-user-actions{display:flex;gap:6px;flex-wrap:wrap}
.admin-aviso-mini{padding:8px 0;border-bottom:1px solid var(--grey-100)}
.admin-aviso-mini strong{display:block}
/* ---- Denúncias ---- */
.admin-badge{display:inline-block;min-width:20px;padding:0 6px;margin-left:4px;border-radius:999px;background:#d93025;color:#fff;font-size:.72rem;line-height:20px;text-align:center}
.admin-stat-alert{border-color:#d93025!important}.admin-stat-alert strong{color:#d93025!important}
.admin-filter{display:flex;gap:6px;flex-wrap:wrap}
.admin-filter a{padding:5px 12px;border-radius:999px;font-size:.82rem;font-weight:600;border:1.5px solid var(--grey-300);color:var(--grey-600)}
.admin-filter a.active,.admin-filter a:hover{background:var(--blue);color:#fff;border-color:var(--blue)}
.report-card{border:1.5px solid var(--grey-300);border-left:5px solid var(--grey-300);border-radius:14px;padding:14px;margin-bottom:12px}
.report-card.status-pendente{border-left-color:#f29900}.report-card.status-procedente{border-left-color:#d93025}.report-card.status-improcedente{border-left-color:#1f7a4d}
.report-card-top{display:flex;gap:14px;flex-wrap:wrap}
.report-thumb{width:84px;height:84px;object-fit:cover;border-radius:12px;flex:none}
.report-thumb-ph{display:flex;align-items:center;justify-content:center;background:var(--blue-100);font-size:1.6rem}
.report-details{margin:8px 0 0;padding:8px 12px;background:var(--grey-100);border-radius:10px;font-size:.9rem}
.report-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:12px}
.report-actions input[type=text]{flex:1;min-width:220px;padding:8px 12px;border-radius:10px;border:1.5px solid var(--grey-300);background:transparent;color:inherit}
html.dark .report-details{background:#231d3a}
html.dark .admin-tabs a{background:#161322;border-color:var(--grey-300);color:var(--grey-600)}
html.dark .admin-tabs a.active,html.dark .admin-tabs a:hover{background:var(--blue);color:#0C0B14}
html.dark .admin-stat,html.dark .admin-panel{background:#161322;border-color:var(--grey-300)}
html.dark .admin-row{border-color:var(--grey-300)}
html.dark .admin-avatar-fb{background:var(--blue-100);color:var(--blue-300)}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
