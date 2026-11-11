# Login com Google — OctoArt

## 1. Google Cloud
1. Acesse https://console.cloud.google.com/apis/credentials
2. Crie um projeto
3. **Tela de consentimento OAuth** → Externo → preencha nome "OctoArt"
4. **Credenciais** → Criar → **ID do cliente OAuth** → Aplicativo da Web
5. Em **URIs de redirecionamento autorizados** adicione EXATAMENTE:
   ```
   http://localhost/octoart/google_callback.php
   ```
   (Se a pasta no htdocs tiver outro nome, troque `octoart`.)

## 2. Arquivo config/app.php
```php
define('GOOGLE_CLIENT_ID', 'xxxxx.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'GOCSPX-xxxxx');
```
Pode deixar `APP_URL` vazio — o site detecta sozinho.

## 3. Teste
Abra login → Continuar com Google → autorize.
Você recebe e-mail da OctoArt no e-mail Google (e cópia em `storage/mail_log/` no XAMPP).

## Senhas no banco
Todas as senhas ficam em bcrypt (`$2y$10$...`). Não dá para ler a senha original no phpMyAdmin.
