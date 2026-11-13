<?php
require __DIR__ . '/inc/bootstrap.php';
$eu = exigir_login();
exigir_modulo('reunioes');
$pdo = db();
if (!empty($_GET['id'])) {
    $st = $pdo->prepare('SELECT * FROM reunioes WHERE id = ?'); $st->execute([(int)$_GET['id']]);
    $r = $st->fetch();
    topo($r ? $r['titulo'] : 'Reunião', 'reunioes');
    echo '<p class="sm"><a class="voltar" href="' . e(url('reunioes.php')) . '">← Todas as reuniões</a></p>';
    if (!$r) { echo '<div class="card vazio"><strong>Reunião não encontrada.</strong></div>'; rodape(); exit; }
    evento('abriu_reuniao', (int)$eu['id'], null, (int)$r['id']);
    $d = $pdo->prepare('SELECT texto FROM decisoes WHERE reuniao_id = ? ORDER BY id'); $d->execute([$r['id']]);
    $ds = $d->fetchAll();
    $t = $pdo->prepare('SELECT t.id, t.titulo, t.prazo, t.status, t.responsavel_id, p.nome, p.nome AS responsavel FROM tarefas t JOIN pessoas p ON p.id = t.responsavel_id WHERE t.reuniao_id = ? ORDER BY t.prazo'); $t->execute([$r['id']]);
    $ts = com_equipe($t->fetchAll());
    // O resumo vira blocos: o texto antes do 1º "##" fica no cabeçalho; cada "## Título" vira um cartão.
    $partes = preg_split('/^##\s+/m', str_replace(["\r\n", "\r"], "\n", trim((string)$r['resumo'])));
    $intro = trim((string)array_shift($partes));
    $secs = []; $temCombinado = false;
    foreach ($partes as $p) {
        $q = explode("\n", $p, 2);
        $tit = trim($q[0]); $low = mb_strtolower($tit);
        $tipo = str_contains($low, 'apresentad') ? 'apres' : (str_contains($low, 'combinad') || str_contains($low, 'decidid') ? 'comb' : (str_contains($low, 'confirmar') || str_contains($low, 'aberto') ? 'conf' : 'outro'));
        if ($tipo === 'comb') $temCombinado = true;
        $secs[] = ['t' => $tit, 'c' => trim($q[1] ?? ''), 'tipo' => $tipo];
    }
    $nDec = count($ds);
    echo '<header class="bloco reu-cab"><div><span class="badge">' . e(dt($r['data'])) . '</span><h1>' . e($r['titulo']) . '</h1>'
       . ($intro !== '' ? '<div class="prosa bloco-claro">' . md($intro) . '</div>' : '') . '</div>';
    if ($nDec || $ts) {
        echo '<div class="reu-contas">';
        if ($nDec) echo '<div class="conta-mini"><b>' . $nDec . '</b><span>' . ($nDec === 1 ? 'decisão' : 'decisões') . '</span></div>';
        if ($ts) echo '<div class="conta-mini"><b>' . count($ts) . '</b><span>' . (count($ts) === 1 ? 'tarefa' : 'tarefas') . '</span></div>';
        echo '</div>';
    }
    echo '</header><div class="reu-grade"><div>';
    $icones = ['apres' => ['tela', ''], 'comb' => ['check', 'verde'], 'conf' => ['ajuda', 'ouro'], 'outro' => ['livro', '']];
    foreach ($secs as $s) {
        [$ic, $cc] = $icones[$s['tipo']];
        echo '<section class="bloco reu-sec ' . $s['tipo'] . '"><div class="sec-cab"><span class="chip-ico ' . $cc . '">' . ico($ic) . '</span><h2>' . e($s['t']) . '</h2></div>' . md($s['c']) . '</section>';
    }
    if (!$secs && $intro === '') echo '<div class="card vazio"><strong>Sem resumo ainda.</strong></div>';
    if ($ds && !$temCombinado) {
        echo '<section class="bloco reu-sec"><div class="sec-cab"><span class="chip-ico verde">' . ico('check') . '</span><h2>Decidido nesta reunião</h2></div><ul>';
        foreach ($ds as $x) echo '<li>' . e($x['texto']) . '</li>';
        echo '</ul></section>';
    }
    echo '</div><aside>';
    if ($ts) {
        echo '<section class="bloco"><div class="sec-cab"><span class="chip-ico">' . ico('tarefa') . '</span><h2>' . (count($ts) === 1 ? 'Tarefa que saiu daqui' : 'Tarefas que saíram daqui') . '</h2></div>';
        foreach ($ts as $x) {
            [$rot, $cls] = prazo_info($x['prazo'], $x['status']);
            echo '<div class="reu-tarefa"><a href="' . e(url('tarefa.php?id=' . $x['id'])) . '">' . e($x['titulo']) . '</a><div class="meta"><span class="badge ' . e($x['status']) . '">' . e(STATUS[$x['status']]) . '</span>'
               . '<span class="resp">' . equipe_html($x) . '</span><span>' . e(dt($x['prazo'])) . ($rot ? ' · ' . e($rot) : '') . '</span></div></div>';
        }
        echo '</section>';
    }
    if ($nDec) echo '<p class="sm"><a href="' . e(url('decidido.php')) . '">Ver todas as decisões →</a></p>';
    echo '</aside></div>';
    rodape(); exit;
}
topo('Reuniões', 'reunioes');
echo '<div class="cab"><h1>Reuniões</h1>' . ajuda('reunioes') . '</div><p class="sub">O resumo de cada reunião e o que saiu dela.</p>';
$rows = $pdo->query('SELECT r.*, (SELECT COUNT(*) FROM decisoes d WHERE d.reuniao_id = r.id) AS n_dec, (SELECT COUNT(*) FROM tarefas t WHERE t.reuniao_id = r.id) AS n_tar FROM reunioes r ORDER BY r.data DESC, r.id DESC')->fetchAll();
if (!$rows) echo '<div class="card vazio"><strong>Nenhuma reunião registrada ainda.</strong>Depois de cada reunião, o resumo aparece aqui.</div>';
foreach ($rows as $r) {
    $ts = strtotime($r['data']);
    $sub = [];
    if ($r['n_dec']) $sub[] = $r['n_dec'] . ($r['n_dec'] == 1 ? ' decisão' : ' decisões');
    if ($r['n_tar']) $sub[] = $r['n_tar'] . ($r['n_tar'] == 1 ? ' tarefa' : ' tarefas');
    echo '<a class="bloco card-link reu-item" href="' . e(url('reunioes.php?id=' . $r['id'])) . '"><div class="cal-mini"><b>' . date('d', $ts) . '</b><span>' . MESES_ABR[(int)date('n', $ts) - 1] . '</span></div>'
       . '<div><strong>' . e($r['titulo']) . '</strong><div class="sm muted">' . e(dt($r['data'])) . ($sub ? ' · ' . e(implode(' · ', $sub)) : '') . '</div></div><span class="seta">' . ico('seta') . '</span></a>';
}
rodape();
