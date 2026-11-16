<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/migrar_uploads.php';
octoart_agendar_migracao_uploads(); // leva as imagens antigas de uploads/ para o banco, sozinho, em segundo plano
$u = current_user();
$active = $active ?? '';
$theme = $_COOKIE['octoart_theme'] ?? 'dark';
$app_base = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="<?= $theme === 'dark' ? 'dark' : '' ?>">
  <head>
<link rel="icon" type="image/png" href="<?= h($app_base) ?>assets/img/perfil.png">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($page_title ?? 'OctoArt') ?></title>
<link rel="stylesheet" href="<?= h($app_base) ?>assets/css/style.css?v=<?= file_exists(__DIR__ . '/../assets/css/style.css') ? filemtime(__DIR__ . '/../assets/css/style.css') : time() ?>">
<link rel="stylesheet" href="<?= h($app_base) ?>assets/css/feed.css?v=<?= file_exists(__DIR__ . '/../assets/css/feed.css') ? filemtime(__DIR__ . '/../assets/css/feed.css') : time() ?>">
</head>
<body>
<nav class="nav">
  <div class="wrap">
    <a class="brand" href="index.php">
      <img src="<?= h($app_base) ?>assets/img/<?= $theme === 'dark' ? 'perfil-noite.png' : 'perfil.png' ?>" alt="OctoArt" class="mascot-img">
      <span>OctoArt</span>
    </a>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php" class="<?= $active==='inicio'?'active':'' ?>">Início</a></li>
      <li><a href="explorar.php?tipo=artistas" class="<?= $active==='artistas'?'active':'' ?>">Comunidade</a></li>
      <li><a href="explorar.php?tipo=projetos" class="<?= $active==='projetos'?'active':'' ?>">Projetos</a></li>
      <?php if ($u): ?>
        <?php if (is_admin($u)): ?>
          <li><a href="admin.php" class="<?= $active==='admin'?'active':'' ?>">Admin</a></li>
        <?php else: ?>
          <li><a href="mensagens.php" class="<?= $active==='mensagens'?'active':'' ?>">Mensagens</a></li>
          <?php endif; ?>
      <?php endif; ?>
      <?php /* Itens que só aparecem dentro do menu no celular */ ?>
      <?php if ($u): ?>
        <li class="nav-only-mobile"><a href="<?= is_admin($u) ? 'admin.php' : 'dashboard.php' ?>">Minha conta</a></li>
        <li class="nav-only-mobile"><a href="logout.php">Sair</a></li>
      <?php else: ?>
        <li class="nav-only-mobile nav-cta"><a href="login.php?cadastro=1" class="btn btn-primary btn-block">Criar conta</a></li>
        <li class="nav-only-mobile"><a href="login.php">Entrar</a></li>
      <?php endif; ?>
    </ul>
    <div class="nav-actions">
      <?php if ($u):
        $notificacoes_nao_lidas = 0;
        try {
          $stNotificacoes = db()->prepare('SELECT COUNT(*) FROM notificacoes WHERE id_destinatario=? AND lida=0');
          $stNotificacoes->execute([(int)$u['id_usuario']]);
          $notificacoes_nao_lidas = (int)$stNotificacoes->fetchColumn();
        } catch (Throwable $e) {}
      ?>
        <a href="notificacoes.php" class="notification-nav <?= $active==='notificacoes'?'active':'' ?>" aria-label="Notificações" title="Notificações">
          <span class="notification-bell">✉️</span>
          <?php if ($notificacoes_nao_lidas > 0): ?><span class="notification-count"><?= $notificacoes_nao_lidas > 99 ? '99+' : $notificacoes_nao_lidas ?></span><?php endif; ?>
        </a>
      <?php endif; ?>
      <button class="theme-toggle" type="button" id="themeToggle" title="Alternar tema"><?= $theme==='dark'?'☀︎︎':'☾' ?></button>
      <?php if ($u): ?>
        <a href="<?= is_admin($u) ? 'admin.php' : 'dashboard.php' ?>" class="avatar-btn" title="Minha conta">
          <?php if (!empty($u['foto_perfil'])): ?>
            <span class="avatar-circle" style="background-image:url('<?= h(media_url($u['foto_perfil'])) ?>');background-size:cover;background-position:center"></span>
          <?php else: ?>
            <span class="avatar-circle"><?= h(initials($u['nome'])) ?></span>
          <?php endif; ?>
          <span style="font-size:.88rem;font-weight:600"><?= h(explode(' ', $u['nome'])[0]) ?></span>
        </a>
        <a href="logout.php" class="btn btn-ghost btn-sm nav-hide-mobile">Sair</a>
      <?php else: ?>
        <a href="login.php" class="btn btn-ghost btn-sm nav-hide-mobile">Entrar</a>
        <a href="login.php?cadastro=1" class="btn btn-primary btn-sm nav-hide-mobile">Criar conta</a>
      <?php endif; ?>
      <button class="nav-toggle" type="button" id="navToggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="navLinks"><span></span></button>
    </div>
  </div>
</nav>
<script>
(function(){
  const toggle = document.getElementById('navToggle');
  const menu = document.getElementById('navLinks');
  if (!toggle || !menu) return;
  function setOpen(open) {
    menu.classList.toggle('show', open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
  }
  toggle.addEventListener('click', function(e){ e.stopPropagation(); setOpen(!menu.classList.contains('show')); });
  menu.addEventListener('click', function(e){ if (e.target.closest('a')) setOpen(false); });
  document.addEventListener('click', function(e){ if (!e.target.closest('.nav')) setOpen(false); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') setOpen(false); });
  window.addEventListener('resize', function(){ if (window.innerWidth > 960) setOpen(false); });
})();
</script>
<script>
(function(){
  const btn = document.getElementById('themeToggle');
  if(!btn) return;
  btn.addEventListener('click', function(){
    const html = document.documentElement;
    const dark = html.classList.toggle('dark');
    document.cookie = 'octoart_theme=' + (dark ? 'dark' : 'light') + ';path=/;max-age=31536000';
    btn.textContent = dark ? '☀︎︎' : '☾';
    document.querySelectorAll('.mascot-img, .category-mascot').forEach(function(img){
      img.src = dark ? '<?= h($app_base) ?>assets/img/perfil-noite.png' : '<?= h($app_base) ?>assets/img/perfil.png';
    });
  });
})();
</script>
