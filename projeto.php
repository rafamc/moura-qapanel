<?php
require __DIR__ . '/inc/bootstrap.php';
exigir_login();
exigir_modulo('guia');
$pdo = db();
$paginas = $pdo->query('SELECT slug, secao, titulo, conteudo, updated_at FROM paginas ORDER BY ordem, id')->fetchAll();
$destaque = null; $outras = [];
foreach ($paginas as $r) {
    if ($destaque === null && ($r['secao'] === 'Comece aqui' || $r['slug'] === 'como-usar')) $destaque = $r;
    else $outras[] = $r;
}
if (!empty($_GET['p'])) {
    $pg = null; foreach ($paginas as $r) if ($r['slug'] === $_GET['p']) $pg = $r;
    topo($pg ? $pg['titulo'] : 'Guia do projeto', 'projeto');
    echo '<p class="sm"><a class="voltar" href="' . e(url('projeto.php')) . '">← Todas as páginas</a></p>';
    if (!$pg) { echo '<div class="card vazio"><strong>Página não encontrada.</strong></div>'; rodape(); exit; }
    echo '<nav class="abas-pag" aria-label="Páginas do projeto">';
    foreach (array_merge($destaque ? [$destaque] : [], $outras) as $r) {
        echo '<a href="' . e(url('projeto.php?p=' . $r['slug'])) . '"' . ($r['slug'] === $pg['slug'] ? ' class="on" aria-current="page"' : '') . '>' . e($r['titulo']) . '</a>';
    }
    echo '</nav>';
    echo '<header class="cab-pag' . ($pg['slug'] === 'em-aberto' ? ' aberto' : '') . '"><p class="eyebrow">' . e($pg['secao']) . '</p><h1>' . e($pg['titulo']) . '</h1><p class="sm muted" style="margin:0">Atualizado em ' . e(dt($pg['updated_at'])) . '</p></header>';
    echo '<article class="bloco prosa">' . md($pg['conteudo']) . '</article>';
    rodape(); exit;
}
topo('Guia do projeto', 'projeto');
echo '<div class="cab"><h1>Guia do projeto</h1>' . ajuda('projeto') . '</div><p class="sub">Tudo para entender o projeto em um só lugar: como usar o painel, o que estamos construindo, o protótipo do site, o conteúdo de cada página, as perguntas em aberto e a pauta das reuniões.</p>';
if (!$paginas) { echo '<div class="card vazio"><strong>Nenhuma página ainda.</strong></div>'; rodape(); exit; }
echo '<div class="bento' . ($destaque ? ' com-destaque' : '') . '">';
if ($destaque) {
    echo '<div><p class="rotulo">Comece aqui</p><a class="destaque-escuro" href="' . e(url('projeto.php?p=' . $destaque['slug'])) . '">'
       . '<div><div class="topo-d"><span class="pill">Guia</span>' . ico('seta') . '</div>'
       . '<h3 style="margin-top:var(--sp-4)">' . e($destaque['titulo']) . '</h3><p>' . e(trecho($destaque['conteudo'], 120)) . '</p></div>'
       . '<div class="rod">Atualizado em ' . e(dt($destaque['updated_at'])) . '</div></a></div>';
}
echo '<div><p class="rotulo">Páginas do projeto · ' . count($outras) . '</p><div class="cards-pag">';
foreach ($outras as $i => $r) {
    $cl = $r['slug'] === 'em-aberto' ? 'ouro' : 'acc-' . ($i % 3);
    echo '<a class="card-pag ' . $cl . '" href="' . e(url('projeto.php?p=' . $r['slug'])) . '"><div><span class="pill">' . e($r['secao']) . '</span>'
       . '<h3>' . e($r['titulo']) . '</h3><p>' . e(trecho($r['conteudo'])) . '</p></div>'
       . '<div class="rod"><span>' . e(dt($r['updated_at'])) . '</span><b>Abrir</b></div></a>';
}
echo '</div></div></div>';
rodape();
