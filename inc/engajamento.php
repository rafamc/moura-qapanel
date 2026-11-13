<?php
// Engajamento — etapa 3. SÓ LEITURA: transforma eventos (migration 007) e comentarios em números e datas.
// Não dá nota nem julga: mostra o que aconteceu e quando. Quem chama já passou por exigir_admin().

const ENG_PADRAO = ['nao_abriu' => 3, 'sem_acao' => 5, 'sem_acesso' => 7];

function eng_tabela(string $t): bool {
    static $c = [];
    if (!isset($c[$t])) { try { db()->query('SELECT 1 FROM `' . $t . '` LIMIT 1'); $c[$t] = true; } catch (Throwable $e) { $c[$t] = false; } }
    return $c[$t];
}

/** Limites dos alertas, em dias (migration 008; sem a tabela valem os padrões). */
function eng_limites(): array {
    $l = ENG_PADRAO;
    if (eng_tabela('ajustes')) {
        foreach (db()->query("SELECT chave, valor FROM ajustes WHERE chave IN ('eng_nao_abriu','eng_sem_acao','eng_sem_acesso')") as $r) {
            $k = substr($r['chave'], 4); $v = (int)$r['valor'];
            if (isset($l[$k]) && $v >= 1 && $v <= 90) $l[$k] = $v;
        }
    }
    return $l;
}

/** Data/hora do primeiro evento registrado: antes disso não existe dado de acesso. */
function eng_coleta(): ?string {
    if (!eng_tabela('eventos')) return null;
    $d = db()->query('SELECT MIN(criado_em) FROM eventos')->fetchColumn();
    return $d ?: null;
}

function eng_dias_desde(string $dt): int { return max(0, (int)floor((time() - strtotime($dt)) / 86400)); }

function eng_duracao(int $seg): string {
    if ($seg < 60) return 'menos de 1 min';
    if ($seg < 3600) return round($seg / 60) . ' min';
    if ($seg < 86400) { $h = intdiv($seg, 3600); $m = (int)round(($seg % 3600) / 60); return $h . ' h' . ($m ? ' ' . $m . ' min' : ''); }
    $d = intdiv($seg, 86400); $h = (int)round(($seg % 86400) / 3600); return $d . ' d' . ($h ? ' ' . $h . ' h' : '');
}

function eng_media(array $segs): array {
    return ['n' => count($segs), 'media' => $segs ? (int)round(array_sum($segs) / count($segs)) : null];
}

/** Números de uma pessoa. $inicio = data de início da coleta (eng_coleta). */
function eng_resumo(array $pessoa, ?string $inicio, array $lim): array {
    $pdo = db(); $pid = (int)$pessoa['id']; $evOk = eng_tabela('eventos'); $hoje = date('Y-m-d');
    $acessos = []; $abriu = []; $atrib = []; $dias = [];
    if ($evOk) {
        $st = $pdo->prepare('SELECT tipo, tarefa_id, criado_em FROM eventos WHERE pessoa_id = ? ORDER BY criado_em, id');
        $st->execute([$pid]);
        foreach ($st as $r) {
            $dias[substr($r['criado_em'], 0, 10)] = true;
            $tid = (int)$r['tarefa_id'];
            if ($r['tipo'] === 'login' || $r['tipo'] === 'acesso') $acessos[] = $r['criado_em'];
            elseif ($r['tipo'] === 'abriu_tarefa' && $tid) $abriu[$tid][] = $r['criado_em'];
            elseif ($r['tipo'] === 'atribuida' && $tid && !isset($atrib[$tid])) $atrib[$tid] = $r['criado_em'];
        }
    }
    $coment = []; $ultAcao = null;
    $st = $pdo->prepare('SELECT tarefa_id, created_at FROM comentarios WHERE pessoa_id = ? ORDER BY created_at, id');
    $st->execute([$pid]);
    foreach ($st as $r) { $coment[(int)$r['tarefa_id']][] = $r['created_at']; $dias[substr($r['created_at'], 0, 10)] = true; $ultAcao = $r['created_at']; }

    [$f, $p] = filtro_minhas($pid);
    $st = $pdo->prepare("SELECT t.id, t.titulo, t.prazo, t.status, t.created_at FROM tarefas t WHERE $f ORDER BY t.prazo, t.id");
    $st->execute($p);
    $abertas = $feitas = $atrasadas = $abriuAbertas = 0; $tAbrir = $tResp = $alertas = [];
    $primeiro = fn(array $lista, ?string $apos) => (function () use ($lista, $apos) { foreach ($lista as $x) if ($apos === null || $x >= $apos) return $x; return null; })();
    foreach ($st as $t) {
        $tid = (int)$t['id'];
        if ($t['status'] === 'feito') { $feitas++; continue; }
        $abertas++;
        if ($t['prazo'] < $hoje) $atrasadas++;
        if (!empty($abriu[$tid])) $abriuAbertas++;
        $real = $atrib[$tid] ?? null;
        $ref = $real ?? ($inicio ? max($t['created_at'], $inicio) : null);
        $po = $primeiro($abriu[$tid] ?? [], $ref);
        $pr = $real ? $primeiro($coment[$tid] ?? [], $real) : null;
        if ($real && $po) $tAbrir[] = strtotime($po) - strtotime($real);
        if ($real && $pr) $tResp[] = strtotime($pr) - strtotime($real);
        if (!$evOk || !$ref) continue;
        if (!$po) {
            $d = eng_dias_desde($ref);
            if ($d >= $lim['nao_abriu']) $alertas[] = ['t' => 'nao_abriu', 'tarefa' => $tid, 'titulo' => $t['titulo'], 'desde' => $ref, 'dias' => $d, 'estimado' => !$real];
        } elseif (!$primeiro($coment[$tid] ?? [], $po)) {
            $d = eng_dias_desde($po);
            if ($d >= $lim['sem_acao']) $alertas[] = ['t' => 'sem_acao', 'tarefa' => $tid, 'titulo' => $t['titulo'], 'desde' => $po, 'dias' => $d];
        }
    }
    $ultimo = $acessos ? end($acessos) : null;
    if ($evOk) {
        if ($ultimo === null && $inicio && eng_dias_desde($inicio) >= $lim['sem_acesso']) $alertas[] = ['t' => 'sem_acesso', 'desde' => $inicio, 'dias' => eng_dias_desde($inicio), 'nunca' => true];
        elseif ($ultimo !== null && eng_dias_desde($ultimo) >= $lim['sem_acesso']) $alertas[] = ['t' => 'sem_acesso', 'desde' => $ultimo, 'dias' => eng_dias_desde($ultimo), 'nunca' => false];
    }
    $quad = []; $ativos = 0;
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $on = isset($dias[$d]); if ($on) $ativos++;
        $quad[] = ['d' => $d, 's' => $on ? 'on' : (($inicio && $d < substr($inicio, 0, 10)) ? 'antes' : 'off')];
    }
    return ['ultimo_acesso' => $ultimo, 'ultima_acao' => $ultAcao, 'abertas' => $abertas, 'abriu_abertas' => $abriuAbertas,
            'feitas' => $feitas, 'atrasadas' => $atrasadas, 'abrir' => eng_media($tAbrir), 'resp' => eng_media($tResp),
            'quadrados' => $quad, 'ativos14' => $ativos, 'alertas' => $alertas];
}

/** Frase simples de cada alerta (já com HTML escapado). */
function eng_alerta_texto(array $a): string {
    $tar = isset($a['tarefa']) ? '<a href="' . e(url('tarefa.php?id=' . (int)$a['tarefa'])) . '">' . e($a['titulo']) . '</a>' : '';
    $dias = (int)$a['dias'] . ($a['dias'] == 1 ? ' dia' : ' dias');
    if ($a['t'] === 'nao_abriu') return $tar . ' — ' . ($a['estimado'] ? 'em aberto desde o início da coleta (' . e(dt($a['desde'])) . ')' : 'recebida em ' . e(dt($a['desde']))) . ', sem abrir há ' . $dias . '.';
    if ($a['t'] === 'sem_acao') return $tar . ' — abriu em ' . e(dt($a['desde'])) . ' e não registrou nenhuma ação há ' . $dias . '.';
    return $a['nunca'] ? 'Nenhum acesso registrado desde o início da coleta (' . e(dt($a['desde'])) . '), há ' . $dias . '.' : 'Último acesso em ' . e(dt($a['desde'], true)) . ', há ' . $dias . '.';
}

/** Linha do tempo da pessoa: acessos, aberturas, atribuições e ações, do mais recente para o mais antigo. */
function eng_linha_tempo(int $pid, int $limite = 80): array {
    $pdo = db(); $itens = [];
    if (eng_tabela('eventos')) {
        $st = $pdo->prepare('SELECT e.tipo, e.criado_em AS quando, t.id AS tid, t.titulo AS ttit, r.titulo AS rtit FROM eventos e LEFT JOIN tarefas t ON t.id = e.tarefa_id LEFT JOIN reunioes r ON r.id = e.reuniao_id WHERE e.pessoa_id = ? ORDER BY e.criado_em DESC, e.id DESC LIMIT ' . (int)$limite);
        $st->execute([$pid]);
        foreach ($st as $r) {
            $tar = $r['tid'] ? '«' . $r['ttit'] . '»' : '';
            $txt = ['login' => 'Entrou com a senha', 'acesso' => 'Acessou o painel', 'abriu_tarefa' => 'Abriu a tarefa ' . $tar,
                    'abriu_reuniao' => 'Abriu a reunião «' . $r['rtit'] . '»', 'atribuida' => 'Recebeu a tarefa ' . $tar][$r['tipo']] ?? $r['tipo'];
            $itens[] = ['q' => $r['quando'], 'txt' => $txt, 'tid' => $r['tid']];
        }
    }
    $st = $pdo->prepare('SELECT c.tipo, c.texto, c.created_at AS quando, t.id AS tid, t.titulo AS ttit FROM comentarios c LEFT JOIN tarefas t ON t.id = c.tarefa_id WHERE c.pessoa_id = ? ORDER BY c.created_at DESC, c.id DESC LIMIT ' . (int)$limite);
    $st->execute([$pid]);
    foreach ($st as $r) {
        $texto = mb_strlen($r['texto']) > 140 ? mb_substr($r['texto'], 0, 140) . '…' : $r['texto'];
        $itens[] = ['q' => $r['quando'], 'tid' => $r['tid'],
                    'txt' => ($r['tipo'] === 'comentario' ? 'Comentou na tarefa «' . $r['ttit'] . '»: ' . $texto : 'Na tarefa «' . $r['ttit'] . '»: ' . $texto)];
    }
    usort($itens, fn($a, $b) => strcmp($b['q'], $a['q']));
    return array_slice($itens, 0, $limite);
}