-- Painel do projeto (modelo Moura Digital) — schema completo e atual (MySQL/MariaDB, utf8mb4)
-- Instalação nova: rode este arquivo UMA vez num banco vazio, depois o seed.sql.
-- Mudanças futuras: crie migrations/NNN_nome.sql E atualize este arquivo, para ele continuar sendo a verdade.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pessoas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(190) NULL UNIQUE,
  senha_hash VARCHAR(255) NULL,
  senha_provisoria TINYINT(1) NOT NULL DEFAULT 0,
  falhas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_ate DATETIME NULL,
  papel VARCHAR(120) NOT NULL DEFAULT '',
  lado ENUM('cliente','moura') NOT NULL DEFAULT 'cliente',
  token CHAR(40) NOT NULL UNIQUE,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reunioes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  data DATE NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  resumo MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tarefas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(250) NOT NULL,
  descricao TEXT NULL,
  responsavel_id INT UNSIGNED NOT NULL,
  prazo DATE NOT NULL,
  status ENUM('a_fazer','fazendo','travado','feito') NOT NULL DEFAULT 'a_fazer',
  bola_com ENUM('cliente','moura') NULL,          -- de quem é a vez (NULL = feita)
  bola_desde DATETIME NULL,
  reuniao_id INT UNSIGNED NULL,
  concluida_em DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_resp_status (responsavel_id, status),
  KEY idx_prazo (prazo),
  CONSTRAINT fk_tarefa_resp FOREIGN KEY (responsavel_id) REFERENCES pessoas(id),
  CONSTRAINT fk_tarefa_reuniao FOREIGN KEY (reuniao_id) REFERENCES reunioes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico: comentários e mudanças de status da tarefa
CREATE TABLE IF NOT EXISTS comentarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tarefa_id INT UNSIGNED NOT NULL,
  pessoa_id INT UNSIGNED NOT NULL,
  tipo ENUM('comentario','status') NOT NULL DEFAULT 'comentario',
  texto TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_tarefa (tarefa_id, created_at),
  CONSTRAINT fk_com_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE,
  CONSTRAINT fk_com_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS decisoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  texto TEXT NOT NULL,
  data DATE NOT NULL,
  reuniao_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dec_reuniao FOREIGN KEY (reuniao_id) REFERENCES reunioes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documentação do projeto (conteúdo em markdown simples)
CREATE TABLE IF NOT EXISTS paginas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  secao VARCHAR(80) NOT NULL DEFAULT 'Projeto',
  titulo VARCHAR(200) NOT NULL,
  conteudo MEDIUMTEXT NOT NULL,
  ordem INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pessoa_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  criada_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em DATETIME NOT NULL,
  CONSTRAINT fk_sessoes_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico do projeto: linha do tempo em linguagem simples, visível para o cliente
CREATE TABLE IF NOT EXISTS historico (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  data DATE NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  descricao TEXT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_data (data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anexos das tarefas
CREATE TABLE IF NOT EXISTS anexos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tarefa_id INT UNSIGNED NOT NULL,
  pessoa_id INT UNSIGNED NOT NULL,
  nome_original VARCHAR(255) NOT NULL,
  arquivo CHAR(32) NOT NULL UNIQUE,
  mime VARCHAR(100) NOT NULL,
  tamanho INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_anexo_tarefa (tarefa_id),
  CONSTRAINT fk_anexo_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE,
  CONSTRAINT fk_anexo_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Observações privadas do admin: só o admin lê e escreve
CREATE TABLE IF NOT EXISTS observacoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pessoa_id INT UNSIGNED NOT NULL,
  tarefa_id INT UNSIGNED NULL,
  tarefa_titulo VARCHAR(250) NULL,
  texto TEXT NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_obs_pessoa (pessoa_id, criado_em),
  KEY idx_obs_tarefa (tarefa_id),
  CONSTRAINT fk_obs_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id) ON DELETE CASCADE,
  CONSTRAINT fk_obs_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quem também participa de uma tarefa, além do responsável
CREATE TABLE IF NOT EXISTS tarefa_pessoas (
  tarefa_id INT UNSIGNED NOT NULL,
  pessoa_id INT UNSIGNED NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tarefa_id, pessoa_id),
  KEY idx_tp_pessoa (pessoa_id),
  CONSTRAINT fk_tp_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE,
  CONSTRAINT fk_tp_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eventos de engajamento (só o admin vê)
CREATE TABLE IF NOT EXISTS eventos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pessoa_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(20) NOT NULL,            -- login | acesso | abriu_tarefa | abriu_reuniao | atribuida
  tarefa_id INT UNSIGNED NULL,
  reuniao_id INT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pessoa_tipo (pessoa_id, tipo, criado_em),
  KEY idx_tarefa_tipo (tarefa_id, tipo),
  CONSTRAINT fk_ev_pessoa  FOREIGN KEY (pessoa_id)  REFERENCES pessoas(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ev_tarefa  FOREIGN KEY (tarefa_id)  REFERENCES tarefas(id)  ON DELETE CASCADE,
  CONSTRAINT fk_ev_reuniao FOREIGN KEY (reuniao_id) REFERENCES reunioes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limites dos alertas de engajamento
CREATE TABLE IF NOT EXISTS ajustes (
  chave VARCHAR(40) NOT NULL PRIMARY KEY,
  valor VARCHAR(100) NOT NULL,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO ajustes (chave, valor) VALUES ('eng_nao_abriu', '3'), ('eng_sem_acao', '5'), ('eng_sem_acesso', '7');

-- De quem é a vez: cada período em que a tarefa ficou com um lado (início e fim). Alimenta os números do admin.
CREATE TABLE IF NOT EXISTS bola_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tarefa_id INT UNSIGNED NOT NULL,
  bola ENUM('cliente','moura') NOT NULL,
  inicio DATETIME NOT NULL,
  fim DATETIME NULL,
  KEY idx_bola_tarefa (tarefa_id, fim),
  KEY idx_bola_lado (bola, fim),
  CONSTRAINT fk_bola_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Arquivos do projeto para o cliente baixar (pack de logo, Media Kit...)
CREATE TABLE IF NOT EXISTS arquivos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoria VARCHAR(80) NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  descricao VARCHAR(300) NULL,
  nome_original VARCHAR(255) NOT NULL,
  arquivo CHAR(32) NOT NULL UNIQUE,
  mime VARCHAR(100) NOT NULL,
  tamanho INT UNSIGNED NOT NULL,
  ordem INT NOT NULL DEFAULT 0,
  pessoa_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_arq_cat (categoria, ordem),
  CONSTRAINT fk_arq_pessoa FOREIGN KEY (pessoa_id) REFERENCES pessoas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ações para desempacar o projeto: data, o que foi feito e se funcionou (só o admin vê)
CREATE TABLE IF NOT EXISTS acoes_desempacar (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  data DATE NOT NULL,
  tipo_problema VARCHAR(80) NOT NULL,
  descricao TEXT NOT NULL,
  resultado ENUM('em_andamento','sim','nao') NOT NULL DEFAULT 'em_andamento',
  avaliado_em DATE NULL,
  nota TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
