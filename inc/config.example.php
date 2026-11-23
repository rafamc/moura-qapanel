<?php
// Copie este arquivo para inc/config.php e preencha. O config.php NÃO vai para o Git.
// Uma instalação por cliente: cada uma com seu banco, seu config.php e seu deploy.
return [
    // Banco e servidor
    'db_host'   => 'localhost',
    'db_name'   => 'NOME_DO_BANCO',
    'db_user'   => 'USUARIO_DO_BANCO',
    'db_pass'   => 'SENHA_DO_BANCO',
    'api_token' => 'GERE_COM: openssl rand -hex 20',
    'base_url'  => '/painel',            // caminho público onde o painel fica no domínio ('' se estiver na raiz do subdomínio)
    'timezone'  => 'America/Sao_Paulo',

    // Identidade: tudo o que aparece na tela vem daqui
    'cookie'         => 'cliente',                         // prefixo dos cookies; use um valor diferente em cada instalação
    'marca'          => 'CLIENTE',                         // sigla/nome no topo e no favicon (primeira letra)
    'marca_em'       => '',                                // segunda parte da marca, em destaque (ex.: a sigla da cidade). Pode ficar vazio
    'subtitulo'      => 'Painel do projeto',
    'cliente'        => 'Nome completo do cliente',        // rodapé e tela de login
    'cliente_curto'  => 'Cliente',                         // como a equipe do cliente é chamada no admin e em "A vez é de"
    'gestor'         => 'Rafa',                            // quem o cliente chama quando trava ("Avisar o Rafa")
    'gestor_empresa' => 'Moura Digital',
    'cores' => [
        'primary'  => '#1f2a44',   // cor principal (topo, botões)
        'primary2' => '#2d3d63',   // tom mais claro da principal
        'destaque' => '#ffc641',   // detalhe (dourado, laranja...)
    ],

    // Módulos: false desliga o módulo (some do menu e a página redireciona). Sem a chave, fica ligado.
    'modulos' => [
        'reunioes'    => true,
        'decidido'    => true,
        'historico'   => true,
        'guia'        => true,
        'arquivos'    => true,
        'relatos'     => true,   // o cliente relata bug, ajuste ou dúvida (vira tarefa)
        'engajamento' => true,   // registro de uso, "A vez é de" e Desempacar (só o admin vê os números)
    ],

    // Relatos do cliente: em quantos dias a gestão responde (prazo da tarefa criada). Padrão 3.
    'url_publica' => '', // opcional: endereço do painel usado no WhatsApp (ex.: https://engnear.rafaelmoura.net)
    'relato_prazo_dias' => 3,

    // Transparência: aparece no rodapé enquanto o módulo "engajamento" estiver ligado. Combine o mesmo texto no contrato.
    'aviso_registro' => 'Este painel registra acessos e ações nas tarefas para acompanhar o andamento do projeto.',
];
