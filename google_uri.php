<?php
/**
 * Página de diagnóstico do Login Google
 * Abra: http://localhost/octoart/google_uri.php
 */
require_once __DIR__ . '/config/app.php';
$uri = google_redirect_uri();
$base = app_base_url();
$hasId = GOOGLE_CLIENT_ID !== '';
$hasSecret = GOOGLE_CLIENT_SECRET !== '';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Diagnóstico Google — OctoArt</title>
  <style>
    body{font-family:system-ui,sans-serif;max-width:640px;margin:40px auto;padding:0 16px;line-height:1.5}
    code,pre{background:#f4f0ff;padding:2px 6px;border-radius:6px;word-break:break-all}
    pre{padding:12px;display:block}
    .ok{color:#0a7}.bad{color:#c00}
    .box{border:1px solid #ddd;border-radius:12px;padding:16px;margin:16px 0}
    li{margin:8px 0}
  </style>
</head>
<body>
  <h1>Diagnóstico Login Google</h1>
  <div class="box">
    <p><strong>URI que o OctoArt está enviando ao Google:</strong></p>
    <pre id="uri"><?= htmlspecialchars($uri) ?></pre>
    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('uri').innerText)">Copiar URI</button>
  </div>
  <div class="box">
    <p>APP_URL: <code><?= htmlspecialchars($base) ?></code></p>
    <p>Client ID: <span class="<?= $hasId?'ok':'bad' ?>"><?= $hasId ? 'configurado' : 'VAZIO — preencha config/app.php' ?></span></p>
    <p>Client Secret: <span class="<?= $hasSecret?'ok':'bad' ?>"><?= $hasSecret ? 'configurado' : 'VAZIO — preencha config/app.php' ?></span></p>
  </div>
  <h2>Como corrigir o erro 400 redirect_uri_mismatch</h2>
  <ol>
    <li>Abra o <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud → Credenciais</a></li>
    <li>Clique no seu <strong>ID do cliente OAuth 2.0</strong> (tipo Aplicativo da Web)</li>
    <li>Em <strong>URIs de redirecionamento autorizados</strong>, adicione <em>exatamente</em> a URI de cima (copie e cole)</li>
    <li>Salve e espere 1–2 minutos</li>
    <li>Entre no site com o <strong>mesmo host</strong> da URI:
      <ul>
        <li>Se a URI usa <code>localhost</code>, abra <code>http://localhost/octoart/...</code></li>
        <li>Se usa <code>127.0.0.1</code>, abra <code>http://127.0.0.1/octoart/...</code></li>
        <li>Não misture os dois</li>
      </ul>
    </li>
  </ol>
  <p><a href="login.php">← Voltar ao login</a></p>
</body>
</html>
