<?php
require __DIR__ . '/inc/bootstrap.php';
$eu = exigir_login();
$pdo = db();
$todas = ($_GET['v'] ?? '') === 'todas';
$modo = in_array($_GET['m'] ?? '', ['quadro', 'agenda'], true) ? $_GET['m'] : 'lista';

/** Endereço desta tela com escopo (minhas/todas) e visão (lista/quadro/agenda). */
function href_tela(bool $todas, string $modo): string {
    $q = [];
    if ($todas) $q['v'] = 'todas';
    if ($modo !== 'lista') $q['m'] = $modo;
    return url($q ? '?' . http_build_query($q) : '');
}

function ucfirst_mb(string $s): string { return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1); }
function saudacao(): string { $h = (int)date('G'); return $h < 12 ? 'Bom dia' : ($h < 18 ? 'Boa tarde' : 'Boa noite'); }
const DIAS_SEM = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const DIAS_FULL = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

function cartao_tarefa(array $t, array $eu, bool $mostraResp, string $volta, bool $compacto = false, bool $semPrazo = false, bool $hero = false): void {
    [$rot, $cls] = prazo_info($t['prazo'], $t['status']);
    $meu = pode_agir($t, $eu);
    $classes = 'card tarefa st-' . $cls . ($t['status'] === 'travado' ? ' is-travado' : '') . ($compacto ? ' compacta' : '') . ($hero ? ' hero' : '');
    $hid = '<input type="hidden" name="tarefa_id" value="' . (int)$t['id'] . '"><input type="hidden" name="volta" value="' . e($volta) . '">';
    echo '<article class="' . e($classes) . '">';
    if ($hero) echo '<div class="hero-rot">Comece por esta</div>';
    if ($rot && !$semPrazo) echo '<div class="prazo ' . e($cls) . '">' . e(ucfirst_mb($rot)) . '</div>';
    echo '<h3><a href="' . e(url('tarefa.php?id=' . $t['id'])) . '">' . e($t['titulo']) . '</a></h3>';
    echo '<div class="meta">';
    if ($mostraResp) echo '<span class="resp">' . equipe_html($t) . '</span><span>·</span>';
    elseif (!empty($t['participantes'])) echo '<span class="resp">Com ' . e(equipe_texto($t, (int)$eu['id'])) . '</span><span>·</span>';
    if (!$semPrazo) echo '<span>Prazo ' . e(dt($t['prazo'])) . '</span>';
    echo tipo_badge($t);
    if (in_array($t['status'], ['travado', 'fazendo', 'validacao'], true)) echo '<span class="badge ' . e($t['status']) . '">' . e(STATUS[$t['status']]) . '</span>';
    echo bola_badge($t);
    echo '</div>';
    if (!$compacto && $t['descricao']) echo '<p class="sm">' . e($t['descricao']) . '</p>';
    if ($hero && ($t['reu_titulo'] || (int)$t['n_hist'] > 0)) {
        echo '<div class="chips">';
        if ($t['reu_titulo']) echo '<a class="chip" href="' . e(url('reunioes.php?id=' . (int)$t['reuniao_id'])) . '">Origem: <b>' . e($t['reu_titulo']) . ' (' . e(date('d/m', strtotime($t['reu_data']))) . ')</b></a>';
        if ((int)$t['n_hist'] > 0) echo '<a class="chip" href="' . e(url('tarefa.php?id=' . $t['id'])) . '">Histórico <b>(' . (int)$t['n_hist'] . ')</b></a>';
        echo '</div>';
    }
    if ($meu && $t['status'] !== 'feito') echo botoes_status($t, $eu, $hid, 'c' . (int)$t['id'], $compacto);
    echo '</article>';
}

function linha_feita(array $t, bool $mostraResp): void {
    echo '<article class="card tarefa feita-linha" style="padding:12px 16px"><div class="meta" style="margin:0"><span class="badge feito">' . e(status_rotulo($t)) . '</span>' . tipo_badge($t) . '<a href="' . e(url('tarefa.php?id=' . $t['id'])) . '">' . e($t['titulo']) . '</a>' . ($mostraResp ? '<span>· ' . e(equipe_texto($t)) . '</span>' : '') . '</div></article>';
}

topo('Para mim', 'index');

$base = 'SELECT t.*, p.nome AS responsavel, r.titulo AS reu_titulo, r.data AS reu_data, (SELECT COUNT(*) FROM comentarios c WHERE c.tarefa_id = t.id) AS n_hist FROM tarefas t JOIN pessoas p ON p.id = t.responsavel_id LEFT JOIN reunioes r ON r.id = t.reuniao_id';
[$fMin, $pMin] = filtro_minhas((int)$eu['id']);
if ($todas) {
    $abertas = $pdo->query("$base WHERE t.status <> 'feito' ORDER BY t.prazo, t.id")->fetchAll();
} else {
    $st = $pdo->prepare("$base WHERE $fMin AND t.status <> 'feito' ORDER BY t.prazo, t.id");
    $st->execute($pMin);
    $abertas = $st->fetchAll();
}
$q = $pdo->prepare("$base WHERE t.status = 'feito'" . ($todas ? '' : " AND $fMin") . ' ORDER BY t.concluida_em DESC LIMIT 8');
$q->execute($todas ? [] : $pMin);
$feitas = com_equipe($q->fetchAll());
$abertas = com_equipe($abertas);

$volta = href_tela($todas, $modo);

/* Faixa de resumo: três números, sem enfeite */
$hoje0 = strtotime(date('Y-m-d'));
$nAtras = 0; $proxima = null;
foreach ($abertas as $t) {
    if (strtotime($t['prazo']) < $hoje0) $nAtras++;
    elseif ($proxima === null && $t['status'] !== 'travado') $proxima = $t;
}
$proxRot = $proxima ? ucfirst_mb(prazo_info($proxima['prazo'], $proxima['status'])[0] ?: dt($proxima['prazo'])) : '—';

echo '<div class="cab"><h1 class="saudacao">' . ($todas ? 'Todas as tarefas' : saudacao() . ', ' . e(primeiro_nome($eu['nome']))) . '</h1>' . ajuda($todas ? 'todas' : 'para_mim') . '</div>';
echo '<p class="sub">' . ($todas ? 'O que está aberto, por prazo.' : ($nAtras ? 'Você tem ' . ($nAtras === 1 ? '1 tarefa atrasada. Comece por ela.' : $nAtras . ' tarefas atrasadas. Comece pela primeira.') : 'Estas são as tarefas que ficaram com você.')) . '</p>';

if ($abertas) {
    $nA = count($abertas);
    echo '<div class="resumo" role="group" aria-label="Resumo">'
       . '<div class="res"><b>' . $nA . '</b><span>' . ($nA === 1 ? 'aberta' : 'abertas') . '</span></div>'
       . '<div class="res' . ($nAtras ? ' atraso' : '') . '"><b>' . $nAtras . '</b><span>' . ($nAtras === 1 ? 'atrasada' : 'atrasadas') . '</span>' . ($nAtras ? '' : '<em class="selo-ok">Em dia</em>') . '</div>'
       . '<div class="res"><b>' . e($proxRot) . '</b><span>próxima data' . ($proxima ? ': ' . e(mb_strimwidth($proxima['titulo'], 0, 34, '…')) : '') . '</span></div></div>';
}
echo '<div class="barra">';
echo '<nav class="seg" aria-label="De quem">';
echo '<a href="' . e(href_tela(false, $modo)) . '"' . (!$todas ? ' class="on" aria-current="page"' : '') . '>Minhas</a>';
echo '<a href="' . e(href_tela(true, $modo)) . '"' . ($todas ? ' class="on" aria-current="page"' : '') . '>Todas</a></nav>';
echo '<nav class="seg visao" aria-label="Como ver">';
foreach (['lista' => 'Lista', 'quadro' => 'Quadro', 'agenda' => 'Agenda'] as $k => $n) {
    echo '<a href="' . e(href_tela($todas, $k)) . '"' . ($modo === $k ? ' class="on" aria-current="page"' : '') . '>' . $n . '</a>';
}
echo '</nav></div>';

if (!$abertas && $modo !== 'quadro') {
    echo '<div class="card vazio"><svg viewBox="0 0 96 96" aria-hidden="true"><circle cx="48" cy="48" r="44" style="fill:var(--primary-soft)"/><circle cx="48" cy="48" r="30" style="fill:var(--primary)"/><path d="M34 49l10 10 19-21" fill="none" style="stroke:var(--gold)" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg><strong>' . ($todas ? 'Nenhuma tarefa aberta.' : 'Tudo em dia! Nada pendente com você.') . '</strong>' . ($todas ? 'Quando alguém ficar com uma tarefa, ela aparece aqui.' : 'Você não tem nada pendente. Quando algo ficar combinado com você, aparece aqui com a data.') . '</div>';
}

/* ---------- QUADRO: três colunas, em palavras simples ---------- */
if ($modo === 'quadro') {
    /* Quatro colunas. Cada cartão pode ser arrastado de uma coluna para outra (mover.php grava). */
    $novos = recursos_novos();
    $col = ['a_fazer' => [], 'fazendo' => [], 'validacao' => [], 'travado' => [], 'feito' => $feitas];
    foreach ($abertas as $t) {
        $k = in_array($t['status'], ['fazendo', 'travado', 'validacao'], true) ? $t['status'] : 'a_fazer';
        $col[$k][] = $t;
    }
    $defs = [
        ['a_fazer', 'Aberto', 'c-fazer', 'Nada por aqui. Tudo em dia.'],
        ['fazendo', 'Em execução', 'c-fazendo', 'Arraste um item para cá quando começar.'],
    ];
    if ($novos) $defs[] = ['validacao', 'Em validação', 'c-validacao', 'Nada esperando validação.'];
    $defs[] = ['travado', 'Travou', 'c-travado', 'Nenhuma tarefa travada.'];
    $defs[] = ['feito', 'Concluídos', 'c-feito', 'Quando algo for concluído, aparece aqui.'];
    echo '<div class="quadro kb' . ($novos ? ' kb5' : '') . '" data-mover="' . e(url('mover.php')) . '" data-csrf="' . e(csrf()) . '">';
    foreach ($defs as [$k, $nome, $cl, $vazio]) {
        echo '<section class="coluna ' . $cl . '" aria-label="' . e($nome) . '"><div class="coluna-cab"><h2>' . e($nome) . '</h2><span class="conta">' . count($col[$k]) . '</span></div>';
        echo '<div class="kb-corpo" data-st="' . $k . '"><div class="vazio-mini kb-vazio"' . ($col[$k] ? ' hidden' : '') . '>' . e($vazio) . '</div>';
        foreach ($col[$k] as $t) {
            $meu = pode_agir($t, $eu);
            echo '<div class="kb-item' . ($meu ? ' movivel' : '') . '"' . ($meu ? ' draggable="true"' : '') . ' data-id="' . (int)$t['id'] . '" data-st="' . $k . '">';
            if ($k === 'feito') linha_feita($t, $todas); else cartao_tarefa($t, $eu, $todas, $volta, true);
            echo '</div>';
        }
        echo '</div></section>';
    }
    echo '</div>';
    echo '<dialog id="kb-motivo" class="kb-dialog"><form method="dialog"><h3>O que está impedindo?</h3>'
       . '<p class="sm muted">Conte em uma frase, para o ' . gestor() . ' conseguir ajudar.</p>'
       . '<textarea id="kb-motivo-txt" placeholder="Ex.: ainda não recebi as fotos"></textarea>'
       . '<div class="kb-dialog-acoes"><button class="btn sec" value="cancel" type="submit">Cancelar</button><button class="btn warn" id="kb-motivo-ok" value="ok" type="submit">Avisar o ' . gestor() . '</button></div></form></dialog>';
    echo '<div class="kb-toast" id="kb-toast" role="status" hidden></div>';
    echo '<script>(function(){var K=document.querySelector(".kb");if(!K)return;var URL_=K.dataset.mover,TOK=K.dataset.csrf,arrasta=null;'
       . 'var dlg=document.getElementById("kb-motivo"),txt=document.getElementById("kb-motivo-txt"),toast=document.getElementById("kb-toast"),tt;'
       . 'function aviso(m){toast.textContent=m;toast.hidden=false;clearTimeout(tt);tt=setTimeout(function(){toast.hidden=true},4200);}'
       . 'function conta(){K.querySelectorAll(".coluna").forEach(function(c){var n=c.querySelectorAll(".kb-item").length;c.querySelector(".conta").textContent=n;var v=c.querySelector(".kb-vazio");if(v)v.hidden=n>0;});}'
       . 'function enviar(item,corpo,destino,motivo){var origem=item.parentNode,antes=item.dataset.st;corpo.appendChild(item);item.dataset.st=destino;conta();'
       . 'var fd=new FormData();fd.append("_csrf",TOK);fd.append("tarefa_id",item.dataset.id);fd.append("status",destino);fd.append("texto",motivo||"");'
       . 'fetch(URL_,{method:"POST",body:fd,credentials:"same-origin"}).then(function(r){return r.json().catch(function(){return{ok:false,msg:"Resposta inesperada do servidor."}})}).then(function(j){'
       . 'if(!j.ok){origem.appendChild(item);item.dataset.st=antes;conta();aviso(j.msg||"Não foi possível mover.");return;}'
       . 'if(antes==="feito"||destino==="feito"||destino==="travado"||antes==="travado"||destino==="validacao"||antes==="validacao"){location.reload();}else{aviso("Movida.");}'
       . '}).catch(function(){origem.appendChild(item);item.dataset.st=antes;conta();aviso("Sem conexão. A tarefa voltou para onde estava.");});}'
       . 'K.addEventListener("dragstart",function(e){var it=e.target.closest&&e.target.closest(".kb-item.movivel");if(!it)return;arrasta=it;setTimeout(function(){it.classList.add("arrastando")},0);e.dataTransfer.effectAllowed="move";e.dataTransfer.setData("text/plain",it.dataset.id);});'
       . 'K.addEventListener("dragend",function(){if(arrasta)arrasta.classList.remove("arrastando");arrasta=null;K.querySelectorAll(".kb-corpo.sobre").forEach(function(c){c.classList.remove("sobre")});});'
       . 'K.addEventListener("dragover",function(e){var c=e.target.closest(".kb-corpo");if(!c||!arrasta)return;e.preventDefault();e.dataTransfer.dropEffect="move";K.querySelectorAll(".kb-corpo.sobre").forEach(function(x){if(x!==c)x.classList.remove("sobre")});c.classList.add("sobre");});'
       . 'K.addEventListener("dragleave",function(e){var c=e.target.closest(".kb-corpo");if(c&&!c.contains(e.relatedTarget))c.classList.remove("sobre");});'
       . 'K.addEventListener("drop",function(e){var c=e.target.closest(".kb-corpo");if(!c||!arrasta)return;e.preventDefault();c.classList.remove("sobre");var it=arrasta,destino=c.dataset.st;if(destino===it.dataset.st)return;'
       . 'if(destino==="travado"){txt.value="";dlg.returnValue="";dlg.showModal();dlg.onclose=function(){if(dlg.returnValue==="ok"&&txt.value.trim()){enviar(it,c,destino,txt.value.trim());}else if(dlg.returnValue==="ok"){aviso("Sem o motivo, a tarefa não pode ir para Travou.");}};}else{enviar(it,c,destino,"");}});'
       . '})();</script>';
}

/* ---------- AGENDA: por dia, o que vence primeiro no topo ---------- */
elseif ($modo === 'agenda') {
    /* Calendário mensal (segunda a domingo). Cada tarefa é um botão no dia do prazo;
       ao clicar, o cartão completo abre no painel da direita. */
    $mesQ = $_GET['mes'] ?? '';
    $ini = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesQ) ? strtotime($mesQ . '-01') : strtotime(date('Y-m-01'));
    $mesAnt = date('Y-m', strtotime('-1 month', $ini));
    $mesProx = date('Y-m', strtotime('+1 month', $ini));
    $mesAtual = date('Y-m', $ini);
    $NOMES_MES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    $hojeS = date('Y-m-d');
    $offset = (int)date('N', $ini) - 1;
    $nDias = (int)date('t', $ini);
    $nSem = (int)ceil(($offset + $nDias) / 7);
    $gridIni = strtotime('-' . $offset . ' days', $ini);
    $volta = href_tela($todas, 'agenda') . (strpos(href_tela($todas, 'agenda'), '?') !== false ? '&' : '?') . 'mes=' . $mesAtual;
    $hrefMes = fn(string $m) => e(href_tela($todas, 'agenda') . (strpos(href_tela($todas, 'agenda'), '?') !== false ? '&' : '?') . 'mes=' . $m);

    $porDia = []; $atras = []; $fora = 0; $abrir = null;
    foreach ($abertas as $t) {
        $d = substr($t['prazo'], 0, 10);
        $porDia[$d][] = $t;
        if ($d < $hojeS) $atras[] = $t;
        if (substr($d, 0, 7) !== $mesAtual) $fora++;
        if ($abrir === null && $t['status'] !== 'travado') $abrir = (int)$t['id'];
    }
    if ($abrir === null && $abertas) $abrir = (int)$abertas[0]['id'];

    $chip = function (array $t) use ($todas): string {
        [, $cls] = prazo_info($t['prazo'], $t['status']);
        $nome = $todas ? '<i>' . e(equipe_curta($t)) . '</i>' : '';
        return '<button type="button" class="ag-chip st-' . e($cls) . ($t['status'] === 'travado' ? ' is-travado' : '') . '" data-t="' . (int)$t['id'] . '" title="' . e($t['titulo']) . '">' . $nome . '<span>' . e($t['titulo']) . '</span></button>';
    };

    echo '<div class="ag-layout"><section class="ag-cal" aria-label="Calendário">';
    echo '<div class="ag-cab"><h2>' . $NOMES_MES[(int)date('n', $ini) - 1] . ' <small>' . date('Y', $ini) . '</small></h2>'
       . '<nav class="ag-nav" aria-label="Mês"><a href="' . $hrefMes($mesAnt) . '" aria-label="Mês anterior">‹</a>'
       . '<a href="' . $hrefMes(date('Y-m')) . '" class="hoje-btn">Hoje</a>'
       . '<a href="' . $hrefMes($mesProx) . '" aria-label="Próximo mês">›</a></nav></div>';

    if ($atras) {
        echo '<div class="ag-atras"><b>Atrasadas (' . count($atras) . ')</b>';
        foreach ($atras as $t) echo $chip($t);
        echo '</div>';
    }

    echo '<div class="ag-grade" style="--sem:' . $nSem . '">';
    foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $dn) echo '<div class="ag-dh">' . $dn . '</div>';
    for ($i = 0; $i < $nSem * 7; $i++) {
        $ts = strtotime('+' . $i . ' days', $gridIni);
        $d = date('Y-m-d', $ts);
        $dentro = substr($d, 0, 7) === $mesAtual;
        $lista = $porDia[$d] ?? [];
        $cl = 'ag-dia' . ($dentro ? '' : ' fora') . ($d === $hojeS ? ' hoje' : '') . (!$lista ? ' vazia' : '') . ((int)date('N', $ts) >= 6 ? ' fds' : '');
        echo '<div class="' . $cl . '"><div class="ag-num"><span>' . date('j', $ts) . '</span><em>' . DIAS_SEM[(int)date('w', $ts)] . ' · ' . date('d/m', $ts) . '</em></div>';
        foreach ($lista as $t) echo $chip($t);
        echo '</div>';
    }
    echo '</div>';
    if ($fora) echo '<p class="sm muted ag-fora">' . $fora . ($fora === 1 ? ' tarefa tem' : ' tarefas têm') . ' prazo em outros meses. Use as setas para ver.</p>';
    if (!$abertas) echo '<div class="vazio-mini" style="margin-top:16px">Nenhuma tarefa aberta.</div>';
    echo '</section>';

    echo '<aside class="ag-painel" id="ag-painel" aria-live="polite"><div class="ag-vazio">Clique em uma tarefa do calendário para ver os detalhes aqui.</div><div class="ag-slot"></div></aside></div>';

    echo '<div class="ag-store" hidden>';
    foreach ($abertas as $t) {
        echo '<div class="ag-ficha" data-f="' . (int)$t['id'] . '">';
        cartao_tarefa($t, $eu, true, $volta, false);
        echo '</div>';
    }
    echo '</div>';
    echo '<script>(function(){var P=document.getElementById("ag-painel");if(!P)return;var slot=P.querySelector(".ag-slot"),vazio=P.querySelector(".ag-vazio"),cur=null,curId=null,V=' . json_encode(url('visto.php')) . ',K=' . json_encode(csrf()) . ';'
       . 'function abrir(id,rolar){var casa=document.querySelector(\'.ag-ficha[data-f="\'+id+\'"]\');if(!casa)return;'
       . 'if(cur){var antiga=document.querySelector(\'.ag-ficha[data-f="\'+curId+\'"]\');if(antiga)antiga.appendChild(cur);}'
       . 'cur=casa.firstElementChild;curId=id;slot.appendChild(cur);try{var fd=new FormData();fd.append("_csrf",K);fd.append("tarefa_id",id);fetch(V,{method:"POST",body:fd,keepalive:true,credentials:"same-origin"});}catch(x){}if(vazio)vazio.hidden=true;'
       . 'document.querySelectorAll(".ag-chip.on").forEach(function(c){c.classList.remove("on")});'
       . 'document.querySelectorAll(\'.ag-chip[data-t="\'+id+\'"]\').forEach(function(c){c.classList.add("on")});'
       . 'if(rolar&&window.matchMedia("(max-width:1000px)").matches)P.scrollIntoView({behavior:"smooth",block:"start"});}'
       . 'document.addEventListener("click",function(e){var b=e.target.closest(".ag-chip");if(b)abrir(b.getAttribute("data-t"),true);});'
       . 'var ini=' . (int)($abrir ?? 0) . ';if(ini)abrir(ini,false);})();</script>';
}

/* ---------- LISTA ---------- */
else {
    $lateral = '';
    $prox = $pdo->query("SELECT t.id, t.titulo, t.prazo, t.status, t.responsavel_id, p.nome AS responsavel FROM tarefas t JOIN pessoas p ON p.id = t.responsavel_id WHERE t.status <> 'feito' ORDER BY t.prazo, t.id LIMIT 4")->fetchAll();
    $prox = com_equipe($prox);
    if ($prox && !$todas) {
        $lateral .= '<section class="bloco"><div class="sec-cab"><h2>Próximos prazos</h2></div>';
        foreach ($prox as $x) {
            $ts = strtotime($x['prazo']); [$rot] = prazo_info($x['prazo'], $x['status']);
            $lateral .= '<a class="prox" href="' . e(url('tarefa.php?id=' . $x['id'])) . '"><div class="cal-mini"><b>' . date('d', $ts) . '</b><span>' . MESES_ABR[(int)date('n', $ts) - 1] . '</span></div>'
                      . '<div><strong>' . e($x['titulo']) . '</strong><div class="sm muted">' . e(equipe_curta($x)) . ($rot ? ' · ' . e($rot) : '') . '</div></div></a>';
        }
        $lateral .= '</section>';
    }
    $ur = $pdo->query('SELECT id, titulo, data FROM reunioes ORDER BY data DESC, id DESC LIMIT 1')->fetch();
    $nd = (int)$pdo->query('SELECT COUNT(*) FROM decisoes')->fetchColumn();
    $at = '';
    if ($ur) $at .= '<a class="atalho" href="' . e(url('reunioes.php?id=' . $ur['id'])) . '"><span class="chip-ico verde">' . ico('livro') . '</span><div><strong>Última reunião</strong><span>' . e(dt($ur['data'])) . ' · ' . e($ur['titulo']) . '</span></div>' . ico('seta') . '</a>';
    if ($nd) $at .= '<a class="atalho" href="' . e(url('decidido.php')) . '"><span class="chip-ico">' . ico('check') . '</span><div><strong>Decisões registradas</strong><span>' . $nd . ($nd === 1 ? ' decisão' : ' decisões') . '</span></div>' . ico('seta') . '</a>';
    $at .= '<a class="atalho" href="' . e(url('projeto.php')) . '"><span class="chip-ico">' . ico('tarefa') . '</span><div><strong>Guia do projeto</strong><span>Como usar, protótipo, páginas do site e perguntas em aberto</span></div>' . ico('seta') . '</a>';
    $lateral .= '<section class="bloco"><div class="sec-cab"><h2>Atalhos</h2></div>' . $at . '</section>';

    echo '<div class="home-grade"><div>';
    $paraValidar = [];
    if (recursos_novos() && ($eu['lado'] ?? '') === 'cliente') { // o cliente vê primeiro o que está esperando a validação dele
        foreach ($abertas as $i => $t) if ($t['status'] === 'validacao' && pode_agir($t, $eu)) { $paraValidar[] = $t; unset($abertas[$i]); }
    }
    if ($paraValidar) {
        echo '<h2 class="sec-validar">Esperando sua validação (' . count($paraValidar) . ') ' . ajuda('validar') . '</h2><div class="lista-tarefas">';
        foreach ($paraValidar as $t) cartao_tarefa($t, $eu, false, $volta);
        echo '</div>';
    }
    if (!empty($eu['is_admin']) && recursos_novos() && equipe_ativa() && $todas) { // o admin vê quem precisa ser avisado
        $qv = $pdo->query("SELECT DISTINCT p.id, p.nome, p.lado, p.celular FROM pessoas p WHERE p.ativo = 1 AND p.lado = 'cliente' AND (p.id IN (SELECT t.responsavel_id FROM tarefas t WHERE t.status = 'validacao') OR p.id IN (SELECT tp.pessoa_id FROM tarefa_pessoas tp JOIN tarefas t ON t.id = tp.tarefa_id WHERE t.status = 'validacao'))");
        $aval = $qv->fetchAll();
        if ($aval) echo '<div class="card aviso-zap"><strong>Há itens em validação.</strong> ' . wa_avisos_html($aval, 'validar') . '</div>';
    }
    if ($abertas && !$todas) {
        $iH = null;
        foreach ($abertas as $i => $t) { if ($t['status'] !== 'travado') { $iH = $i; break; } }
        $iH ??= array_key_first($abertas);
        cartao_tarefa($abertas[$iH], $eu, false, $volta, false, false, true);
        unset($abertas[$iH]);
    }
    if ($abertas) {
        echo '<div class="lista-tarefas">';
        foreach ($abertas as $t) cartao_tarefa($t, $eu, $todas, $volta);
        echo '</div>';
    }
    if ($feitas) {
        echo '<h2 style="margin-top:28px">Já feitas ' . ajuda('feitas') . '</h2><div class="lista-tarefas">';
        foreach ($feitas as $t) linha_feita($t, $todas);
        echo '</div>';
    }
    echo '</div><aside>' . $lateral . '</aside></div>';
}
rodape();
