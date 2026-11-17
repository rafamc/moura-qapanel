# Painel do projeto — modelo Moura Digital

Um código, uma instalação por cliente (banco, `config.php` e deploy próprios). Stack: PHP 8 puro + MySQL, sem build.

## Instalar para um cliente novo
1. Crie um banco MySQL vazio no servidor.
2. Rode `schema.sql` e depois `seed.sql` (phpMyAdmin). O seed cria só o admin, sem senha.
3. Copie `inc/config.example.php` para `inc/config.php` e preencha: banco, `base_url`, `cookie` (único por instalação), marca, cores, módulos e `api_token` (`openssl rand -hex 20`).
4. Abra `/primeiro-acesso.php`, informe a senha do banco (ou o `api_token`) como chave, e defina e-mail e senha do admin. A página some depois.
5. Em Admin > Pessoas, cadastre a equipe do cliente (lado "cliente") e a sua (lado "Moura").
6. Admin > Importar aceita um JSON de reunião (reunião, decisões, tarefas, páginas) com "Validar" antes de gravar.

Use `.htaccess` do Apache (Hostinger ok). Sem Apache, bloqueie `inc/`, `uploads/` e `*.sql` no servidor.

## O que vem do config.php
Marca e sigla, cores, nome do cliente, nome do gestor, prefixo dos cookies e **módulos ligáveis**:
`reunioes`, `decidido`, `historico`, `guia`, `arquivos`, `engajamento`. Módulo `false` some do menu e redireciona.
Com `engajamento` ligado, o rodapé mostra o aviso de registro (`aviso_registro`). Use o mesmo texto no contrato.

## Medição (só o admin vê)
- **A vez é de**: cada tarefa tem a bola com "cliente" ou "moura". Começa pelo lado do responsável; "Travou" passa para o outro lado; "Feita" tira de jogo. Tempo parado por lado fica em Admin > Engajamento. Mede tempo parado, não esforço: compare sempre os dois lados.
- **Desempacar**: registre a ação (data, tipo de problema, o que fez) e marque se funcionou. Mostra o tempo médio parado com o cliente antes e depois da data. É ensaio, sem grupo de controle.
- **Engajamento**: observações privadas e log de acessos/ações por pessoa.

## Mudanças de banco
`schema.sql` é a verdade. Mudança nova: `migrations/NNN_nome.sql` (rodar no phpMyAdmin ANTES do push) e atualizar o `schema.sql` no mesmo commit.

## Ainda não existe
- Endpoint de números agregados e anônimos para comparar clientes (desenhado, não construído).
- Cada instalação é independente: correção de bug precisa ser aplicada em cada deploy (mesmo repositório, `git pull`).

## Relatos do cliente (bug, ajuste, dúvida)

O cliente tem a aba **Relatar problema** (`relatar.php`, módulo `relatos`). Cada relato vira uma tarefa: título com prefixo `[Bug]`, `[Ajuste]` ou `[Dúvida]`, responsável é o primeiro admin ativo, a bola começa com a gestão, e o prazo é de resposta (`relato_prazo_dias`, padrão 3). Quem relatou entra como participante, então acompanha, marca como feita ou reabre se o retesto falhar. Limite de 15 relatos por pessoa por hora. Não exige mudança no banco.
