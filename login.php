<?php
require __DIR__ . '/inc/bootstrap.php';

if (pessoa_atual()) voltar(url());

$erro = '';
$email = '';
$volta = destino_seguro((string)($_GET['volta'] ?? $_POST['volta'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');
    $pdo = db();
    if (!csrf_ok()) {
        $erro = 'A página expirou. Tente de novo.';
    } else {
        $st = $pdo->prepare('SELECT * FROM pessoas WHERE email = ? AND ativo = 1');
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && $u['bloqueado_ate'] && strtotime($u['bloqueado_ate']) > time()) {
            $erro = 'Muitas tentativas erradas. Espere 15 minutos ou peça ao ' . gestor() . ' para criar uma senha nova.';
        } elseif ($u && $u['senha_hash'] && password_verify($senha, $u['senha_hash'])) {
            $pdo->prepare('UPDATE pessoas SET falhas = 0, bloqueado_ate = NULL WHERE id = ?')->execute([$u['id']]);
            iniciar_sessao((int)$u['id']);
            voltar($u['senha_provisoria'] ? url('senha.php') : $volta);
        } else {
            if ($u) {
                $pdo->prepare('UPDATE pessoas SET falhas = falhas + 1, bloqueado_ate = IF(falhas >= 5, NOW() + INTERVAL 15 MINUTE, bloqueado_ate) WHERE id = ?')->execute([$u['id']]);
            }
            $erro = 'E-mail ou senha incorretos.';
        }
    }
}

topo('Entrar', '', false);
echo '<div class="card narrow"><p class="eyebrow">' . e(cliente()) . '</p><h1>Entrar</h1><p class="sub">Use o e-mail e a senha que o ' . gestor() . ' passou para você.</p>';
if ($erro !== '') echo '<div class="aviso erro" role="alert">' . e($erro) . '</div>';
echo '<form method="post">' . csrf_campo() . '<input type="hidden" name="volta" value="' . e($volta) . '">
<label for="email">E-mail</label>
<input id="email" type="email" name="email" value="' . e($email) . '" required autofocus autocomplete="username" autocapitalize="none" inputmode="email">
<label for="senha">Senha</label>
<input id="senha" type="password" name="senha" required autocomplete="current-password">
<p class="form-acoes"><button class="btn" type="submit">Entrar</button></p></form>
<p class="sm muted">Esqueceu a senha? Peça ao ' . gestor() . ' pelo WhatsApp: ele cria uma nova na hora. ' . ajuda('login') . '</p>'
    . (admin_sem_senha() ? '<p class="sm"><a href="' . e(url('primeiro-acesso.php')) . '">Primeiro acesso do administrador</a></p>' : '') . '</div>';
rodape();
