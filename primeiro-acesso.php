<?php
require __DIR__ . '/inc/bootstrap.php';

// Só existe enquanto o admin ainda não tem senha. Depois disso, a página some (404).
$alvo = admin_sem_senha();
if ($alvo === null) {
    http_response_code(404);
    topo('Indisponível', '', false);
    echo '<div class="card narrow"><h1>Página indisponível</h1><p><a href="' . e(url('login.php')) . '">Ir para o login</a></p></div>';
    rodape();
    exit;
}

$pdo = db();
$erro = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $chave = (string)($_POST['chave'] ?? '');
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $nova  = (string)($_POST['nova'] ?? '');
    $conf  = (string)($_POST['conf'] ?? '');
    $st = $pdo->prepare('SELECT falhas, bloqueado_ate FROM pessoas WHERE id = ?'); $st->execute([$alvo]); $est = $st->fetch();
    // chave válida: senha do banco ou api_token (se for um token de verdade), ambos do config.php
    $chavesOk = array_values(array_filter([(string)cfg('db_pass'), preg_match('/^[a-f0-9]{40}$/', (string)cfg('api_token')) ? (string)cfg('api_token') : '']));
    $chaveOk = false;
    foreach ($chavesOk as $k) { if (hash_equals($k, $chave)) $chaveOk = true; }

    if (!csrf_ok()) {
        $erro = 'A página expirou. Tente de novo.';
    } elseif ($est && $est['bloqueado_ate'] && strtotime($est['bloqueado_ate']) > time()) {
        $erro = 'Muitas tentativas erradas. Espere 15 minutos.';
    } elseif (!$chaveOk) {
        $pdo->prepare('UPDATE pessoas SET falhas = falhas + 1, bloqueado_ate = IF(falhas >= 5, NOW() + INTERVAL 15 MINUTE, bloqueado_ate) WHERE id = ?')->execute([$alvo]);
        usleep(1200000);
        $erro = 'Chave de instalação incorreta.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (mb_strlen($nova) < 4) {
        $erro = 'A senha precisa ter pelo menos 4 caracteres.';
    } elseif ($nova !== $conf) {
        $erro = 'As duas senhas não são iguais.';
    } else {
        try {
            $up = $pdo->prepare('UPDATE pessoas SET email = ?, senha_hash = ?, senha_provisoria = 0, falhas = 0, bloqueado_ate = NULL WHERE id = ? AND senha_hash IS NULL');
            $up->execute([$email, password_hash($nova, PASSWORD_DEFAULT), $alvo]);
            if ($up->rowCount() === 1) {
                iniciar_sessao($alvo);
                aviso('Pronto. Você já está dentro. Agora cadastre as pessoas e gere as senhas aqui no Admin.');
                voltar(url('admin/?v=pessoas'));
            }
            $erro = 'Não foi possível salvar. Recarregue a página.';
        } catch (PDOException $ex) {
            $erro = 'Esse e-mail já está em uso.';
        }
    }
}

topo('Primeiro acesso', '', false);
echo '<div class="card narrow"><h1>Primeiro acesso do administrador</h1>
<p class="sub">Crie o e-mail e a senha do administrador do painel. Esta página some assim que você terminar.</p>';
if ($erro !== '') echo '<div class="aviso erro" role="alert">' . e($erro) . '</div>';
echo '<form method="post">' . csrf_campo() . '
<label for="chave">Chave de instalação ' . ajuda('chave') . '</label>
<input id="chave" type="password" name="chave" required autocomplete="off">
<p class="sm muted" style="margin:4px 0 0">Use a senha do banco de dados ou o <code>api_token</code> que estão no <code>config.php</code> do servidor.</p>
<label for="email">Seu e-mail</label>
<input id="email" type="email" name="email" value="' . e($email) . '" required autocomplete="username" autocapitalize="none" inputmode="email">
<label for="nova">Crie uma senha</label>
<input id="nova" type="password" name="nova" required minlength="4" autocomplete="new-password">
<label for="conf">Repita a senha</label>
<input id="conf" type="password" name="conf" required minlength="4" autocomplete="new-password">
<p class="form-acoes"><button class="btn" type="submit">Criar acesso</button></p></form></div>';
rodape();
