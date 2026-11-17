-- Dados iniciais de uma instalação nova. Rode DEPOIS do schema.sql, uma vez.
-- Cria só o administrador (sem senha): abra /primeiro-acesso.php para definir e-mail e senha.
SET NAMES utf8mb4;

INSERT INTO pessoas (nome, papel, lado, token, is_admin)
VALUES ('Rafael Moura', 'Gestor do projeto', 'moura', LEFT(SHA1(CONCAT(RAND(), NOW(), UUID())), 40), 1);

-- Página padrão do Guia do projeto. Edite à vontade em Admin > Páginas.
INSERT INTO paginas (slug, secao, titulo, conteudo, ordem) VALUES ('como-usar', 'O projeto', 'Como usar o painel',
'## Em 4 passos

- **1. Veja o que é seu.** A primeira tela mostra só as tarefas que ficaram com você, com a data combinada.
- **2. Terminou? Toque em "Feita".** Se algo impediu você de continuar, toque em "Travou?" e conte o motivo. A gestão do projeto recebe o aviso e ajuda.
- **3. Precisa lembrar o que foi combinado?** Abra "Decidido". Tudo o que foi resolvido nas reuniões fica lá, com a data.
- **4. Achou um problema?** Abra "Relatar problema", descreva e cole um print com Ctrl+V.

## Três jeitos de ver suas tarefas

Na tela "Para mim", escolha como quer ver:

- **Lista:** uma tarefa embaixo da outra, com a data combinada.
- **Quadro:** colunas por situação (A fazer, Fazendo, Travou e Feitas). Arraste a tarefa de uma coluna para outra conforme avança.
- **Agenda:** o calendário do mês. Toque numa tarefa para ver os detalhes.

"Minhas" mostra só o que é seu. "Todas" mostra o projeto inteiro.

## Dentro de uma tarefa

- **Comentar:** escreva uma dúvida, um aviso ou o que você fez. Fica registrado para todos que participam da tarefa.
- **Arquivos:** envie fotos, PDFs e documentos. Aceita JPG, PNG, HEIC, PDF, Word, Excel e ZIP.
- **A vez é de:** cada tarefa mostra com quem está a bola: com você ou com a gestão do projeto.
- **Travou?** Conte o que está impedindo. A gestão recebe o aviso e ajuda a destravar.

## Achou um problema? Relate aqui

Na aba **Relatar problema**, conte o que encontrou. Pode ser um bug (algo que não funciona), um ajuste (algo para mudar) ou uma dúvida.

- **Título curto:** diga o essencial em uma frase. Ex.: "botão Enviar não funciona no celular".
- **Onde aconteceu:** o endereço da página ou o nome da tela. Ajuda a gente a achar mais rápido.
- **O que aconteceu:** o que você fez, o que esperava e o que viu. Se puder, diga o aparelho e o navegador.
- **Print de tela:** tire o print, clique na página e aperte **Ctrl+V** (no Mac, **Cmd+V**). Ele aparece na prévia e vai junto com o relato. No celular, toque para escolher a foto.

Cada relato vira uma tarefa com prazo de resposta. Você acompanha em "Para mim". Quando a gestão marcar como resolvida, teste de novo: se estiver certo, toque em **Feita**; se o problema continuar, toque em **Reabrir** e conte o que ainda acontece.

## Seu acesso

Entre com o seu e-mail e a senha que você recebeu; na primeira vez, o painel pede para você criar a sua. Para trocar depois, toque em "Senha", no alto da tela.

Os pontos de interrogação (?) espalhados pelas telas explicam, em uma frase, o que cada coisa significa.

O painel registra quando você entra e o que faz nas tarefas (abrir, comentar, marcar como feita) para acompanhar o andamento do projeto.', 0);
