-- OctoArt v4 — imagens guardadas no banco de dados
-- Opcional: o site cria esta tabela sozinho no primeiro upload.
-- Depois de rodar, abra migrar_imagens.php (como administrador) para levar
-- as imagens antigas da pasta uploads/ para o banco.

CREATE TABLE IF NOT EXISTS imagens_arquivos (
  id CHAR(32) NOT NULL PRIMARY KEY,
  mime VARCHAR(40) NOT NULL,
  tamanho INT NOT NULL,
  dados MEDIUMBLOB NOT NULL,
  id_usuario INT NULL,
  data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_imagens_arquivos_usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
