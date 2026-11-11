<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();
$com = isset($_GET['com']) ? (int)$_GET['com'] : 0;

// Descobre com quem a conversa é (e bloqueia envio para a administração)
$outro = null;
if ($com) {
    $st = db()->prepare("SELECT * FROM usuarios WHERE id_usuario=?");
    $st->execute([$com]);
    $outro = $st->fetch() ?: null;
}
$conversa_admin = $outro && $outro['tipo'] === 'admin';
$pode_enviar = $outro && !$conversa_admin && (int)$outro['id_usuario'] !== (int)$user['id_usuario'] && !is_admin($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $com) {
    $texto = trim($_POST['mensagem'] ?? '');
    if ($pode_enviar && $texto !== '') {
        db()->prepare('INSERT INTO mensagens (id_remetente,id_destinatario,mensagem,data_envio) VALUES (?,?,?,?)')
            ->execute([$user['id_usuario'], $com, $texto, now_iso()]);
        criar_notificacao((int)$com, (int)$user['id_usuario'], 'mensagem', $user['nome'] . ' enviou uma mensagem para você.', 'mensagens.php?com=' . (int)$user['id_usuario']);
    }
    header('Location: mensagens.php?com='.$com);
    exit;
}

// conversas
$st = db()->prepare("
  SELECT * FROM mensagens
  WHERE id_remetente=? OR id_destinatario=?
  ORDER BY data_envio DESC
");
$st->execute([$user['id_usuario'], $user['id_usuario']]);
$all = $st->fetchAll();
$parceiros = [];
foreach ($all as $m) {
    $oid = (int)$m['id_remetente'] === (int)$user['id_usuario'] ? (int)$m['id_destinatario'] : (int)$m['id_remetente'];
    if (!isset($parceiros[$oid])) $parceiros[$oid] = $m;
}
if ($com && !isset($parceiros[$com]) && $pode_enviar) {
    $parceiros[$com] = null;
}

$msgs = [];
if ($com && $outro) {
    $st = db()->prepare("
      SELECT * FROM mensagens
      WHERE (id_remetente=? AND id_destinatario=?) OR (id_remetente=? AND id_destinatario=?)
      ORDER BY data_envio ASC
    ");
    $st->execute([$user['id_usuario'], $com, $com, $user['id_usuario']]);
    $msgs = $st->fetchAll();
    db()->prepare('UPDATE mensagens SET lida=1 WHERE id_destinatario=? AND id_remetente=?')
        ->execute([$user['id_usuario'], $com]);
}

$page_title = 'Mensagens — OctoArt';
$active = 'mensagens';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap" style="padding:24px">
  <h2>Mensagens</h2>
  <div style="display:grid;grid-template-columns:280px 1fr;gap:16px;min-height:420px;background:var(--white);border:1.5px solid var(--grey-300);border-radius:16px;overflow:hidden;margin-top:12px">
    <div style="border-right:1px solid var(--grey-300);overflow:auto">
      <?php if (!$parceiros): ?>
        <p class="muted" style="padding:20px">Nenhuma conversa ainda. Visite o perfil de alguém da comunidade para começar.</p>
      <?php endif; ?>
      <?php foreach ($parceiros as $oid => $last):
        $ou = db()->prepare('SELECT nome, tipo FROM usuarios WHERE id_usuario=?');
        $ou->execute([$oid]);
        $ouRow = $ou->fetch();
        $nome = $ouRow['nome'] ?? 'Usuário';
        $ehAdmin = ($ouRow['tipo'] ?? '') === 'admin';
      ?>
        <a href="mensagens.php?com=<?= $oid ?>" style="display:block;padding:14px;border-bottom:1px solid var(--grey-100);<?= $com===$oid?'background:var(--blue-50)':'' ?>">
          <strong><?= h($nome) ?><?= $ehAdmin ? ' 📢 Administração' : '' ?></strong>
          <div class="muted" style="font-size:.82rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= h($last['mensagem'] ?? 'Nova conversa') ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;flex-direction:column">
      <div style="padding:14px;border-bottom:1px solid var(--grey-300);font-weight:600;color:var(--blue)">
        <?= $outro ? h($outro['nome']) : 'Selecione uma conversa' ?>
      </div>
      <div class="messages-chat-body" style="flex:1;overflow:auto;padding:18px 22px;display:flex;flex-direction:column;gap:10px;background:var(--grey-100);align-items:flex-start;">
        <?php foreach ($msgs as $m):
          $mine = (int)$m['id_remetente'] === (int)$user['id_usuario'];
        ?>
          <div class="message-bubble <?= $mine ? 'message-mine' : 'message-them' ?>">
            <span class="message-text"><?= h($m['mensagem']) ?></span>
            <span class="message-time"><?= fmt_date($m['data_envio']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($conversa_admin): ?>
      <div style="padding:14px;border-top:1px solid var(--grey-300);text-align:center" class="muted">
         Mensagens da administração são somente leitura — não é possível responder.
      </div>
      <?php elseif ($pode_enviar): ?>
      <form method="post" style="display:flex;gap:8px;padding:12px;border-top:1px solid var(--grey-300)">
        <input type="text" name="mensagem" class="form-control" style="flex:1;padding:10px 12px;border-radius:10px;border:1.5px solid var(--grey-300)" placeholder="Digite sua mensagem..." required>
        <button class="btn btn-primary" type="submit">Enviar</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<style>@media(max-width:768px){.wrap>div[style*="grid-template-columns"]{grid-template-columns:1fr!important}}</style>
<?php require __DIR__ . '/includes/footer.php'; ?>
