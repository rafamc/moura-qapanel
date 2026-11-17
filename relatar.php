<?php
// O cliente relata um bug, ajuste ou dúvida. Vira uma tarefa com prazo de resposta, com a "vez" do lado da gestão.
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/anexos.php';
$eu = exigir_login();
exigir_modulo('relatos');
$pdo = db();

const RELATO_TIPOS = ['bug' => 'Bug', 'ajuste' => 'Ajuste', 'duvida' => 'Dúvida'];
$erro = ''; $v = ['tipo' => 'bug', 'titulo' => '', 'onde' => '', 'descricao' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $erro = 'Os arquivos passaram do limite de envio do servidor. Envie menos arquivos ou arquivos menores.';
    } elseif (!csrf_ok()) {
        $erro = 'A página expirou. Recarregue e tente de novo.';
    } else {
        $v['tipo']      = array_key_exists($_POST['tipo'] ?? '', RELATO_TIPOS) ? (string)$_POST['tipo'] : 'bug';
        $v['titulo']    = trim((string)($_POST['titulo'] ?? ''));
        $v['onde']      = trim((string)($_POST['onde'] ?? ''));
        $v['descricao'] = trim((string)($_POST['descricao'] ?? ''));
        // Trava simples contra clique duplo ou abuso: no máximo 15 relatos por pessoa por hora.
        $recentes = $pdo->prepare("SELECT COUNT(*) FROM comentarios WHERE pessoa_id = ? AND tipo = 'status' AND texto = 'relatou o problema' AND created_at > NOW() - INTERVAL 1 HOUR");
        $recentes->execute([(int)$eu['id']]);
        if ($v['titulo'] === '') $erro = 'Dê um título curto ao relato.';
        elseif (mb_strlen($v['titulo']) > 150) $erro = 'O título passou de 150 caracteres. Deixe só o essencial e conte o resto na descrição.';
        elseif ($v['descricao'] === '') $erro = 'Conte o que aconteceu, para a ' . gestor_empresa() . ' conseguir reproduzir.';
        elseif ((int)$recentes->fetchColumn() >= 15) $erro = 'Você enviou muitos relatos na última hora. Espere um pouco e tente de novo.';
        else {
            $adm = (int)$pdo->query('SELECT id FROM pessoas WHERE is_admin = 1 AND ativo = 1 ORDER BY id LIMIT 1')->fetchColumn();
            if (!$adm) { $erro = 'Não há gestor ativo para receber o relato. Avise a ' . gestor_empresa() . '.'; }
            else {
                $dias = max(1, (int)opt('relato_prazo_dias', 3));
                $prazo = date('Y-m-d', strtotime("+$dias days"));
                $desc = ($v['onde'] !== '' ? "Onde: " . $v['onde'] . "\n\n" : '') . $v['descricao'];
                $pdo->prepare('INSERT INTO tarefas (titulo, descricao, responsavel_id, prazo) VALUES (?,?,?,?)')
                    ->execute(['[' . RELATO_TIPOS[$v['tipo']] . '] ' . $v['titulo'], $desc, $adm, $prazo]);
                $id = (int)$pdo->lastInsertId();
                if ((int)$eu['id'] !== $adm) salvar_equipe($id, $adm, [(int)$eu['id']], (int)$eu['id']); // quem relatou acompanha e pode confirmar ou reabrir
                definir_bola($id, 'moura');
                registrar($id, (int)$eu['id'], 'status', 'relatou o problema');
                $ax = anexos_salvar($id, (int)$eu['id'], $_FILES['anexos'] ?? []);
                aviso('Relato enviado. A ' . gestor_empresa() . ' responde até ' . dt($prazo) . '.' . anexos_resumo($ax), $ax['erros'] ? 'erro' : 'ok');
                voltar(url('tarefa.php?id=' . $id));
            }
        }
    }
}

topo('Relatar um problema', 'relatar');
echo '<div class="cab"><h1>Relatar um problema</h1></div>';
echo '<p class="sub">Achou um bug, quer um ajuste ou tem uma dúvida? Conte aqui. Vira uma tarefa com prazo, e você acompanha até ficar resolvida.</p>';
if ($erro) echo '<div class="aviso erro" role="alert">' . e($erro) . '</div>';
echo '<div class="card"><form method="post" enctype="multipart/form-data" class="form-relato">' . csrf_campo();
echo '<div class="linha-relato"><div><label for="tipo">O que é?</label><select id="tipo" name="tipo">';
foreach (RELATO_TIPOS as $k => $n) echo '<option value="' . $k . '"' . ($v['tipo'] === $k ? ' selected' : '') . '>' . e($n) . '</option>';
echo '</select></div>';
echo '<div><label for="titulo">Título curto</label><input type="text" id="titulo" name="titulo" maxlength="150" required value="' . e($v['titulo']) . '" placeholder="Ex.: botão Enviar não funciona no celular"></div></div>';
echo '<label for="onde">Onde aconteceu <span class="opc">(opcional)</span></label><input type="text" id="onde" name="onde" value="' . e($v['onde']) . '" placeholder="Endereço da página ou nome da tela">';
echo '<label for="descricao">O que aconteceu</label><textarea id="descricao" name="descricao" required placeholder="O que você fez, o que esperava e o que aconteceu. Se der, diga o aparelho e o navegador.">' . e($v['descricao']) . '</textarea>';
echo anexos_campo_html();
echo '<p class="form-acoes"><button class="btn" type="submit">Enviar relato</button></p></form></div>';
rodape();
