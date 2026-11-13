<?php
require __DIR__ . '/inc/bootstrap.php';
exigir_login();
exigir_modulo('decidido');
topo('Decidido', 'decidido');
echo '<div class="cab"><h1>Decidido</h1>' . ajuda('decidido') . '</div><p class="sub">O que já foi combinado. Não precisa ser discutido de novo.</p>';
$rows = db()->query('SELECT d.*, r.titulo AS rt FROM decisoes d LEFT JOIN reunioes r ON r.id = d.reuniao_id ORDER BY d.data DESC, d.id DESC')->fetchAll();
if (!$rows) echo '<div class="card vazio"><strong>Ainda não há decisões registradas.</strong>As decisões das reuniões aparecem aqui, com a data.</div>';
echo '<div class="lista-decisoes">';
foreach ($rows as $d) {
    echo '<div class="card decisao"><div class="dia">' . e(dt($d['data'])) . '</div><div><p style="margin:0">' . e($d['texto']) . '</p>' . ($d['rt'] ? '<p class="sm muted" style="margin:4px 0 0">Reunião: ' . e($d['rt']) . '</p>' : '') . '</div></div>';
}
echo '</div>';
rodape();
