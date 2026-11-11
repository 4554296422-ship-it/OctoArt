<?php
require_once __DIR__ . '/includes/auth.php';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$msg = $erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = reset_password_with_token($token, $_POST['senha'] ?? '');
    if ($res['ok']) $msg = $res['msg'];
    else $erro = $res['msg'];
}
$page_title = 'Nova senha — OctoArt';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell" style="min-height:70vh">
  <div class="auth-form-col" style="margin:0 auto;max-width:420px;padding:40px 16px">
    <div class="auth-box">
      <h2 style="margin-top:0">Nova senha</h2>
      <?php if ($msg): ?>
        <div class="success-msg"><?= h($msg) ?></div>
        <a class="btn btn-primary" href="login.php">Entrar</a>
      <?php else: ?>
        <?php if ($erro): ?><div class="error-msg show"><?= h($erro) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="token" value="<?= h($token) ?>">
          <div class="field"><label>Nova senha</label><input type="password" name="senha" minlength="6" required></div>
          <button class="btn btn-primary btn-block" type="submit">Salvar senha</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
