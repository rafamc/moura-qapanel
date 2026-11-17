<?php
declare(strict_types=1);

$CFG = require __DIR__ . '/config.php';
date_default_timezone_set($CFG['timezone']);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function cfg(string $k) { global $CFG; return $CFG[$k]; }
/** Valor opcional do config.php, com padrão: instalações com config antigo continuam funcionando. */
function opt(string $k, $padrao = '') { global $CFG; return $CFG[$k] ?? $padrao; }

/* ---------- Marca e módulos (tudo vem do config.php; nenhum cliente fixo no código) ---------- */
function gestor(): string { return (string)opt('gestor', 'Rafa'); }
function gestor_empresa(): string { return (string)opt('gestor_empresa', 'Moura Digital'); }
function cliente(): string { return (string)opt('cliente', 'Painel do projeto'); }          // nome completo: rodapé e login
function cliente_curto(): string { return (string)opt('cliente_curto', 'Cliente'); }        // como a equipe do cliente aparece no admin
function cookie_nome(string $sufixo): string { return (string)opt('cookie', 'mp') . '_' . $sufixo; } // um prefixo por instalação
function cores(): array {
    $c = opt('cores', []);
    return array_merge(['primary' => '#1f2a44', 'primary2' => '#2d3d63', 'destaque' => '#ffc641'], is_array($c) ? $c : []);
}
/** Módulo ligado? Sem a chave no config, fica ligado. Módulos: reunioes, decidido, historico, guia, arquivos, engajamento. */
function modulo(string $m): bool {
    $mods = opt('modulos', []);
    return !is_array($mods) || !array_key_exists($m, $mods) || (bool)$mods[$m];
}
function exigir_modulo(string $m): void { if (!modulo($m)) { header('Location: ' . url()); exit; } }

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . cfg('db_host') . ';port=' . (int)opt('db_port', 3306) . ';dbname=' . cfg('db_name') . ';charset=utf8mb4',
            cfg('db_user'), cfg('db_pass'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $pdo->exec("SET time_zone = '-03:00'");
    }
    return $pdo;
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string {
    // A home aponta para index.php de propósito: a URL limpa da pasta pode ficar presa em cache do servidor.
    if ($path === '') return rtrim(cfg('base_url'), '/') . '/index.php';
    return rtrim(cfg('base_url'), '/') . '/' . ltrim($path, '/');
}
function dt(?string $d, bool $hora = false): string {
    if (!$d) return '';
    $t = strtotime($d);
    return $hora ? date('d/m/Y H:i', $t) : date('d/m/Y', $t);
}
function primeiro_nome(string $n): string { return explode(' ', trim($n))[0]; }
/** Bolinha com as iniciais; a cor vem do id, então a mesma pessoa tem sempre a mesma cor. */
function avatar(string $nome, int $id = 0, string $tam = ''): string {
    $p = preg_split('/\s+/', trim($nome)) ?: ['?'];
    $i = mb_strtoupper(mb_substr($p[0], 0, 1));
    if (count($p) > 1) $i .= mb_strtoupper(mb_substr(end($p), 0, 1));
    return '<span class="av av-' . ($id % 6) . ($tam !== '' ? ' ' . $tam : '') . '" aria-hidden="true">' . e($i) . '</span>';
}

/* ---------- Autenticação: e-mail + senha (sessão guardada no banco) e link pessoal (legado) ---------- */
const SESSAO_DIAS = 30;

function cookie_opts(int $expires): array {
    return [
        'expires' => $expires, 'path' => rtrim(cfg('base_url'), '/') . '/',
        'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']),
    ];
}

function pessoa_atual(): ?array {
    static $p = false;
    if ($p !== false) return $p;
    $p = null;
    // 1) sessão de login (cookie ace_s; no banco fica só o hash)
    $s = $_COOKIE[cookie_nome('s')] ?? '';
    if (is_string($s) && preg_match('/^[a-f0-9]{64}$/', $s)) {
        $st = db()->prepare('SELECT p.* FROM sessoes s JOIN pessoas p ON p.id = s.pessoa_id WHERE s.token_hash = ? AND s.expira_em > NOW() AND p.ativo = 1');
        $st->execute([hash('sha256', $s)]);
        $p = $st->fetch() ?: null;
        if ($p) $p['_via'] = 'senha';
    }
    // 2) link pessoal (acesso antigo, continua funcionando)
    if ($p === null) {
        $t = $_GET['t'] ?? ($_COOKIE[cookie_nome('t')] ?? '');
        if (is_string($t) && preg_match('/^[a-f0-9]{40}$/', $t)) {
            $st = db()->prepare('SELECT * FROM pessoas WHERE token = ? AND ativo = 1');
            $st->execute([$t]);
            $p = $st->fetch() ?: null;
            if ($p) {
                $p['_via'] = 'link';
                if (isset($_GET['t'])) setcookie(cookie_nome('t'), $t, cookie_opts(time() + 86400 * 180));
            }
        }
    }
    return $p;
}

function iniciar_sessao(int $pessoaId): void {
    $tok = bin2hex(random_bytes(32));
    $pdo = db();
    $pdo->exec('DELETE FROM sessoes WHERE expira_em < NOW()');
    $pdo->prepare('INSERT INTO sessoes (pessoa_id, token_hash, expira_em) VALUES (?, ?, NOW() + INTERVAL ' . SESSAO_DIAS . ' DAY)')
        ->execute([$pessoaId, hash('sha256', $tok)]);
    setcookie(cookie_nome('s'), $tok, cookie_opts(time() + 86400 * SESSAO_DIAS));
    session_regenerate_id(true);
    unset($_SESSION['csrf']);
    evento('login', $pessoaId, null, null, 0);
}

function encerrar_sessao(): void {
    $s = $_COOKIE[cookie_nome('s')] ?? '';
    if (is_string($s) && preg_match('/^[a-f0-9]{64}$/', $s)) {
        db()->prepare('DELETE FROM sessoes WHERE token_hash = ?')->execute([hash('sha256', $s)]);
    }
    setcookie(cookie_nome('s'), '', cookie_opts(time() - 3600));
    setcookie(cookie_nome('t'), '', cookie_opts(time() - 3600));
}

/** Só deixa voltar para caminhos de dentro do painel (evita redirecionar para fora). */
function destino_seguro(string $v): string {
    $base = rtrim(cfg('base_url'), '/') . '/';
    if ($v !== '' && strpos($v, $base) === 0 && strpos($v, '//', strlen($base)) === false
        && !preg_match('/[\r\n]/', $v) && !preg_match('#/(login|sair)\.php#', $v)) {
        return $v;
    }
    return url();
}

/** '' -> null · e-mail válido -> minúsculo · inválido -> false */
function email_ou_null($v) {
    $v = strtolower(trim((string)$v));
    if ($v === '') return null;
    return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : false;
}

/** Id do admin que ainda não tem senha (habilita o "Primeiro acesso"), ou null. */
function admin_sem_senha(): ?int {
    $id = db()->query('SELECT id FROM pessoas WHERE is_admin = 1 AND ativo = 1 AND senha_hash IS NULL ORDER BY id LIMIT 1')->fetchColumn();
    return $id ? (int)$id : null;
}

function exigir_login(): array {
    $p = pessoa_atual();
    if (!$p) {
        $volta = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? (string)($_SERVER['REQUEST_URI'] ?? '') : '';
        $dest = url('login.php');
        if ($volta !== '' && destino_seguro($volta) === $volta) $dest .= '?volta=' . urlencode($volta);
        voltar($dest);
    }
    if (isset($_GET['t'])) { // limpa o token da URL
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    // senha provisória: só deixa seguir depois de criar a própria
    if (($p['_via'] ?? '') === 'senha' && !empty($p['senha_provisoria']) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'senha.php') {
        voltar(url('senha.php'));
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') evento('acesso', (int)$p['id']); // no máx. 1 a cada 30 min
    return $p;
}
function exigir_admin(): array {
    $p = exigir_login();
    if (!$p['is_admin']) { http_response_code(403); topo('Sem permissão'); echo '<div class="card"><p>Esta área é restrita à gestão do projeto.</p></div>'; rodape(); exit; }
    return $p;
}

/* ---------- CSRF e avisos ---------- */
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_campo(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">'; }
function csrf_ok(): bool { return isset($_POST['_csrf']) && hash_equals(csrf(), (string)$_POST['_csrf']); }
function aviso(?string $msg = null, string $tipo = 'ok'): ?array {
    if ($msg !== null) { $_SESSION['aviso'] = [$msg, $tipo]; return null; }
    $a = $_SESSION['aviso'] ?? null; unset($_SESSION['aviso']); return $a;
}
function voltar(string $para): void { header('Location: ' . $para); exit; }

/* ---------- Ajuda (?) ---------- */
function ajuda(string $chave): string {
    static $t = null;
    if ($t === null) $t = require __DIR__ . '/ajuda.php';
    $txt = $t[$chave] ?? '';
    if ($txt === '') return '';
    return '<button type="button" class="ajuda" aria-label="O que é isto?" data-texto="' . e($txt) . '">?</button>';
}

/* ---------- Domínio ---------- */
const STATUS = ['a_fazer' => 'A fazer', 'fazendo' => 'Fazendo', 'feito' => 'Feita', 'travado' => 'Travou'];

function prazo_info(string $prazo, string $status): array {
    if ($status === 'feito') return ['', 'ok'];
    $dias = (int)round((strtotime($prazo) - strtotime(date('Y-m-d'))) / 86400);
    if ($dias < 0)  return ['atrasada há ' . abs($dias) . ($dias === -1 ? ' dia' : ' dias'), 'atraso'];
    if ($dias === 0) return ['é hoje', 'hoje'];
    if ($dias === 1) return ['é amanhã', 'breve'];
    if ($dias <= 3)  return ['em ' . $dias . ' dias', 'breve'];
    return ['em ' . $dias . ' dias', 'ok'];
}

function registrar(int $tarefaId, int $pessoaId, string $tipo, string $texto): void {
    db()->prepare('INSERT INTO comentarios (tarefa_id, pessoa_id, tipo, texto) VALUES (?,?,?,?)')
        ->execute([$tarefaId, $pessoaId, $tipo, $texto]);
}

/* ---------- Engajamento (migration 007): registro só de leitura para o admin; nunca derruba a página ---------- */
function evento(string $tipo, int $pessoaId, ?int $tarefaId = null, ?int $reuniaoId = null, int $janelaMin = 30): void {
    static $ativo = null, $conta = [];
    try {
        if ($ativo === null) { try { db()->query('SELECT 1 FROM eventos LIMIT 1'); $ativo = true; } catch (Throwable $e) { $ativo = false; } }
        if (!$ativo || $pessoaId <= 0) return;
        if (!isset($conta[$pessoaId])) { // qualquer pessoa ativa é registrada; a tela de Engajamento filtra quem aparece
            $st = db()->prepare('SELECT 1 FROM pessoas WHERE id = ? AND ativo = 1');
            $st->execute([$pessoaId]);
            $conta[$pessoaId] = (bool)$st->fetchColumn();
        }
        if (!$conta[$pessoaId]) return;
        if ($janelaMin > 0) { // repetição dentro da janela conta como o mesmo evento
            $st = db()->prepare('SELECT 1 FROM eventos WHERE pessoa_id = ? AND tipo = ? AND tarefa_id <=> ? AND reuniao_id <=> ? AND criado_em > DATE_SUB(NOW(), INTERVAL ' . (int)$janelaMin . ' MINUTE) LIMIT 1');
            $st->execute([$pessoaId, $tipo, $tarefaId, $reuniaoId]);
            if ($st->fetchColumn()) return;
        }
        db()->prepare('INSERT INTO eventos (pessoa_id, tipo, tarefa_id, reuniao_id) VALUES (?,?,?,?)')->execute([$pessoaId, $tipo, $tarefaId, $reuniaoId]);
    } catch (Throwable $e) { /* medir nunca pode atrapalhar quem está trabalhando */ }
}
/* ---------- De quem é a vez ("bola com"): mede quanto tempo cada tarefa fica parada com cada lado ----------
   Regra: a bola começa com o lado do responsável. "Travou" passa a bola para o outro lado. "Feita" tira a bola de jogo.
   Cada troca fica em bola_log (início e fim), e é isso que alimenta os números do admin. Medir nunca derruba a tela. */
const LADOS = ['cliente', 'moura'];
function definir_bola(int $tarefaId, ?string $bola): void {
    try {
        $bola = in_array($bola, LADOS, true) ? $bola : null;
        $pdo = db();
        $st = $pdo->prepare('SELECT bola_com FROM tarefas WHERE id = ?'); $st->execute([$tarefaId]);
        $atual = $st->fetchColumn();
        if ($atual === false) return;
        $atual = $atual !== null && $atual !== '' ? (string)$atual : null;
        if ($atual === $bola) return;
        $pdo->prepare('UPDATE bola_log SET fim = NOW() WHERE tarefa_id = ? AND fim IS NULL')->execute([$tarefaId]);
        if ($bola !== null) $pdo->prepare('INSERT INTO bola_log (tarefa_id, bola, inicio) VALUES (?,?,NOW())')->execute([$tarefaId, $bola]);
        $pdo->prepare('UPDATE tarefas SET bola_com = ?, bola_desde = ' . ($bola === null ? 'NULL' : 'NOW()') . ' WHERE id = ?')->execute([$bola, $tarefaId]);
    } catch (Throwable $e) { /* sem a tabela (instalação antiga): segue sem medir */ }
}
/** A bola vai para o lado do responsável da tarefa ($inverter = o outro lado, usado quando a tarefa trava). */
function bola_pelo_responsavel(int $tarefaId, bool $inverter = false): void {
    try {
        $st = db()->prepare('SELECT p.lado FROM tarefas t JOIN pessoas p ON p.id = t.responsavel_id WHERE t.id = ?'); $st->execute([$tarefaId]);
        $lado = $st->fetchColumn();
        if (!in_array($lado, LADOS, true)) return;
        definir_bola($tarefaId, $inverter ? ($lado === 'moura' ? 'cliente' : 'moura') : (string)$lado);
    } catch (Throwable $e) { }
}
function bola_nome(string $lado): string { return $lado === 'moura' ? gestor_empresa() : cliente_curto(); }
/** Etiqueta "A vez é de: X · há N dias". Vazia se a tarefa não tem bola (feita ou instalação sem a coluna). */
function bola_badge(array $t): string {
    if (empty($t['bola_com']) || !in_array($t['bola_com'], LADOS, true)) return '';
    $dias = !empty($t['bola_desde']) ? (int)floor((time() - strtotime($t['bola_desde'])) / 86400) : 0;
    $txt = 'A vez é de: ' . bola_nome($t['bola_com']) . ($dias >= 1 ? ' · há ' . $dias . ($dias === 1 ? ' dia' : ' dias') : '');
    return '<span class="badge bola bola-' . e($t['bola_com']) . '">' . e($txt) . '</span>';
}

function mudar_status(int $tarefaId, int $pessoaId, string $novo, string $nota = ''): void {
    $pdo = db();
    $pdo->prepare('UPDATE tarefas SET status = ?, concluida_em = ? WHERE id = ?')
        ->execute([$novo, $novo === 'feito' ? date('Y-m-d H:i:s') : null, $tarefaId]);
    if ($novo === 'feito') definir_bola($tarefaId, null);
    elseif ($novo === 'travado') bola_pelo_responsavel($tarefaId, true);
    else bola_pelo_responsavel($tarefaId);
    $rot = ['feito' => 'marcou como feita', 'travado' => 'avisou que travou', 'a_fazer' => 'reabriu a tarefa', 'fazendo' => 'começou a fazer'][$novo];
    registrar($tarefaId, $pessoaId, 'status', $rot . ($nota !== '' ? ': ' . $nota : ''));
}

/* ---------- Equipe da tarefa: o responsável (tarefas.responsavel_id) + quem também participa (tarefa_pessoas, migration 006) ---------- */
function equipe_ativa(): bool {
    static $ok = null;
    if ($ok === null) { try { db()->query('SELECT 1 FROM tarefa_pessoas LIMIT 1'); $ok = true; } catch (Throwable $e) { $ok = false; } }
    return $ok;
}
/** Acrescenta a cada tarefa: 'participantes' (quem também participa: [id, nome]) e 'equipe_ids' (responsável + participantes). */
function com_equipe(array $tarefas): array {
    $ids = array_map(fn($t) => (int)$t['id'], $tarefas);
    $mapa = [];
    if ($ids && equipe_ativa()) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT tp.tarefa_id, p.id, p.nome FROM tarefa_pessoas tp JOIN pessoas p ON p.id = tp.pessoa_id WHERE tp.tarefa_id IN ($in) AND p.ativo = 1 ORDER BY p.nome");
        $st->execute($ids);
        foreach ($st as $r) $mapa[(int)$r['tarefa_id']][] = ['id' => (int)$r['id'], 'nome' => (string)$r['nome']];
    }
    foreach ($tarefas as &$t) {
        $t['participantes'] = $mapa[(int)$t['id']] ?? [];
        $t['equipe_ids'] = array_merge([(int)$t['responsavel_id']], array_column($t['participantes'], 'id'));
    }
    unset($t);
    return $tarefas;
}
/** Responsável + participantes, o responsável primeiro. Exige $t['responsavel'] (nome). */
function equipe_lista(array $t): array {
    return array_merge([['id' => (int)$t['responsavel_id'], 'nome' => (string)$t['responsavel']]], $t['participantes'] ?? []);
}
function juntar_nomes(array $nomes): string {
    $nomes = array_values($nomes); $ult = array_pop($nomes);
    return $nomes ? implode(', ', $nomes) . ' e ' . $ult : (string)$ult;
}
function equipe_texto(array $t, ?int $semId = null): string {
    $n = [];
    foreach (equipe_lista($t) as $x) if ($semId === null || $x['id'] !== $semId) $n[] = primeiro_nome($x['nome']);
    return juntar_nomes($n);
}
function equipe_curta(array $t): string {
    $n = count($t['participantes'] ?? []);
    return primeiro_nome((string)$t['responsavel']) . ($n ? ' +' . $n : '');
}
function equipe_html(array $t, bool $forte = false): string {
    $av = '';
    foreach (equipe_lista($t) as $x) $av .= avatar($x['nome'], $x['id'], 'sm');
    $txt = e(equipe_texto($t));
    return $av . ($forte ? '<strong>' . $txt . '</strong>' : $txt);
}
function pode_agir(array $t, array $eu): bool {
    return !empty($eu['is_admin']) || in_array((int)$eu['id'], $t['equipe_ids'] ?? [(int)$t['responsavel_id']], true);
}
function na_equipe(int $tarefaId, int $pessoaId): bool {
    $st = db()->prepare('SELECT 1 FROM tarefas WHERE id = ? AND responsavel_id = ?');
    $st->execute([$tarefaId, $pessoaId]);
    if ($st->fetchColumn()) return true;
    if (!equipe_ativa()) return false;
    $st = db()->prepare('SELECT 1 FROM tarefa_pessoas WHERE tarefa_id = ? AND pessoa_id = ?');
    $st->execute([$tarefaId, $pessoaId]);
    return (bool)$st->fetchColumn();
}
/** Condição SQL (alias t) das tarefas "minhas": responsável ou participante. Devolve [sql, parâmetros]. */
function filtro_minhas(int $uid): array {
    if (!equipe_ativa()) return ['t.responsavel_id = ?', [$uid]];
    return ['(t.responsavel_id = ? OR t.id IN (SELECT tarefa_id FROM tarefa_pessoas WHERE pessoa_id = ?))', [$uid, $uid]];
}
/** Grava quem também participa (sem o responsável). Só mexe no que mudou e registra no histórico da tarefa. */
function salvar_equipe(int $tarefaId, int $responsavelId, $ids, int $autorId = 0): void {
    if (!equipe_ativa()) return;
    $pdo = db();
    $novos = array_values(array_unique(array_filter(array_map('intval', (array)$ids), fn($i) => $i > 0 && $i !== $responsavelId)));
    if ($novos) {
        $in = implode(',', array_fill(0, count($novos), '?'));
        $st = $pdo->prepare("SELECT id FROM pessoas WHERE ativo = 1 AND id IN ($in)");
        $st->execute($novos);
        $novos = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    sort($novos);
    $st = $pdo->prepare('SELECT pessoa_id FROM tarefa_pessoas WHERE tarefa_id = ? ORDER BY pessoa_id');
    $st->execute([$tarefaId]);
    $antes = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    if ($antes === $novos) return;
    foreach (array_diff($antes, $novos) as $pid) $pdo->prepare('DELETE FROM tarefa_pessoas WHERE tarefa_id = ? AND pessoa_id = ?')->execute([$tarefaId, $pid]);
    foreach (array_diff($novos, $antes) as $pid) { $pdo->prepare('INSERT INTO tarefa_pessoas (tarefa_id, pessoa_id) VALUES (?,?)')->execute([$tarefaId, $pid]); evento('atribuida', (int)$pid, $tarefaId, null, 0); }
    if ($autorId) registrar($tarefaId, $autorId, 'status', $novos ? 'definiu quem participa da tarefa' : 'tirou os participantes extras da tarefa');
}

/* ---------- Markdown mínimo ---------- */
function md_inline(string $s): string {
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($s));
    return preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)"\x27]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $s);
}
function md(?string $t): string {
    $t = str_replace(["\r\n", "\r"], "\n", trim((string)$t));
    $out = ''; $lista = false;
    foreach (explode("\n", $t) as $linha) {
        $l = rtrim($linha);
        if (preg_match('/^\s*(?:[-*]|\d+\.)\s+(.*)$/', $l, $m)) {
            if (!$lista) { $out .= '<ul>'; $lista = true; }
            $out .= '<li>' . md_inline($m[1]) . '</li>';
            continue;
        }
        if ($lista) { $out .= '</ul>'; $lista = false; }
        if ($l === '') continue;
        if (preg_match('/^(#{1,3})\s+(.*)$/', $l, $m)) {
            $n = strlen($m[1]) + 1;
            $out .= "<h$n>" . md_inline($m[2]) . "</h$n>";
            continue;
        }
        $out .= '<p>' . md_inline($l) . '</p>';
    }
    if ($lista) $out .= '</ul>';
    return $out;
}


const MESES_ABR = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
/* ---------- Apresentação: ícones e trechos (visual vindo do Stitch, sem fonte de ícones) ---------- */
function ico(string $n): string {
    static $d = [
        'tela'   => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'check'  => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 9.5"/>',
        'ajuda'  => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01"/>',
        'tarefa' => '<rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4h6v3H9zM9 12h6M9 16h4"/>',
        'seta'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'livro'  => '<path d="M5 4h11a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3zM5 17a3 3 0 0 1 3-3h11"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($d[$n] ?? '') . '</svg>';
}
/** Primeira frase útil de um texto em markdown simples, para descrever um cartão. */
function trecho(?string $md, int $max = 110): string {
    foreach (preg_split('/\R/', (string)$md) as $l) {
        $l = trim(preg_replace('/^\s*(?:[-*]|\d+\.)\s+/', '', $l));
        if ($l === '' || $l[0] === '#') continue;
        $l = str_replace('**', '', $l);
        return mb_strlen($l) > $max ? rtrim(mb_substr($l, 0, $max - 1)) . '…' : $l;
    }
    return '';
}

/* ---------- Layout ---------- */
function topo(string $titulo, string $ativo = '', bool $menu = true): void {
    $p = pessoa_atual();
    $abas = ['index' => 'Para mim', 'decidido' => 'Decidido', 'projeto' => 'Guia do projeto', 'reunioes' => 'Reuniões', 'historico' => 'Histórico', 'arquivos' => 'Arquivos', 'relatar' => 'Relatar problema'];
    $deModulo = ['decidido' => 'decidido', 'projeto' => 'guia', 'reunioes' => 'reunioes', 'historico' => 'historico', 'arquivos' => 'arquivos', 'relatar' => 'relatos'];
    foreach ($deModulo as $aba => $m) if (!modulo($m)) unset($abas[$aba]);
    $cor = cores();
    $letra = mb_strtoupper(mb_substr((string)opt('marca', 'P'), 0, 1));
    $fav = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="' . $cor['primary'] . '"/><text x="32" y="45" font-size="36" font-family="Georgia,serif" font-weight="700" text-anchor="middle" fill="' . $cor['destaque'] . '">' . htmlspecialchars($letra, ENT_QUOTES) . '</text></svg>');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="' . e($cor['primary']) . '">
<link rel="icon" href="' . e($fav) . '">
<title>' . e($titulo) . ' · ' . e(trim(opt('marca', 'Painel') . ' ' . opt('marca_em', ''))) . '</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="' . e(url('assets/painel.css')) . '?v=13">
<link rel="stylesheet" href="' . e(url('assets/painel-v2.css')) . '?v=8">
<style>:root{--primary:' . e($cor['primary']) . ';--primary-2:' . e($cor['primary2']) . ';--gold:' . e($cor['destaque']) . ';--primary-soft:color-mix(in srgb,' . e($cor['primary']) . ' 10%,#fff);--surf-low:color-mix(in srgb,' . e($cor['primary']) . ' 6%,#fff);--surf-hi:color-mix(in srgb,' . e($cor['primary']) . ' 12%,#fff);--bg:color-mix(in srgb,' . e($cor['primary']) . ' 8%,#eceef1)}</style>
</head><body>
<a class="skip" href="#conteudo">Ir para o conteúdo</a>
<header class="topo"><div class="wrap topo-in">
  <a class="marca" href="' . e(url()) . '"><span class="marca-sigla">' . e((string)opt('marca', 'Painel')) . ' <em>' . e((string)opt('marca_em', '')) . '</em></span><span class="marca-sub"><b>' . e((string)opt('subtitulo', 'Painel do projeto')) . '</b></span></a>';
    if ($p && $menu) {
        echo '<div class="topo-dir"><a class="topo-link" href="' . e(url('senha.php')) . '">Senha</a>'
           . '<span class="quem">' . avatar($p['nome'], (int)$p['id'], 'sm') . e(primeiro_nome($p['nome'])) . '</span>'
           . '<form method="post" action="' . e(url('sair.php')) . '">' . csrf_campo() . '<button class="topo-link" type="submit">Sair</button></form></div>';
    }
    echo '</div>';
    if ($p && $menu) {
        echo '<nav class="wrap abas" aria-label="Seções">';
        foreach ($abas as $k => $v) {
            echo '<a href="' . e(url($k === 'index' ? '' : $k . '.php')) . '"' . ($ativo === $k ? ' class="on" aria-current="page"' : '') . '>' . e($v) . '</a>';
        }
        if ($p['is_admin']) echo '<a class="adm' . ($ativo === 'admin' ? ' on' : '') . '" href="' . e(url('admin/')) . '">Admin</a>';
        echo '</nav>';
    }
    echo '</header><main class="wrap" id="conteudo">';
    if ($a = aviso()) echo '<div class="aviso ' . e($a[1]) . '" role="status">' . e($a[0]) . '</div>';
}
function rodape(): void {
    echo '</main><footer class="wrap rodape"><strong>' . e(cliente()) . '</strong><br>Painel feito pela ' . e(gestor_empresa()) . (modulo('engajamento') ? '<br><span class="sm">' . e((string)opt('aviso_registro', 'Este painel registra acessos e ações nas tarefas para acompanhar o andamento do projeto.')) . '</span>' : '') . '</footer>
<script src="' . e(url('assets/painel.js')) . '?v=3"></script><script src="' . e(url('assets/anexos.js')) . '?v=2"></script></body></html>';
}
