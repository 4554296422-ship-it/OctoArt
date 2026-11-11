<?php
require_once __DIR__ . '/includes/auth.php';
if (empty($_SESSION['pending_2fa'])) { header('Location: login.php'); exit; }
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = verify_2fa_code($_POST['codigo'] ?? '');
    if ($res['ok']) {
        $u = $res['user'];
        header('Location: ' . (is_admin($u) ? 'admin.php' : 'index.php'));
        exit;
    }
    $erro = $res['msg'];
}
$page_title = 'Verificação em duas etapas — OctoArt';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-shell" style="min-height:70vh">
  <div class="auth-form-col" style="margin:0 auto;max-width:420px;padding:40px 16px">
    <div class="auth-box">
      <h2 style="margin-top:0">Código do e-mail</h2>
      <p class="muted">Enviamos um código de 6 dígitos. No XAMPP veja também <code>storage/mail_log/</code>.</p>
      <?php if ($erro): ?><div class="error-msg show"><?= h($erro) ?></div><?php endif; ?>
      <form method="post">
        <div class="field"><label>Código</label><input type="text" name="codigo" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required placeholder="000000" style="letter-spacing:6px;font-size:1.3rem;text-align:center"></div>
        <button class="btn btn-primary btn-block" type="submit">Confirmar</button>
      </form>
      <p class="form-note"><a href="logout.php">Cancelar</a></p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
