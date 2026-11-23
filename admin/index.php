<?php
require __DIR__ . '/../inc/bootstrap.php';
$eu = exigir_admin();
require __DIR__ . '/_acoes.php';
require_once __DIR__ . '/../inc/anexos.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') admin_post($eu);

$pdo = db();
$v = $_GET['v'] ?? 'tarefas';
$abas = ['tarefas' => 'Tarefas', 'decisoes' => 'Decisões', 'reunioes' => 'Reuniões', 'historico' => 'Histórico', 'paginas' => 'Páginas', 'arquivos' => 'Arquivos', 'pessoas' => 'Pessoas', 'engajamento' => 'Engajamento', 'desempacar' => 'Desempacar', 'importar' => 'Importar'];
foreach (['decisoes' => 'decidido', 'reunioes' => 'reunioes', 'historico' => 'historico', 'paginas' => 'guia', 'arquivos' => 'arquivos', 'engajamento' => 'engajamento', 'desempacar' => 'engajamento'] as $k => $m) if (!modulo($m)) unset($abas[$k]);
if (!isset($abas[$v])) $v = 'tarefas';
$pessoas = $pdo->query('SELECT * FROM pessoas WHERE ativo = 1 ORDER BY lado DESC, nome')->fetchAll();
$reunioes = $pdo->query('SELECT id, data, titulo FROM reunioes ORDER BY data DESC, id DESC')->fetchAll();
$H = fn(string $a, array $extra = []) => csrf_campo() . '<input type="hidden" name="a" value="' . e($a) . '"><input type="hidden" name="v" value="' . e($v) . '">'
    . implode('', array_map(fn($k, $x) => '<input type="hidden" name="' . e($k) . '" value="' . e($x) . '">', array_keys($extra), $extra));
$optPessoas = fn($sel = 0) => implode('', array_map(fn($p) => '<option value="' . $p['id'] . '"' . ((int)$sel === (int)$p['id'] ? ' selected' : '') . '>' . e($p['nome']) . '</option>', $pessoas));
$optReunioes = fn($sel = 0) => '<option value="">— nenhuma —</option>' . implode('', array_map(fn($r) => '<option value="' . $r['id'] . '"' . ((int)$sel === (int)$r['id'] ? ' selected' : '') . '>' . e(dt($r['data']) . ' · ' . $r['titulo']) . '</option>', $reunioes));
$apagar = fn(string $a, int $id) => '<form method="post" class="inline" onsubmit="return confirm(\'Apagar? Não dá para desfazer.\')">' . $H($a, ['id' => $id]) . '<button class="btn perigo mini" type="submit">Apagar</button></form>';

topo('Admin', 'admin');
echo '<div class="cab"><h1>Admin</h1></div><nav class="seg adm-abas" aria-label="Áreas do admin">';
foreach ($abas as $k => $n) echo '<a href="?v=' . $k . '"' . ($v === $k ? ' class="on"' : '') . '>' . $n . '</a>';
echo '</nav>';

/* ---------------- TAREFAS ---------------- */
if ($v === 'tarefas') {
    $f = (string)($_GET['f'] ?? 'abertas');
    if (!in_array($f, ['abertas', 'atrasadas', 'travadas', 'concluidas', 'todas'], true)) $f = 'abertas';
    $ed = null;
    if (!empty($_GET['edit'])) { $q = $pdo->prepare('SELECT * FROM tarefas WHERE id = ?'); $q->execute([(int)$_GET['edit']]); $ed = $q->fetch() ?: null; }
    echo '<div class="adm-split"><div class="adm-form">';
    echo '<div class="card"><h2>' . ($ed ? 'Editar tarefa' : 'Nova tarefa') . '</h2><form method="post" enctype="multipart/form-data">' . ($ed ? $H('tarefa_editar', ['id' => $ed['id'], 'f' => $f]) : $H('tarefa_nova', ['f' => $f]));
    echo '<label>Título</label><input type="text" name="titulo" required value="' . e($ed['titulo'] ?? '') . '">';
    if (recursos_novos()) {
        echo '<label>Tipo ' . ajuda('adm_tipo') . '</label><select name="tipo">';
        foreach (TIPOS as $k => $n) echo '<option value="' . $k . '"' . (($ed['tipo'] ?? 'tarefa') === $k ? ' selected' : '') . '>' . e($n) . '</option>';
        echo '</select>';
    }
    echo '<label>Detalhe (opcional)</label><textarea name="descricao">' . e($ed['descricao'] ?? '') . '</textarea>';
    echo '<div class="grid2"><div><label>Responsável ' . ajuda('adm_resp') . '</label><select name="responsavel_id" required>' . $optPessoas($ed['responsavel_id'] ?? 0) . '</select></div>';
    echo '<div><label>Prazo ' . ajuda('adm_prazo') . '</label><input type="date" name="prazo" required value="' . e($ed['prazo'] ?? '') . '"></div></div>';
    $marcados = [];
    if ($ed && equipe_ativa()) { $qe = $pdo->prepare('SELECT pessoa_id FROM tarefa_pessoas WHERE tarefa_id = ?'); $qe->execute([(int)$ed['id']]); $marcados = array_map('intval', $qe->fetchAll(PDO::FETCH_COLUMN)); }
    echo '<label>Também participam (opcional) ' . ajuda('adm_equipe') . '</label>';
    if (!equipe_ativa()) echo '<p class="sm muted">Rode <code>migrations/006_tarefa_pessoas.sql</code> para liberar esta opção.</p>';
    else {
        echo '<div>';
        foreach ($pessoas as $px) echo '<label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;margin:4px 14px 4px 0"><input type="checkbox" name="participantes[]" value="' . (int)$px['id'] . '"' . (in_array((int)$px['id'], $marcados, true) ? ' checked' : '') . '> ' . e($px['nome']) . '</label>';
        echo '</div><p class="sm muted">Quem estiver marcado também vê a tarefa em "Minhas" e pode concluí-la. Se for o mesmo do responsável, é ignorado.</p>';
    }
    if (modulo('reunioes')) echo '<label>Reunião de origem</label><select name="reuniao_id">' . $optReunioes($ed['reuniao_id'] ?? 0) . '</select>';
    echo '<label>A vez é de (opcional) ' . ajuda('adm_bola') . '</label><select name="bola"><option value="">Automático (lado do responsável)</option><option value="cliente"' . (($ed['bola_com'] ?? '') === 'cliente' ? ' selected' : '') . '>' . e(cliente_curto()) . '</option><option value="moura"' . (($ed['bola_com'] ?? '') === 'moura' ? ' selected' : '') . '>' . e(gestor_empresa()) . '</option></select>';
    if ($ed) {
        echo '<label>Situação</label><select name="situacao">';
        foreach (STATUS as $k => $n) { if ($k === 'validacao' && !recursos_novos()) continue; echo '<option value="' . $k . '"' . ($ed['status'] === $k ? ' selected' : '') . '>' . e($n) . '</option>'; }
        echo '</select>';
    }
    echo anexos_campo_html();
    echo '<p class="form-acoes"><button class="btn" type="submit">' . ($ed ? 'Salvar' : 'Criar tarefa') . '</button>' . ($ed ? ' <a class="btn sec" href="?v=tarefas&f=' . $f . '">Cancelar</a>' : '') . '</p></form></div>';
    if ($ed) echo '<div class="card"><h2>Arquivos desta tarefa</h2>' . anexos_lista_html(anexos_da_tarefa((int)$ed['id']), $eu, url('admin/?v=tarefas&f=' . $f . '&edit=' . (int)$ed['id'])) . '</div>';
    echo '</div><div class="adm-lista">';

    $hoje = date('Y-m-d');
    $rows = com_equipe($pdo->query("SELECT t.*, p.nome, p.nome AS responsavel FROM tarefas t JOIN pessoas p ON p.id = t.responsavel_id ORDER BY t.prazo, t.id")->fetchAll());
    $cnt = ['abertas' => 0, 'atrasadas' => 0, 'validacao' => 0, 'travadas' => 0, 'concluidas' => 0, 'todas' => count($rows)];
    foreach ($rows as $x) {
        if ($x['status'] === 'feito') { $cnt['concluidas']++; continue; }
        $cnt['abertas']++;
        if ($x['prazo'] < $hoje) $cnt['atrasadas']++;
        if ($x['status'] === 'travado') $cnt['travadas']++;
        if ($x['status'] === 'validacao') $cnt['validacao']++;
    }
    $filtros = ['abertas' => 'Em aberto', 'atrasadas' => 'Atrasadas', 'validacao' => 'Em validação', 'travadas' => 'Travadas', 'concluidas' => 'Concluídas', 'todas' => 'Todas'];
    if (!recursos_novos()) unset($filtros['validacao']);
    $vis = array_values(array_filter($rows, fn($x) => match ($f) {
        'atrasadas' => $x['status'] !== 'feito' && $x['prazo'] < $hoje,
        'travadas' => $x['status'] === 'travado',
        'validacao' => $x['status'] === 'validacao',
        'concluidas' => $x['status'] === 'feito',
        'todas' => true,
        default => $x['status'] !== 'feito',
    }));
    if ($f === 'concluidas') usort($vis, fn($p, $q) => strcmp((string)$q['concluida_em'], (string)$p['concluida_em']));
    echo '<nav class="seg adm-filtros" aria-label="Filtrar tarefas">';
    foreach ($filtros as $k => $n) echo '<a href="?v=tarefas&f=' . $k . '"' . ($f === $k ? ' class="on" aria-current="page"' : '') . '>' . $n . ' (' . $cnt[$k] . ')</a>';
    echo '</nav>';
    echo '<div class="card"><h2>' . e($filtros[$f]) . ' (' . count($vis) . ')</h2>';
    if (!$vis) echo '<p class="muted">Nenhuma tarefa nesta visão.</p>';
    else {
        echo '<div class="tabela-wrap"><table class="tabela"><tr><th>Tarefa</th><th>Quem</th><th>Prazo</th><th>Situação</th><th></th></tr>';
        foreach ($vis as $x) {
            [$rot, $cls] = prazo_info($x['prazo'], $x['status']);
            $feita = $x['status'] === 'feito';
            $btn = '<form method="post" class="inline">' . $H('tarefa_status', ['id' => $x['id'], 'novo' => $feita ? 'a_fazer' : 'feito', 'f' => $f]) . '<button class="btn' . ($feita ? ' sec' : '') . ' mini" type="submit">' . ($feita ? 'Reabrir' : 'Concluir') . '</button></form>';
            echo '<tr><td>' . tipo_badge($x) . ' <a href="' . e(url('tarefa.php?id=' . $x['id'])) . '">' . e($x['titulo']) . '</a></td><td>' . e(equipe_texto($x)) . '</td><td>' . e(dt($x['prazo'])) . ($rot ? '<br><span class="badge ' . e($cls) . '">' . e($rot) . '</span>' : '') . '</td>'
               . '<td><span class="badge ' . e($x['status']) . '">' . e(status_rotulo($x)) . '</span>' . ($feita && $x['concluida_em'] ? '<br><span class="sm muted">em ' . e(dt(substr($x['concluida_em'], 0, 10))) . '</span>' : '') . '<div style="margin-top:6px">' . $btn . '</div></td>'
               . '<td style="white-space:nowrap"><a class="btn sec mini" href="?v=tarefas&f=' . $f . '&edit=' . $x['id'] . '">Editar</a> ' . $apagar('tarefa_apagar', (int)$x['id']) . '</td></tr>';
        }
        echo '</table></div>';
    }
    echo '</div>';
    echo '</div></div>';
}

/* ---------------- DECISÕES ---------------- */
if ($v === 'decisoes') {
    echo '<div class="adm-split"><div class="adm-form">';
    echo '<div class="card"><h2>Nova decisão</h2><form method="post">' . $H('decisao_nova') . '<label>O que foi decidido</label><textarea name="texto" required></textarea><div class="grid2"><div><label>Data</label><input type="date" name="data" required value="' . date('Y-m-d') . '"></div><div><label>Reunião</label><select name="reuniao_id">' . $optReunioes() . '</select></div></div><p class="form-acoes"><button class="btn" type="submit">Registrar</button></p></form></div>';
    echo '</div><div class="adm-lista">';
    foreach ($pdo->query('SELECT * FROM decisoes ORDER BY data DESC, id DESC') as $d)
        echo '<div class="card decisao"><div class="dia">' . e(dt($d['data'])) . '</div><div style="flex:1"><p style="margin:0 0 8px">' . e($d['texto']) . '</p>' . $apagar('decisao_apagar', (int)$d['id']) . '</div></div>';
    echo '</div></div>';
}

/* ---------------- REUNIÕES ---------------- */
if ($v === 'reunioes') {
    echo '<div class="adm-split"><div class="adm-form">';
    echo '<div class="card"><h2>Nova reunião</h2><form method="post">' . $H('reuniao_nova') . '<div class="grid2"><div><label>Título</label><input type="text" name="titulo" required></div><div><label>Data</label><input type="date" name="data" required value="' . date('Y-m-d') . '"></div></div><label>Resumo ' . ajuda('adm_md') . '</label><textarea name="resumo" style="min-height:160px"></textarea><p class="form-acoes"><button class="btn" type="submit">Registrar</button></p></form></div>';
    echo '</div><div class="adm-lista">';
    foreach ($reunioes as $r) echo '<div class="card"><div class="cab"><strong><a href="' . e(url('reunioes.php?id=' . $r['id'])) . '">' . e($r['titulo']) . '</a></strong><span class="muted sm">' . e(dt($r['data'])) . '</span></div>' . $apagar('reuniao_apagar', (int)$r['id']) . '</div>';
    echo '</div></div>';
}

/* ---------------- HISTÓRICO ---------------- */
if ($v === 'historico') {
    try { $hist = $pdo->query('SELECT * FROM historico ORDER BY data DESC, id DESC')->fetchAll(); $tabelaOk = true; }
    catch (Throwable $t) { $hist = []; $tabelaOk = false; }
    if (!$tabelaOk) echo '<div class="aviso erro"><strong>A tabela do histórico ainda não existe.</strong> Rode <code>migrations/002_historico.sql</code> no phpMyAdmin.</div>';
    else {
    echo '<div class="adm-split"><div class="adm-form">';
        echo '<div class="card"><h2>Novo marco</h2><form method="post">' . $H('historico_novo') . '<div class="grid2"><div><label>Título</label><input type="text" name="titulo" required></div><div><label>Data</label><input type="date" name="data" required value="' . date('Y-m-d') . '"></div></div><label>O que aconteceu ' . ajuda('adm_md') . '</label><textarea name="descricao" style="min-height:120px"></textarea><p class="sm muted">Linguagem simples. ' . e(cliente_curto()) . ' lê esta aba: nada de estratégia comercial nem opinião sobre pessoas.</p><p class="form-acoes"><button class="btn" type="submit">Registrar</button></p></form></div>';
    echo '</div><div class="adm-lista">';
        foreach ($hist as $h)
            echo '<div class="card"><div class="cab"><strong>' . e($h['titulo']) . '</strong><span class="muted sm">' . e(dt($h['data'])) . '</span></div>' . ($h['descricao'] ? '<div class="prosa">' . md($h['descricao']) . '</div>' : '') . $apagar('historico_apagar', (int)$h['id']) . '</div>';
    echo '</div></div>';
    }
}

/* ---------------- PÁGINAS ---------------- */
if ($v === 'paginas') {
    $ed = null;
    if (!empty($_GET['edit'])) { $q = $pdo->prepare('SELECT * FROM paginas WHERE id = ?'); $q->execute([(int)$_GET['edit']]); $ed = $q->fetch() ?: null; }
    echo '<div class="adm-split"><div class="adm-form">';
    echo '<div class="card"><h2>' . ($ed ? 'Editar página' : 'Nova página') . '</h2><form method="post">' . $H('pagina_salvar');
    echo '<div class="grid2"><div><label>Título</label><input type="text" name="titulo" required value="' . e($ed['titulo'] ?? '') . '"></div><div><label>Endereço curto (slug)</label><input type="text" name="slug" required pattern="[a-z0-9-]{2,80}" value="' . e($ed['slug'] ?? '') . '"></div></div>';
    echo '<div class="grid2"><div><label>Seção</label><input type="text" name="secao" value="' . e($ed['secao'] ?? 'O projeto') . '"></div><div><label>Ordem</label><input type="text" name="ordem" value="' . e($ed['ordem'] ?? 0) . '"></div></div>';
    echo '<label>Conteúdo ' . ajuda('adm_md') . '</label><textarea name="conteudo" required style="min-height:260px">' . e($ed['conteudo'] ?? '') . '</textarea><p class="form-acoes"><button class="btn" type="submit">Salvar página</button>' . ($ed ? ' <a class="btn sec" href="?v=paginas">Cancelar</a>' : '') . '</p></form></div>';
    echo '</div><div class="adm-lista">';
    foreach ($pdo->query('SELECT * FROM paginas ORDER BY ordem, id') as $p)
        echo '<div class="card"><div class="cab"><strong><a href="' . e(url('projeto.php?p=' . $p['slug'])) . '">' . e($p['titulo']) . '</a></strong><span class="muted sm">' . e($p['secao']) . ' · ' . e($p['slug']) . '</span></div><a class="btn sec mini" href="?v=paginas&edit=' . $p['id'] . '">Editar</a> ' . $apagar('pagina_apagar', (int)$p['id']) . '</div>';
    echo '</div></div>';
}

/* ---------------- PESSOAS ---------------- */
if ($v === 'pessoas') {
    $esquema = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    echo '<div class="adm-split"><div class="adm-form">';
    if (!empty($_SESSION['zap'])) {
        $z = $_SESSION['zap']; unset($_SESSION['zap']);
        echo '<div class="card"><h2>Mensagem para ' . e($z['nome']) . '</h2><textarea readonly rows="8" class="mono" onclick="this.select()">' . e($z['texto']) . '</textarea><p style="margin-top:10px"><button class="btn" type="button" data-copiar="' . e($z['texto']) . '">Copiar mensagem</button></p><p class="sm muted">A senha só aparece agora. Se perder, use Redefinir senha.</p></div>';
    }
    echo '<div class="card"><h2>Nova pessoa</h2><form method="post">' . $H('pessoa_nova') . '<div class="grid2"><div><label>Nome</label><input type="text" name="nome" required></div><div><label>Papel</label><input type="text" name="papel" placeholder="Ex.: Tesoureira"></div></div><label>E-mail (é o login da pessoa)</label><input type="email" name="email" placeholder="nome@exemplo.com.br" autocapitalize="none">' . (recursos_novos() ? '<label>Celular (WhatsApp, com DDD) <span class="opc">opcional</span></label><input type="text" name="celular" inputmode="tel" placeholder="48 99999-9999">' : '') . '<label>Senha inicial (mínimo 4)</label><input type="text" name="senha" value="1234" autocomplete="off"><label>Lado</label><select name="lado"><option value="cliente">' . e(cliente_curto()) . '</option><option value="moura">Moura Digital</option></select><p class="form-acoes"><button class="btn" type="submit">Criar pessoa</button></p></form></div>';
    echo '</div><div class="adm-lista">';
    foreach ($pdo->query('SELECT * FROM pessoas ORDER BY ativo DESC, lado DESC, nome') as $p) {
        $link = $esquema . url('?t=' . $p['token']);
        echo '<div class="card"><div class="cab"><strong>' . e($p['nome']) . '</strong><span class="muted sm">' . e($p['papel']) . ' · ' . ($p['lado'] === 'moura' ? 'Moura' : e(cliente_curto())) . ($p['is_admin'] ? ' · admin' : '') . '</span>' . (!$p['ativo'] ? '<span class="badge atraso">Desativada</span>' : '') . '</div>';
        $login = empty($p['email']) ? 'sem e-mail' : (empty($p['senha_hash']) ? 'sem senha: defina uma' : (!empty($p['senha_provisoria']) ? 'senha inicial (você definiu; a pessoa ainda não trocou)' : 'senha alterada pela própria pessoa'));
        echo '<div class="sm">Login: <strong>' . e($p['email'] ?: '—') . '</strong> · ' . e($login) . '</div><div class="acoes" style="margin:8px 0 12px">';
        if (!empty($p['email'])) echo '<form method="post" class="inline">' . $H('pessoa_senha_def', ['id' => $p['id']]) . '<input type="text" name="senha" value="1234" autocomplete="off" style="width:110px;display:inline-block;padding:5px 8px"> <button class="btn mini">Definir senha inicial</button></form>';
        if (!empty($p['email'])) echo '<form method="post" class="inline" onsubmit="return confirm(\'Gerar uma senha provisória nova? A senha atual e os acessos abertos dessa pessoa deixam de valer.\')">' . $H('pessoa_senha', ['id' => $p['id']]) . '<button class="btn warn mini">' . (empty($p['senha_hash']) ? 'Gerar senha provisória' : 'Redefinir senha') . '</button></form>';
        echo '</div>';
        echo '<div class="sm">Link pessoal (acesso antigo) ' . ajuda('adm_link') . '</div><code class="linkbox">' . e($link) . '</code><div class="acoes">';
        echo '<button class="btn sec mini" type="button" data-copiar="' . e($link) . '">Copiar link</button>';
        echo '<form method="post" class="inline" onsubmit="return confirm(\'O link antigo vai parar de funcionar. Continuar?\')">' . $H('pessoa_novo_link', ['id' => $p['id']]) . '<button class="btn warn mini">Gerar novo link</button></form>';
        echo '<form method="post" class="inline">' . $H('pessoa_ativo', ['id' => $p['id']]) . '<button class="btn ' . ($p['ativo'] ? 'perigo' : 'sec') . ' mini">' . ($p['ativo'] ? 'Desativar' : 'Reativar') . '</button></form></div>';
        echo '<details class="mini" style="margin-top:10px"><summary class="btn sec mini">Editar dados</summary><form method="post">' . $H('pessoa_editar', ['id' => $p['id']]) . '<div class="grid2"><div><label>Nome</label><input type="text" name="nome" value="' . e($p['nome']) . '"></div><div><label>Papel</label><input type="text" name="papel" value="' . e($p['papel']) . '"></div></div><label>E-mail (login)</label><input type="email" name="email" value="' . e((string)$p['email']) . '" autocapitalize="none">' . (recursos_novos() ? '<label>Celular (WhatsApp, com DDD)</label><input type="text" name="celular" inputmode="tel" value="' . e((string)($p['celular'] ?? '')) . '">' : '') . '<button class="btn mini" style="margin-top:8px">Salvar</button></form></details></div>';
    }
    echo '</div></div>';
}

/* ---------------- ARQUIVOS ---------------- */
if ($v === 'arquivos') { require __DIR__ . '/_arquivos.php'; }

/* ---------------- DESEMPACAR ---------------- */
if ($v === 'desempacar') { require __DIR__ . '/_desempacar.php'; }

/* ---------------- ENGAJAMENTO (privado do admin) ---------------- */
if ($v === 'engajamento') { require __DIR__ . '/_engajamento.php'; }

/* ---------------- IMPORTAR ---------------- */
if ($v === 'importar') {
    $imp = $_SESSION['imp'] ?? null; unset($_SESSION['imp']);
    echo '<div class="card"><div class="cab"><h2>Importar de uma reunião</h2>' . ajuda('adm_importar') . '</div>';
    if ($imp) {
        if ($imp['ok']) echo '<div class="aviso ok">' . (!empty($imp['simulado']) ? 'Validação OK (nada gravado ainda). ' : 'Importado. ') . 'Reunião: ' . $imp['resumo']['reuniao'] . ' · Decisões: ' . $imp['resumo']['decisoes'] . ' · Tarefas: ' . $imp['resumo']['tarefas'] . ' · Páginas: ' . $imp['resumo']['paginas'] . ' · Histórico: ' . ($imp['resumo']['historico'] ?? 0) . '</div>';
        else { echo '<div class="aviso erro"><strong>Nada foi gravado. Corrija:</strong><ul style="margin:6px 0 0;padding-left:18px">'; foreach ($imp['erros'] as $x) echo '<li>' . e($x) . '</li>'; echo '</ul></div>'; }
    }
    $exemplo = "{\n  \"reuniao\": {\"data\": \"" . date('Y-m-d') . "\", \"titulo\": \"Reunião de conteúdo\", \"resumo\": \"- Fechamos os fatos em aberto\\n- Definimos responsáveis\"},\n  \"decisoes\": [{\"texto\": \"O painel é o lugar oficial das tarefas.\"}],\n  \"tarefas\": [{\"titulo\": \"Enviar fotos dos associados\", \"descricao\": \"\", \"responsavel\": \"Funcionária\", \"prazo\": \"" . date('Y-m-d', strtotime('+7 days')) . "\"}],\n  \"paginas\": []\n}";
    echo '<form method="post">' . $H('importar') . '<label>JSON</label><textarea name="json" class="mono" style="min-height:300px" required>' . e($imp['texto'] ?? $exemplo) . '</textarea><p class="form-acoes"><button class="btn sec" name="validar" value="1" type="submit">Validar</button> <button class="btn" type="submit">Importar</button></p></form>';
    echo '<p class="sm muted">Responsável aceita o nome inteiro ou parte única dele (ex.: "José Jorge"). Todo item de tarefa precisa de responsável e prazo.</p></div>';
}
rodape();
