<?php
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/anexos.php';
$eu = exigir_login();
$pdo = db();
$st = $pdo->prepare('SELECT t.*, p.nome AS responsavel, r.titulo AS reuniao_titulo FROM tarefas t JOIN pessoas p ON p.id = t.responsavel_id LEFT JOIN reunioes r ON r.id = t.reuniao_id WHERE t.id = ?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$t = $st->fetch();
topo($t ? $t['titulo'] : 'Tarefa', 'index');
if (!$t) { echo '<div class="card vazio"><strong>Tarefa não encontrada.</strong><a href="' . e(url()) . '">Voltar</a></div>'; rodape(); exit; }

$t = com_equipe([$t])[0];
if (in_array((int)$eu['id'], $t['equipe_ids'], true)) evento('abriu_tarefa', (int)$eu['id'], (int)$t['id']); // só quem é da equipe da tarefa
[$rot, $cls] = prazo_info($t['prazo'], $t['status']);
$meu = pode_agir($t, $eu);
$volta = url('tarefa.php?id=' . $t['id']);
$hid = '<input type="hidden" name="tarefa_id" value="' . (int)$t['id'] . '"><input type="hidden" name="volta" value="' . e($volta) . '">';
$act = e(url('acao.php'));

echo '<p class="sm"><a class="voltar" href="' . e(url()) . '">← Voltar</a></p>';
echo '<div class="duas"><div>';
echo '<div class="card"><div class="cab"><h1>' . e($t['titulo']) . '</h1>' . ajuda('tarefa') . '</div>';
echo '<div class="meta"><span class="resp">' . ($t['participantes'] ? 'Responsáveis: ' : 'Responsável: ') . equipe_html($t, true) . ($t['participantes'] ? ' ' . ajuda('equipe') : '') . '</span><span>·</span><span>Prazo ' . e(dt($t['prazo'])) . ' ' . ajuda('prazo') . '</span>';
if ($rot) echo '<span class="badge ' . e($cls) . '">' . e($rot) . '</span>';
echo '<span class="badge ' . e($t['status']) . '">' . e(STATUS[$t['status']]) . '</span>' . bola_badge($t) . '</div>';
if ($t['descricao']) echo '<p>' . nl2br(e($t['descricao'])) . '</p>';
if ($t['reuniao_titulo']) echo '<p class="sm muted">Combinada em: ' . e($t['reuniao_titulo']) . '</p>';

if ($meu) {
    echo '<div class="acoes">';
    if ($t['status'] === 'feito') {
        echo '<form method="post" action="' . $act . '">' . csrf_campo() . $hid . '<input type="hidden" name="acao" value="reabrir"><button class="btn sec" type="submit">Reabrir</button></form>';
    } else {
        echo '<form method="post" action="' . $act . '" class="inline">' . csrf_campo() . $hid . '<input type="hidden" name="acao" value="feito"><button class="btn feita" type="submit">✓ Feita</button></form>' . ajuda('feita');
        if ($t['status'] !== 'travado') {
            echo '<details class="mini"><summary class="btn warn">Travou?</summary><form method="post" action="' . $act . '">' . csrf_campo() . $hid . '<input type="hidden" name="acao" value="travado"><label for="trt">O que está impedindo? ' . ajuda('travou') . '</label><textarea id="trt" name="texto" required></textarea><button class="btn warn" type="submit" style="margin-top:8px">Avisar o ' . gestor() . '</button></form></details>';
        }
    }
    echo '</div>';
}
echo '</div>';
$anx = anexos_da_tarefa((int)$t['id']);
echo '<div class="card"><div class="cab"><h2>Arquivos ' . ajuda('anexos') . '</h2></div>' . anexos_lista_html($anx, $eu, $volta);
if ($meu) echo '<form method="post" action="' . $act . '" enctype="multipart/form-data" class="form-anexo">' . csrf_campo() . $hid . '<input type="hidden" name="acao" value="anexar">' . anexos_campo_html() . '<button class="btn" type="submit" data-envia-anexos style="margin-top:10px">Enviar arquivos</button></form>';
echo '</div>';
echo '</div><div>';

echo '<div class="card"><h2>Comentar ' . ajuda('comentar') . '</h2><form method="post" action="' . $act . '">' . csrf_campo() . $hid . '<input type="hidden" name="acao" value="comentar"><textarea name="texto" required aria-label="Seu comentário" placeholder="Escreva aqui..."></textarea><button class="btn sec" type="submit" style="margin-top:8px">Salvar comentário</button></form></div>';

$h = $pdo->prepare('SELECT c.*, p.nome FROM comentarios c JOIN pessoas p ON p.id = c.pessoa_id WHERE c.tarefa_id = ? ORDER BY c.created_at DESC, c.id DESC');
$h->execute([$t['id']]);
echo '<div class="card"><h2>Histórico ' . ajuda('historico') . '</h2><ul class="linha-tempo">';
foreach ($h as $c) {
    echo '<li class="' . e($c['tipo']) . '"><div class="quando">' . e(dt($c['created_at'], true)) . '</div>' . avatar($c['nome'], (int)$c['pessoa_id'], 'sm') . '<strong>' . e(primeiro_nome($c['nome'])) . '</strong> ' . ($c['tipo'] === 'status' ? e($c['texto']) : '<br>' . nl2br(e($c['texto']))) . '</li>';
}
echo '</ul></div>';
echo '</div></div>';
rodape();
