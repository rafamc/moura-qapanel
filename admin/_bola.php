<?php
// Painel "De quem é a vez": tempo que as tarefas ficam paradas com cada lado. Incluído por _engajamento.php (só admin).
if (empty($eu['is_admin'])) { http_response_code(403); exit; }
try {
    $res = $pdo->query("SELECT bola, COUNT(*) AS n, SUM(fim IS NULL) AS agora,
        ROUND(AVG(TIMESTAMPDIFF(HOUR, inicio, COALESCE(fim, NOW()))) / 24, 1) AS media,
        ROUND(SUM(TIMESTAMPDIFF(HOUR, inicio, COALESCE(fim, NOW()))) / 24, 1) AS total
        FROM bola_log GROUP BY bola")->fetchAll(PDO::FETCH_UNIQUE);
    $abertas = $pdo->query("SELECT t.id, t.titulo, t.bola_com, t.bola_desde FROM tarefas t WHERE t.bola_com IS NOT NULL ORDER BY t.bola_desde ASC LIMIT 8")->fetchAll();
} catch (Throwable $ex) {
    echo '<div class="aviso erro"><strong>As tabelas de "De quem é a vez" ainda não existem.</strong> Rode o <code>schema.sql</code> (tabela bola_log e colunas bola_com/bola_desde).</div>';
    return;
}
echo '<div class="card"><div class="cab"><h2>De quem é a vez</h2>' . ajuda('adm_bola') . '</div>';
if (!$res) echo '<p class="muted">Ainda sem dados. Os números aparecem conforme as tarefas mudam de mão.</p>';
else {
    echo '<div class="tabela-wrap"><table class="tabela"><tr><th>Lado</th><th>Vezes com a bola</th><th>Tempo médio por vez</th><th>Tempo total</th><th>Paradas agora</th></tr>';
    foreach (LADOS as $l) {
        $r = $res[$l] ?? ['n' => 0, 'agora' => 0, 'media' => '—', 'total' => '—'];
        echo '<tr><td><strong>' . e(bola_nome($l)) . '</strong></td><td>' . (int)$r['n'] . '</td><td>' . ($r['media'] === '—' ? '—' : e(str_replace('.', ',', (string)$r['media'])) . ' dias') . '</td><td>' . ($r['total'] === '—' ? '—' : e(str_replace('.', ',', (string)$r['total'])) . ' dias') . '</td><td>' . (int)$r['agora'] . '</td></tr>';
    }
    echo '</table></div>';
}
echo '<p class="sm muted">Mede tempo parado, não esforço. Sempre compare os dois lados: se o tempo com a ' . e(gestor_empresa()) . ' também é alto, o gargalo não é só do cliente. Conta só a partir da instalação.</p>';
if ($abertas) {
    echo '<h3 style="margin-top:14px">Paradas há mais tempo</h3><div class="tabela-wrap"><table class="tabela"><tr><th>Tarefa</th><th>Com quem</th><th>Há</th></tr>';
    foreach ($abertas as $t) {
        $d = (int)floor((time() - strtotime((string)$t['bola_desde'])) / 86400);
        echo '<tr><td><a href="' . e(url('tarefa.php?id=' . (int)$t['id'])) . '">' . e($t['titulo']) . '</a></td><td>' . e(bola_nome((string)$t['bola_com'])) . '</td><td>' . ($d < 1 ? 'menos de 1 dia' : $d . ($d === 1 ? ' dia' : ' dias')) . '</td></tr>';
    }
    echo '</table></div>';
}
echo '</div>';
