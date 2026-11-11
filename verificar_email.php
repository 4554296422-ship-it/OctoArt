<?php
require_once __DIR__ . '/includes/auth.php';
$token = trim($_GET['token'] ?? '');
$msg = $erro = '';
if ($token) {
    $res = verify_email_token($token);
    if ($res['ok']) $msg = $res['msg'];
    else $erro = $res['msg'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = resend_verification($_POST['email'] ?? '');
    if ($res['ok']) $msg = $res['msg'];
    else $erro = $res['msg'];
}
$page_title = 'Verificar e-mail — OctoArt';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell" style="min-height:70vh">
  <div class="auth-form-col" style="margin:0 auto;max-width:420px;padding:40px 16px">
    <div class="auth-box">
      <h2 style="margin-top:0">Verificação de e-mail</h2>
      <?php if ($msg): ?><div class="success-msg"><?= h($msg) ?></div><?php endif; ?>
      <?php if ($erro): ?><div class="error-msg show"><?= h($erro) ?></div><?php endif; ?>
      <?php if (!$token): ?>
      <form method="post">
        <div class="field"><label>E-mail</label><input type="email" name="email" required value="<?= h($_GET['email'] ?? '') ?>"></div>
        <button class="btn btn-primary btn-block" type="submit">Reenviar confirmação</button>
      </form>
      <?php endif; ?>
      <p class="form-note"><a href="login.php">Ir para o login</a></p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
