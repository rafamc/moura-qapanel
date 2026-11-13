<?php
require __DIR__ . '/inc/bootstrap.php';
exigir_login();
exigir_modulo('historico');
topo('Histórico', 'historico');
echo '<div class="cab"><h1>Histórico</h1>' . ajuda('jornada') . '</div><p class="sub">Como o projeto foi acontecendo, do começo até hoje.</p>';
try {
    $rows = db()->query('SELECT * FROM historico ORDER BY data, id')->fetchAll();
} catch (Throwable $t) {
    $rows = null; // tabela ainda não criada (migration 002)
}
if (!$rows) {
    echo '<div class="card vazio"><strong>O histórico ainda está sendo montado.</strong>Os marcos do projeto vão aparecer aqui, em ordem.</div>';
} else {
    echo '<ol class="linha-tempo jornada">';
    foreach ($rows as $h) {
        echo '<li><div class="quando">' . e(dt($h['data'])) . '</div><strong>' . e($h['titulo']) . '</strong>'
           . ($h['descricao'] ? '<div class="prosa">' . md($h['descricao']) . '</div>' : '') . '</li>';
    }
    echo '</ol>';
}
rodape();
