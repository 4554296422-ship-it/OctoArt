<?php
require_once __DIR__ . '/includes/auth.php';
if ($cu = current_user()) {
    header('Location: ' . (is_admin($cu) ? 'admin.php' : 'index.php'));
    exit;
}

$tab = isset($_GET['cadastro']) ? 'cadastro' : 'login';
$erro = '';
$ok = '';
$auth_flash = $_SESSION['auth_flash'] ?? '';
unset($_SESSION['auth_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'login';
    if ($acao === 'login') {
        $res = login_user($_POST['email'] ?? '', $_POST['senha'] ?? '');
        if (!empty($res['ok']) && !empty($res['need_2fa'])) {
            header('Location: verificar_2fa.php');
            exit;
        }
        if (!empty($res['ok'])) {
            header('Location: ' . (is_admin($res['user']) ? 'admin.php' : 'index.php'));
            exit;
        }
        $erro = $res['msg'] ?? 'Falha no login.';
        if (!empty($res['need_verify'])) {
            $erro .= ' <a href="verificar_email.php?email=' . urlencode($res['email'] ?? '') . '">Reenviar confirmação</a>';
        }
        $tab = 'login';
    } else {
        $res = register_user([
            'nome' => $_POST['nome'] ?? '',
            'email' => $_POST['email'] ?? '',
            'senha' => $_POST['senha'] ?? '',
            'telefone' => $_POST['telefone'] ?? '',
            'cidade' => $_POST['cidade'] ?? '',
        ]);
        if (!empty($res['ok'])) {
            // entra direto no painel após cadastrar
            if (!empty($res['auto_login']) || !empty($res['user'])) {
                header('Location: index.php');
                exit;
            }
            $ok = $res['msg'] ?? 'Conta criada! Faça login.';
            $tab = 'login';
        } else {
            $erro = $res['msg'] ?? 'Falha no cadastro.';
            $tab = 'cadastro';
        }
    }
}

$page_title = 'Entrar — OctoArt';
$active = '';
require __DIR__ . '/includes/header.php';
$app_base = $app_base ?? '';
?>
<div class="auth-shell">
  <div class="auth-side">
    <img src="<?= h($app_base) ?>assets/img/perfil.png" alt="OctoArt" class="mascot-fixed" onerror="this.style.display='none'">
    <h2>Bem-vindo(a) ao OctoArt.</h2>
    <p style="opacity:.85">Publique trabalhos, monte grades de fotos e converse com a comunidade.</p>
  </div>
  <div class="auth-form-col">
    <div class="auth-box">
      <div class="tabs">
        <button type="button" class="<?= $tab==='login'?'active':'' ?>" onclick="location.href='login.php'">Entrar</button>
        <button type="button" class="<?= $tab==='cadastro'?'active':'' ?>" onclick="location.href='login.php?cadastro=1'">Criar conta</button>
      </div>

      <?php if ($auth_flash): ?><div class="error-msg show"><?= h($auth_flash) ?></div><?php endif; ?>
      <?php if ($erro): ?><div class="error-msg show"><?= $erro ?></div><?php endif; ?>
      <?php if ($ok): ?><div class="success-msg"><?= h($ok) ?></div><?php endif; ?>

      <?php if ($tab === 'login'): ?>
      <form method="post">
        <input type="hidden" name="acao" value="login">
        <div class="field"><label>E-mail</label><input type="email" name="email" required placeholder="voce@email.com" value="<?= h($_POST['email'] ?? '') ?>"></div>
        <div class="field"><label>Senha</label><input type="password" name="senha" required></div>
        <button class="btn btn-primary btn-block" type="submit">Entrar</button>
        <p class="form-note" style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px">
          <a href="recuperar_senha.php">Esqueci a senha</a>
          <a href="verificar_email.php">Confirmar e-mail</a>
        </p>
      </form>
      <div style="margin:16px 0;text-align:center;color:var(--grey-600);font-size:.85rem">ou</div>
      <a href="google_auth.php" class="btn btn-outline btn-block" style="display:flex;align-items:center;justify-content:center;gap:8px">
        <span>G</span> Continuar com Google
      </a>
      
      <?php else: ?>
      <form method="post">
        <input type="hidden" name="acao" value="cadastro">
        <div class="field"><label>Nome</label><input type="text" name="nome" required></div>
        <div class="field"><label>E-mail</label><input type="email" name="email" required></div>
        <div class="field"><label>Senha</label><input type="password" name="senha" required minlength="6"></div>
        <div class="field"><label>Cidade <span class="muted">(opcional)</span></label><input type="text" name="cidade" data-city placeholder="Digite o estado ou cidade"></div>
        <div class="field"><label>Telefone <span class="muted">(opcional)</span></label><input type="tel" name="telefone" placeholder="(48) 984490605" inputmode="numeric" autocomplete="tel" maxlength="15"></div>
        <button class="btn btn-primary btn-block" type="submit">Criar minha conta</button>
        <p class="form-note">Enviaremos um link para confirmar o e-mail.</p>
      </form>
      <div style="margin:16px 0;text-align:center;color:var(--grey-600);font-size:.85rem">ou</div>
      <a href="google_auth.php" class="btn btn-outline btn-block">Cadastrar com Google</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="assets/js/cidade_suggest.js"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
