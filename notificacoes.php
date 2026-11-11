<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$uid = (int)$user['id_usuario'];

// Abre a notificação, marca como lida e segue somente para uma rota interna conhecida.
if (isset($_GET['abrir'])) {
    $id = (int)$_GET['abrir'];
    $st = db()->prepare('SELECT link_destino FROM notificacoes WHERE id_notificacao=? AND id_destinatario=? LIMIT 1');
    $st->execute([$id, $uid]);
    $destino = $st->fetchColumn();
    if ($destino !== false) {
        db()->prepare('UPDATE notificacoes SET lida=1 WHERE id_notificacao=? AND id_destinatario=?')->execute([$id, $uid]);
        if (is_string($destino) && preg_match('/^(?:[a-zA-Z0-9_-]+\.php)(?:\?[a-zA-Z0-9_=&%.-]*)?$/', $destino)) {
            header('Location: ' . $destino);
            exit;
        }
    }
    header('Location: notificacoes.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'marcar_todas') {
    db()->prepare('UPDATE notificacoes SET lida=1 WHERE id_destinatario=? AND lida=0')->execute([$uid]);
    header('Location: notificacoes.php?ok=1');
    exit;
}

$st = db()->prepare("SELECT n.*, u.nome AS nome_remetente, u.foto_perfil
    FROM notificacoes n
    LEFT JOIN usuarios u ON u.id_usuario=n.id_remetente
    WHERE n.id_destinatario=?
    ORDER BY n.data_criacao DESC, n.id_notificacao DESC
    LIMIT 100");
$st->execute([$uid]);
$notificacoes = $st->fetchAll();
$nao_lidas = 0;
foreach ($notificacoes as $n) if (!(int)$n['lida']) $nao_lidas++;

$page_title = 'Notificações — OctoArt';
$active = 'notificacoes';
require __DIR__ . '/includes/header.php';
?>
<main class="wrap notifications-page">
  <div class="notifications-heading">
    <div>
      <p class="notifications-eyebrow">FIQUE POR DENTRO</p>
      <h1>Notificações</h1>
      <p class="muted">Curtidas, comentários e mensagens da comunidade em um só lugar.</p>
    </div>
    <?php if ($nao_lidas > 0): ?>
      <form method="post">
        <input type="hidden" name="acao" value="marcar_todas">
        <button type="submit" class="btn btn-outline btn-sm">Marcar todas como lidas</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (isset($_GET['ok'])): ?><div class="notification-success">Tudo certo! Todas as notificações foram marcadas como lidas.</div><?php endif; ?>

  <section class="notifications-list" aria-label="Lista de notificações">
    <?php if (!$notificacoes): ?>
      <div class="notifications-empty">
        <div class="notifications-empty-icon">♢</div>
        <h2>Você está em dia!</h2>
        <p>Quando alguém curtir ou comentar seus projetos, ou enviar uma mensagem, você verá tudo aqui.</p>
        <a href="explorar.php?tipo=projetos" class="btn btn-primary">Explorar projetos</a>
      </div>
    <?php else: ?>
      <?php foreach ($notificacoes as $n):
        $tipo = (string)$n['tipo'];
        $icone = $tipo === 'curtida' ? '♥' : ($tipo === 'comentario' ? '☰' : ($tipo === 'mensagem' ? '✉' : '•'));
        $rotulo = $tipo === 'curtida' ? 'Curtida' : ($tipo === 'comentario' ? 'Comentário' : ($tipo === 'mensagem' ? 'Mensagem' : 'Notificação'));
        $data = strtotime((string)$n['data_criacao']);
      ?>
        <a class="notification-item <?= (int)$n['lida'] ? '' : 'unread' ?>" href="notificacoes.php?abrir=<?= (int)$n['id_notificacao'] ?>">
          <span class="notification-item-icon type-<?= h($tipo) ?>"><?= h($icone) ?></span>
          <span class="notification-item-content">
            <span class="notification-item-top"><strong><?= h($rotulo) ?></strong><?php if (!(int)$n['lida']): ?><span class="notification-unread-dot" aria-label="Não lida"></span><?php endif; ?></span>
            <span class="notification-item-message"><?= h($n['mensagem']) ?></span>
            <span class="notification-item-date"><?= $data ? h(date('d/m/Y - H:i', $data)) : '' ?><?= !empty($n['nome_remetente']) ? ' · ' . h($n['nome_remetente']) : '' ?></span>
          </span>
          <span class="notification-item-arrow">›</span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
