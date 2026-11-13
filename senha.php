<?php
require __DIR__ . '/inc/bootstrap.php';
$eu = exigir_login();
$provisoria = !empty($eu['senha_provisoria']);
$precisaAtual = !empty($eu['senha_hash']) && !$provisoria;
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $atual = (string)($_POST['atual'] ?? '');
    $nova  = (string)($_POST['nova'] ?? '');
    $conf  = (string)($_POST['conf'] ?? '');
    if (!csrf_ok()) $erro = 'A página expirou. Tente de novo.';
    elseif (empty($eu['email'])) $erro = 'Seu e-mail ainda não foi cadastrado. Peça ao ' . gestor() . '.';
    elseif ($precisaAtual && !password_verify($atual, (string)$eu['senha_hash'])) $erro = 'A senha atual não confere.';
    elseif (mb_strlen($nova) < 4) $erro = 'A nova senha precisa ter pelo menos 4 caracteres.';
    elseif ($nova !== $conf) $erro = 'As duas senhas novas não são iguais.';
    elseif ($provisoria && password_verify($nova, (string)$eu['senha_hash'])) $erro = 'Escolha uma senha diferente da provisória.';
    else {
        $pdo = db();
        $pdo->prepare('UPDATE pessoas SET senha_hash = ?, senha_provisoria = 0, falhas = 0, bloqueado_ate = NULL WHERE id = ?')
            ->execute([password_hash($nova, PASSWORD_DEFAULT), $eu['id']]);
        $atualHash = hash('sha256', (string)($_COOKIE[cookie_nome('s')] ?? ''));
        $pdo->prepare('DELETE FROM sessoes WHERE pessoa_id = ? AND token_hash <> ?')->execute([$eu['id'], $atualHash]);
        aviso('Senha salva. Da próxima vez, entre com o seu e-mail e essa senha.');
        voltar(url());
    }
}

topo('Senha', '', !$provisoria);
echo '<div class="card narrow"><h1>' . ($provisoria ? 'Crie a sua senha' : 'Trocar senha') . '</h1>';
echo '<p class="sub">' . ($provisoria ? 'A senha que o ' . gestor() . ' passou é provisória. Escolha uma só sua, com pelo menos 4 caracteres.' : 'Escolha uma senha com pelo menos 4 caracteres.') . '</p>';
if (empty($eu['email'])) echo '<div class="aviso erro">Seu e-mail ainda não foi cadastrado. Peça ao ' . gestor() . ' antes de criar a senha.</div>';
if ($erro !== '') echo '<div class="aviso erro" role="alert">' . e($erro) . '</div>';
echo '<form method="post">' . csrf_campo();
if ($precisaAtual) echo '<label for="atual">Senha atual</label><input id="atual" type="password" name="atual" required autocomplete="current-password">';
echo '<label for="nova">Nova senha</label><input id="nova" type="password" name="nova" required minlength="4" autocomplete="new-password">
<label for="conf">Repita a nova senha</label><input id="conf" type="password" name="conf" required minlength="4" autocomplete="new-password">
<p class="form-acoes"><button class="btn" type="submit">Salvar senha</button></p></form></div>';
rodape();
