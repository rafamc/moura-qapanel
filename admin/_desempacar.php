<?php
// Aba "Desempacar": ações que você tomou para destravar o projeto, com data e resultado (sim/não). Incluída por admin/index.php.
if (empty($eu['is_admin'])) { http_response_code(403); exit; }
try { $acoes = $pdo->query('SELECT * FROM acoes_desempacar ORDER BY data DESC, id DESC')->fetchAll(); }
catch (Throwable $ex) { echo '<div class="aviso erro"><strong>A tabela acoes_desempacar ainda não existe.</strong> Rode o <code>schema.sql</code>.</div>'; return; }
$tipos = ['Decisão lenta', 'Retorno de teste demorado', 'Falta de conteúdo', 'Escopo crescendo', 'Aprovação travada', 'Comunicação fora do painel'];
// média de dias parado COM O CLIENTE, só dos períodos já encerrados, antes e depois de uma data
$media = function (string $op, string $d) use ($pdo): array {
    try {
        $q = $pdo->prepare("SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, inicio, fim)) / 24, 1) AS m, COUNT(*) AS n FROM bola_log WHERE bola = 'cliente' AND fim IS NOT NULL AND DATE(fim) $op ?");
        $q->execute([$d]); $r = $q->fetch();
        return [$r['m'] === null ? null : str_replace('.', ',', (string)$r['m']), (int)$r['n']];
    } catch (Throwable $ex) { return [null, 0]; }
};
echo '<div class="adm-split"><div class="adm-form"><div class="card"><div class="cab"><h2>Nova ação</h2>' . ajuda('adm_desempacar') . '</div>';
echo '<form method="post">' . $H('desempacar_novo');
echo '<div class="grid2"><div><label for="dz-d">Data da ação</label><input id="dz-d" type="date" name="data" required value="' . date('Y-m-d') . '"></div>';
echo '<div><label for="dz-t">Tipo de problema</label><input id="dz-t" type="text" name="tipo" list="dz-tipos" required></div></div>';
echo '<datalist id="dz-tipos">' . implode('', array_map(fn($t) => '<option value="' . e($t) . '">', $tipos)) . '</datalist>';
echo '<label for="dz-x">O que você fez</label><textarea id="dz-x" name="descricao" required placeholder="Ex.: passei a pedir decisão por pergunta de sim ou não, com prazo de 2 dias"></textarea>';
echo '<p class="form-acoes"><button class="btn" type="submit">Registrar</button></p></form></div></div><div class="adm-lista">';
if (!$acoes) echo '<div class="card vazio"><strong>Nenhuma ação registrada.</strong>Registre o que você muda para destravar e depois marque se funcionou.</div>';
foreach ($acoes as $a) {
    [$ma, $na] = $media('<', $a['data']); [$md, $nd] = $media('>=', $a['data']);
    $rot = ['em_andamento' => 'Em andamento', 'sim' => 'Desempacou', 'nao' => 'Não desempacou'][$a['resultado']];
    echo '<div class="card"><div class="cab"><strong>' . e($a['tipo_problema']) . '</strong><span class="muted sm">' . e(dt($a['data'])) . ' · ' . e($rot) . '</span></div><p>' . nl2br(e($a['descricao'])) . '</p>';
    echo '<p class="sm">Tempo médio parado com ' . e(cliente_curto()) . ': antes ' . ($ma === null ? '—' : e($ma) . ' dias (n=' . $na . ')') . ' · depois ' . ($md === null ? '—' : e($md) . ' dias (n=' . $nd . ')') . '. <span class="muted">Ensaio: sem grupo de controle, com poucos casos é indício, não prova.</span></p>';
    if ($a['nota']) echo '<p class="sm muted">Nota: ' . nl2br(e($a['nota'])) . '</p>';
    echo '<form method="post" class="inline">' . $H('desempacar_resultado', ['id' => (int)$a['id']]) . '<input type="text" name="nota" placeholder="Nota (opcional)" style="width:220px;display:inline-block;padding:5px 8px"> '
       . '<button class="btn mini" name="resultado" value="sim">Desempacou</button> <button class="btn sec mini" name="resultado" value="nao">Não</button> <button class="btn sec mini" name="resultado" value="em_andamento">Em andamento</button></form> '
       . $apagar('desempacar_apagar', (int)$a['id']) . '</div>';
}
echo '</div></div>';
