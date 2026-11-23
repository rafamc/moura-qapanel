-- 001: status "Em validação", tipo do item (bug, sugestão...), resultado "ignorado" e celular da pessoa.
-- Rode no phpMyAdmin (aba SQL) ANTES de publicar o código. Rodar só uma vez.
ALTER TABLE tarefas
  MODIFY status ENUM('a_fazer','fazendo','travado','feito','validacao') NOT NULL DEFAULT 'a_fazer',
  ADD COLUMN tipo ENUM('tarefa','bug','sugestao','duvida','ajuste') NOT NULL DEFAULT 'tarefa' AFTER titulo,
  ADD COLUMN resultado ENUM('ignorado') NULL AFTER status;

ALTER TABLE pessoas
  ADD COLUMN celular VARCHAR(20) NULL AFTER email;

-- Relatos antigos do cliente tinham o tipo só no título ("[Bug] ..."): passa para a coluna e tira o prefixo (a etiqueta já mostra o tipo).
UPDATE tarefas SET tipo = 'bug',    titulo = SUBSTRING(titulo, 7)  WHERE titulo LIKE '[Bug] %';
UPDATE tarefas SET tipo = 'ajuste', titulo = SUBSTRING(titulo, 10) WHERE titulo LIKE '[Ajuste] %';
UPDATE tarefas SET tipo = 'duvida', titulo = SUBSTRING(titulo, 10) WHERE titulo LIKE '[Dúvida] %';
