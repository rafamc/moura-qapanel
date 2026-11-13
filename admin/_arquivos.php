<?php
// Aba "Arquivos" do admin: sobe o material que o cliente vai baixar. Incluída por admin/index.php ($H, $apagar e $pdo já no escopo).
if (empty($eu['is_admin'])) { http_response_code(403); exit; }
require_once __DIR__ . '/../inc/arquivos.php';
$grupos = arquivos_agrupados();
echo '<div class="adm-split"><div class="adm-form"><div class="card"><div class="cab"><h2>Novo arquivo</h2>' . ajuda('adm_arquivos') . '</div>';
echo '<form method="post" enctype="multipart/form-data">' . $H('arquivo_enviar');
echo '<label for="arq-cat">Categoria</label><input id="arq-cat" type="text" name="categoria" list="arq-cats" required placeholder="Ex.: Logos, Media Kit">';
echo '<datalist id="arq-cats">' . implode('', array_map(fn($c) => '<option value="' . e((string)$c) . '">', array_keys($grupos))) . '</datalist>';
echo '<label for="arq-tit">Título</label><input id="arq-tit" type="text" name="titulo" required placeholder="Ex.: Pack de logos (PNG e SVG)">';
echo '<label for="arq-desc">Descrição (opcional)</label><input id="arq-desc" type="text" name="descricao" maxlength="300">';
echo '<label for="arq-f">Arquivo</label><input id="arq-f" type="file" name="arquivo" required>';
echo '<p class="sm muted">Até ' . (ARQ_MAX_BYTES / 1048576) . ' MB. Aceita: ' . e(implode(', ', array_keys(ARQ_EXT))) . '. O Media Kit em HTML vai como .html (baixa, não abre no painel).</p>';
echo '<p class="form-acoes"><button class="btn" type="submit">Enviar</button></p></form></div></div><div class="adm-lista">';
if (!$grupos) echo '<div class="card vazio"><strong>Nenhum arquivo ainda.</strong></div>';
foreach ($grupos as $cat => $lista) {
    echo '<div class="card"><h2>' . e($cat) . ' (' . count($lista) . ')</h2>';
    foreach ($lista as $a)
        echo '<div style="border-top:1px solid var(--line);padding:10px 0"><div class="cab"><strong>' . e($a['titulo']) . '</strong><span class="muted sm">' . e(anexo_tamanho((int)$a['tamanho'])) . ' · ' . e(dt($a['created_at'])) . '</span></div>'
           . '<p class="sm muted" style="margin:2px 0 8px">' . e($a['nome_original']) . '</p><a class="btn sec mini" href="' . e(url('arquivo.php?id=' . (int)$a['id'])) . '">Baixar</a> ' . $apagar('arquivo_apagar', (int)$a['id']) . '</div>';
    echo '</div>';
}
echo '</div></div>';
