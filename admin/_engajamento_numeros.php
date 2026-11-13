<?php
// Aba Engajamento — etapa 3: números e datas por pessoa, alertas e limites. Só leitura (exceto o formulário dos limites).
// Incluída por _engajamento.php, que já checou admin e traz $pdo, $H, $eu, $pessoas e $cliLista no escopo.
require_once __DIR__ . '/../inc/engajamento.php';

$incluir = !empty($_GET['inc']);
$alvo = $incluir ? $pessoas : $cliLista;
$ev = eng_tabela('eventos');
$inicio = eng_coleta();
$lim = eng_limites();
$linhas = []; $todosAlertas = [];
foreach ($alvo as $x) {
    $r = eng_resumo($x, $inicio, $lim);
    $linhas[] = [$x, $r];
    foreach ($r['alertas'] as $a) $todosAlertas[(int)$x['id']][] = $a;
}
$aceIds = array_map(fn($x) => (int)$x['id'], $cliLista);
$sem = fn($d) => $d ? e(dt($d, true)) : '<span class="muted">—</span>';
$tempo = function (array $m) { return $m['n'] ? e(eng_duracao((int)$m['media'])) . ' <span class="muted sm">(n=' . (int)$m['n'] . ')</span>' : '<span class="muted">—</span>'; };

echo '<div class="card"><div class="cab"><h2>Números por pessoa ' . ajuda('adm_eng_numeros') . '</h2>';
echo '<a class="btn sec mini" href="?v=engajamento' . ($incluir ? '' : '&amp;inc=1') . '">' . ($incluir ? 'Só o pessoal do cliente' : 'Incluir minhas ações') . '</a></div>';
if (!$ev) {
    echo '<div class="aviso erro"><strong>A tabela dos eventos ainda não existe.</strong> Rode <code>migrations/007_eventos.sql</code> no phpMyAdmin.</div>';
} elseif ($inicio) {
    echo '<p class="sm">Registro automático desde <strong>' . e(dt($inicio, true)) . '</strong>. Antes dessa data não existe dado de acesso, então “sem abrir” e “sem acesso” só contam a partir dela.</p>';
} else {
    echo '<p class="sm muted">Ainda não há nenhum registro. Os números aparecem assim que alguém usar o painel.</p>';
}
echo '<div class="eng-wrap"><table class="tabela eng-tab"><thead><tr><th>Pessoa</th><th>Último acesso</th><th>Última ação</th><th>Em aberto</th><th>Abriu</th><th>Feitas</th><th>Atrasadas</th><th>Até abrir ' . ajuda('adm_eng_tempo') . '</th><th>Até agir</th><th>Últimos 14 dias</th></tr></thead><tbody>';
foreach ($linhas as [$x, $r]) {
    $nome = in_array((int)$x['id'], $aceIds, true) ? '<a href="?v=engajamento&amp;p=' . (int)$x['id'] . ($incluir ? '&amp;inc=1' : '') . '">' . e(primeiro_nome($x['nome'])) . '</a>' : e(primeiro_nome($x['nome'])) . ' <span class="muted sm">(você)</span>';
    echo '<tr><td><strong>' . $nome . '</strong></td><td>' . $sem($r['ultimo_acesso']) . '</td><td>' . $sem($r['ultima_acao']) . '</td>';
    echo '<td>' . (int)$r['abertas'] . '</td><td>' . ($r['abertas'] ? (int)$r['abriu_abertas'] . ' de ' . (int)$r['abertas'] : '<span class="muted">—</span>') . '</td>';
    echo '<td>' . (int)$r['feitas'] . '</td><td>' . (int)$r['atrasadas'] . '</td><td>' . $tempo($r['abrir']) . '</td><td>' . $tempo($r['resp']) . '</td><td><span class="eng-dias" role="img" aria-label="' . (int)$r['ativos14'] . ' de 14 dias com atividade">';
    foreach ($r['quadrados'] as $q) echo '<i class="eng-d ' . e($q['s']) . '" title="' . e(dt($q['d'])) . ($q['s'] === 'on' ? ' · com atividade' : ($q['s'] === 'antes' ? ' · antes da coleta' : ' · sem atividade')) . '"></i>';
    echo '</span> <span class="sm muted">' . (int)$r['ativos14'] . '/14</span></td></tr>';
}
echo '</tbody></table></div>';
echo '<p class="sm muted">“Abriu” é abrir a página da tarefa, não ler. “Até abrir” e “Até agir” só usam tarefas recebidas depois do início da coleta; o <em>n</em> é quantas entraram na média.</p></div>';

echo '<div class="adm-split"><div class="adm-lista"><div class="card"><div class="cab"><h2>Alertas ' . ajuda('adm_eng_alertas') . '</h2></div>';
if (!$todosAlertas) echo '<p class="muted">Nenhum alerta com os limites atuais.</p>';
foreach ($linhas as [$x, $r]) {
    if (empty($todosAlertas[(int)$x['id']])) continue;
    echo '<div style="border-top:1px solid var(--line,#e5e5e5);padding:10px 0"><strong>' . e(primeiro_nome($x['nome'])) . '</strong><ul style="margin:6px 0 0 18px">';
    foreach ($todosAlertas[(int)$x['id']] as $a) echo '<li>' . eng_alerta_texto($a) . '</li>';
    echo '</ul></div>';
}
echo '</div></div><div class="adm-form"><div class="card"><h2>Limites dos alertas</h2>';
if (!eng_tabela('ajustes')) echo '<p class="sm muted">Usando os limites padrão. Para poder mudar, rode <code>migrations/008_ajustes.sql</code> no phpMyAdmin.</p>';
echo '<form method="post">' . $H('ajuste_salvar');
echo '<label for="lim-na">Atribuída e ainda não aberta, a partir de (dias)</label><input id="lim-na" type="number" name="lim_nao_abriu" min="1" max="90" value="' . (int)$lim['nao_abriu'] . '" required>';
echo '<label for="lim-sa">Aberta e sem nenhuma ação, a partir de (dias)</label><input id="lim-sa" type="number" name="lim_sem_acao" min="1" max="90" value="' . (int)$lim['sem_acao'] . '" required>';
echo '<label for="lim-sc">Sem acessar o painel, a partir de (dias)</label><input id="lim-sc" type="number" name="lim_sem_acesso" min="1" max="90" value="' . (int)$lim['sem_acesso'] . '" required>';
echo '<p class="form-acoes"><button class="btn" type="submit">Salvar limites</button></p></form></div></div></div>';