<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) { header('Location: dashboard.php'); exit; }
$msg = $erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = request_password_reset($_POST['email'] ?? '');
    $msg = $res['msg'];
}
$page_title = 'Recuperar senha — OctoArt';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell" style="min-height:70vh">
  <div class="auth-form-col" style="margin:0 auto;max-width:420px;padding:40px 16px">
    <div class="auth-box">
      <h2 style="margin-top:0">Recuperar senha</h2>
      <p class="muted">Enviaremos um link para o seu e-mail. No XAMPP, o link também aparece em <code>storage/mail_log/</code>.</p>
      <?php if ($msg): ?><div class="success-msg"><?= h($msg) ?></div><?php endif; ?>
      <form method="post">
        <div class="field"><label>E-mail</label><input type="email" name="email" required></div>
        <button class="btn btn-primary btn-block" type="submit">Enviar link</button>
      </form>
      <p class="form-note"><a href="login.php">Voltar ao login</a></p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
