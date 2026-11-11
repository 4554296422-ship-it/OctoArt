# OctoArt — PHP + MySQL (XAMPP)

Site completo em **PHP** com banco **MySQL**, pronto para rodar no XAMPP.

## Instalação no Windows (XAMPP)

### 1. Copiar a pasta
Coloque a pasta `octoart` em:

```text
C:\xampp\htdocs\octoart
```

### 2. Ligar o XAMPP
Abra o **XAMPP Control Panel** e clique em **Start** em:
- Apache
- MySQL

### 3. Criar o banco
1. Abra: http://localhost/phpmyadmin
2. Clique em **Importar**
3. Escolha o arquivo: `sql/install.sql`
4. Clique em **Executar**

Isso cria o banco `octoart_bd` com tabelas, categorias e contas demo.

### 4. Abrir o site
No navegador:

**http://localhost/octoart/**

---

## Contas demo

Não existe mais "artista" ou "cliente": toda conta pode publicar trabalhos e pedir projetos.

| E-mail | Senha |
|--------|--------|
| artista@demo.com | octoart123 |
| cliente@demo.com | octoart123 |

---

## O que vai para o banco MySQL

- Login e cadastro → tabela `usuarios`
- Categorias → `categorias`
- Publicações (trabalhos e pedidos) → `projetos`
- Mensagens → `mensagens`
- Pedidos / propostas → `propostas`
- Foto de perfil e imagens → pasta `uploads/` + caminho no banco

## Categorias

1. Ilustração  
2. Design Gráfico  
3. Fotografia  
4. Web Design e UI/UX  
5. Modelagem 3D  

## Se der erro de conexão

Edite `config/db.php`:

```php
define('DB_USER', 'root');
define('DB_PASS', ''); // senha do MySQL no XAMPP (geralmente vazia)
```

## Administrador

| E-mail | Senha |
|--------|--------|
| Octoartadm@admin.com | 123456 |

Painel: http://localhost/octoart/admin.php

Se o banco já existir, rode também `sql/update_admin.sql` e, para esta versão (conta única + perfil com cidade/bio), `sql/update_v2_conta_unica.sql` no phpMyAdmin. Nenhum dado é apagado.

## Regras da versão atual

- Cadastro único: nome, e-mail, senha, cidade e telefone (opcionais).
- Qualquer conta comum pode fazer uma **publicação**; marcando "Estou procurando alguém" ela vira um pedido aberto com orçamento.
- Perfil com foto, cidade, bio, Instagram e site.
- Ninguém envia mensagem para o administrador; avisos dele chegam como mensagem somente leitura.

## Denúncias, curtidas e comentários

- Qualquer membro logado denuncia uma publicação pelo menu **•••** do post (ou na página da publicação). O autor não pode denunciar a própria obra e cada pessoa denuncia uma vez.
- O administrador gerencia tudo em **Admin → 🚩 Denúncias**: remover (oculta a publicação e avisa autor e denunciantes), manter, restaurar ou excluir definitivamente.
- Curtir, comentar e denunciar funcionam **sem recarregar** a página (`acoes.php` + `assets/js/social.js`).
- Cada post tem botão **Mensagem** para falar com o autor.
- Toda conta nova aparece na aba **Comunidade** automaticamente.
- Banco já existente: rode `sql/update_v3_denuncias.sql` (opcional — o site cria a tabela sozinho).

## Novidades (editor, segurança, Google)

### Criar projeto com grades
Abra `criar_projeto.php` — blocos de texto, foto única e grade 2/3/4 fotos.

### Segurança
- Senhas com `password_hash` (bcrypt)
- Confirmação de e-mail no cadastro
- Recuperar senha
- 2FA opcional (código no e-mail)
- Logs de e-mail em `storage/mail_log/` (XAMPP)

### Google Login
Em `config/app.php` preencha:
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `APP_URL` (ex: http://localhost/octoart)

Redirect URI no Google Cloud: `http://localhost/octoart/google_callback.php`

### Cidades
Campo cidade com sugestões de estados/cidades do Brasil.

### SQL
Rode `sql/update_security.sql` se precisar (o site também tenta criar as colunas sozinho).
