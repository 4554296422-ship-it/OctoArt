<?php
$cats = [];
try {
    $cats = db()->query('SELECT * FROM categorias ORDER BY nome LIMIT 5')->fetchAll();
} catch (Throwable $e) {}
?>
<footer>
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-brand">
        <a class="brand" href="index.php"><img src="<?= h($app_base ?? '') ?>assets/img/perfil.png" alt="" class="mascot-fixed foot-mascot"><span>OctoArt</span></a>
        <p>A comunidade onde criadores mostram seus trabalhos e encontram novos projetos.</p>
      </div>
      <div>
        <h5>Plataforma</h5>
        <ul>
          <li><a href="explorar.php?tipo=artistas">Conhecer a comunidade</a></li>
          <li><a href="explorar.php?tipo=projetos">Projetos abertos</a></li>
          <li><a href="login.php?cadastro=1">Publicar meu trabalho</a></li>
        </ul>
      </div>
      <div>
        <h5>Categorias</h5>
        <ul>
          <?php foreach ($cats as $c): ?>
            <li><a href="explorar.php?tipo=artistas&categoria=<?= (int)$c['id_categoria'] ?>"><?= h($c['nome']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h5>Conta</h5>
        <ul>
          <li><a href="login.php">Entrar</a></li>
          <li><a href="dashboard.php">Meu perfil</a></li>
          <li><a href="mensagens.php">Mensagens</a></li>
        </ul>
      </div>
    </div>
    <div class="foot-bottom">
    </div>
  </div>
</footer>
<script src="<?= h($app_base ?? '') ?>assets/js/social.js?v=<?= @filemtime(__DIR__ . '/../assets/js/social.js') ?: time() ?>"></script>
<script src="assets/js/cidade_suggest.js"></script>
<script src="assets/js/telefone_mask.js?v=1"></script>
</body>
</html>
