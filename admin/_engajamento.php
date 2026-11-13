<?php
// Aba "Engajamento" do admin — etapa 1: observações privadas por pessoa e por tarefa.
// Incluída por admin/index.php, que já passou por exigir_admin() e traz $pdo, $H, $eu e $pessoas no escopo.
if (empty($eu['is_admin'])) { http_response_code(403); exit; }

try { $pdo->query('SELECT 1 FROM observacoes LIMIT 1'); $obsOk = true; } catch (Throwable $ex) { $obsOk = false; }
if (!$obsOk) {
    echo '<div class="aviso erro"><strong>A tabela das observações ainda não existe.</strong> Rode <code>migrations/005_observacoes.sql</code> no phpMyAdmin.</div>';
    return;
}

// Só quem é do lado do cliente e não é admin entra na lista.
$cliLista = array_values(array_filter($pessoas, fn($x) => $x['lado'] === 'cliente' && !$x['is_admin']));
if (!$cliLista) { echo '<div class="card vazio"><strong>Nenhuma pessoa do cliente cadastrada.</strong></div>'; return; }

$pSel = (int)($_GET['p'] ?? 0);
$atual = null;
foreach ($cliLista as $x) if ((int)$x['id'] === $pSel) $atual = $x;
if (!$atual) { $atual = $cliLista[0]; $pSel = (int)$atual['id']; }
$tFiltro = (int)($_GET['tf'] ?? 0);

echo '<div class="cab"><h2>Engajamento</h2>' . ajuda('adm_engajamento') . '</div>';
echo '<p class="sm muted">Área privada: só você vê. Nada daqui aparece para o cliente.</p>';
require __DIR__ . '/_bola.php';
require __DIR__ . '/_engajamento_numeros.php';
echo '<nav class="seg adm-filtros" aria-label="Pessoas">';
foreach ($cliLista as $x) {
    echo '<a href="?v=engajamento&amp;p=' . (int)$x['id'] . '"' . ((int)$x['id'] === $pSel ? ' class="on" aria-current="page"' : '') . '>' . e(primeiro_nome($x['nome'])) . '</a>';
}
echo '</nav>';

$linha = eng_linha_tempo($pSel);
echo '<details class="card"><summary><strong>Linha do tempo de ' . e(primeiro_nome($atual['nome'])) . ' (' . count($linha) . ')</strong> ' . ajuda('adm_eng_linha') . '</summary>';
if (!$linha) echo '<p class="muted">Ainda não há registros dessa pessoa.</p>';
foreach ($linha as $l) echo '<div class="sm" style="border-top:1px solid var(--line,#e5e5e5);padding:8px 0"><strong>' . e(dt($l['q'], true)) . '</strong> · ' . e($l['txt']) . '</div>';
echo '</details>';

// Tarefas da pessoa (para ligar a observação a uma tarefa, se for o caso)
[$fMin, $pMin] = filtro_minhas($pSel);
$q = $pdo->prepare("SELECT t.id, t.titulo, t.prazo, t.status FROM tarefas t WHERE $fMin ORDER BY (t.status = 'feito'), t.prazo DESC, t.id DESC");
$q->execute($pMin);
$tarefasP = $q->fetchAll();

echo '<div class="adm-split"><div class="adm-form"><div class="card"><h2>Nova observação sobre ' . e(primeiro_nome($atual['nome'])) . ' ' . ajuda('adm_obs') . '</h2>';
echo '<form method="post">' . $H('obs_nova', ['pessoa_id' => $pSel]);
echo '<label for="obs-tarefa">Sobre qual tarefa? (opcional)</label><select id="obs-tarefa" name="tarefa_id"><option value="">— geral, sem tarefa —</option>';
foreach ($tarefasP as $t) {
    echo '<option value="' . (int)$t['id'] . '"' . ((int)$t['id'] === $tFiltro ? ' selected' : '') . '>' . e($t['titulo'] . ' · ' . dt($t['prazo']) . ' · ' . STATUS[$t['status']]) . '</option>';
}
echo '</select>';
echo '<label for="obs-texto">O que você observou</label><textarea id="obs-texto" name="texto" required maxlength="2000" placeholder="Só o fato. Ex.: visualizou e não respondeu · respondeu só com agrado, sem executar"></textarea>';
echo '<p class="sm muted">A data e a hora entram sozinhas. Depois de salva, a observação não é editada; só pode ser apagada.</p>';
echo '<p class="form-acoes"><button class="btn" type="submit">Salvar observação</button></p></form></div></div>';

echo '<div class="adm-lista">';
$sql = 'SELECT o.*, t.titulo AS titulo_atual FROM observacoes o LEFT JOIN tarefas t ON t.id = o.tarefa_id WHERE o.pessoa_id = ?'
     . ($tFiltro ? ' AND o.tarefa_id = ?' : '') . ' ORDER BY o.criado_em DESC, o.id DESC';
$q = $pdo->prepare($sql);
$q->execute($tFiltro ? [$pSel, $tFiltro] : [$pSel]);
$obs = $q->fetchAll();

echo '<div class="card"><h2>Observações sobre ' . e(primeiro_nome($atual['nome'])) . ' (' . count($obs) . ')</h2>';
if ($tFiltro) echo '<p class="sm">Mostrando só uma tarefa. <a href="?v=engajamento&amp;p=' . $pSel . '">Ver todas</a></p>';
if (!$obs) echo '<p class="muted">Nenhuma observação ainda.</p>';
foreach ($obs as $o) {
    echo '<div style="border-top:1px solid var(--linha,#e5e5e5);padding:12px 0"><div class="cab"><strong>' . e(dt($o['criado_em'], true)) . '</strong>';
    if ($o['tarefa_id'] && $o['titulo_atual'] !== null) {
        echo '<span class="sm">Tarefa: <a href="' . e(url('tarefa.php?id=' . (int)$o['tarefa_id'])) . '">' . e($o['titulo_atual']) . '</a> · <a href="?v=engajamento&amp;p=' . $pSel . '&amp;tf=' . (int)$o['tarefa_id'] . '">só esta</a></span>';
    } elseif ($o['tarefa_titulo']) {
        echo '<span class="sm muted">Tarefa (apagada depois): ' . e($o['tarefa_titulo']) . '</span>';
    }
    echo '</div><p style="margin:6px 0 10px">' . nl2br(e($o['texto'])) . '</p>'
       . '<form method="post" class="inline" onsubmit="return confirm(\'Apagar esta observação? Não dá para desfazer.\')">' . $H('obs_apagar', ['id' => (int)$o['id'], 'pessoa_id' => $pSel])
       . '<button class="btn perigo mini" type="submit">Apagar</button></form></div>';
}
echo '</div></div></div>';
