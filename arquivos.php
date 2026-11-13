<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/arquivos.php';
exigir_login();
exigir_modulo('arquivos');
topo('Arquivos', 'arquivos');
echo '<div class="cab"><h1>Arquivos</h1>' . ajuda('arquivos') . '</div><p class="sub">Logos, Media Kit e outros materiais do projeto, prontos para baixar.</p>';
$grupos = arquivos_agrupados();
if (!$grupos) {
    echo '<div class="card vazio"><strong>Ainda não há arquivos.</strong>Quando houver logos, Media Kit ou outros materiais, eles aparecem aqui para baixar.</div>';
}
foreach ($grupos as $cat => $lista) {
    echo '<div class="card"><div class="cab"><h2>' . e($cat) . '</h2></div><ul class="galeria">';
    foreach ($lista as $a) {
        $id = (int)$a['id'];
        $baixar = e(url('arquivo.php?id=' . $id));
        $img = in_array($a['mime'], ARQ_VER, true);
        $ext = strtoupper(pathinfo($a['nome_original'], PATHINFO_EXTENSION));
        echo '<li class="gi">';
        if ($img) echo '<a class="gi-foto" href="' . e(url('arquivo.php?id=' . $id . '&ver=1')) . '" target="_blank" rel="noopener" aria-label="Ver ' . e($a['titulo']) . '"><img loading="lazy" src="' . e(url('arquivo.php?id=' . $id . '&ver=1')) . '" alt="' . e($a['titulo']) . '"></a>';
        else echo '<a class="gi-foto gi-ico" href="' . $baixar . '" aria-label="Baixar ' . e($a['titulo']) . '"><b>' . e($ext !== '' ? $ext : 'ARQ') . '</b><span>Baixar</span></a>';
        echo '<div class="gi-info"><strong title="' . e($a['titulo']) . '">' . e($a['titulo']) . '</strong>'
           . ($a['descricao'] ? '<span>' . e($a['descricao']) . '</span>' : '')
           . '<span>' . e(anexo_tamanho((int)$a['tamanho'])) . ' · ' . e($ext) . '</span></div>'
           . '<div class="gi-acoes"><a class="btn mini" href="' . $baixar . '">Baixar</a></div></li>';
    }
    echo '</ul></div>';
}
rodape();
