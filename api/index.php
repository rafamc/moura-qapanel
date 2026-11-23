<?php
declare(strict_types=1);
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/importar.php';

/**
 * API do painel (JSON). Autenticação: Authorization: Bearer <api_token> (ou ?token=).
 *   GET  api/index.php?r=estado            -> tudo (pessoas, tarefas+histórico, decisões, reuniões, páginas)
 *   POST api/index.php?r=importar[&dry=1]  -> corpo JSON no formato do importador (dry=1 só valida)
 *   POST api/index.php?r=comentario        -> {"tarefa_id":1,"texto":"..."}   (autor: 1º admin)
 *   POST api/index.php?r=status            -> {"tarefa_id":1,"status":"feito|travado|a_fazer|fazendo|validacao","nota":""}
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function resp(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($auth === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) if (strtolower($k) === 'authorization') $auth = $v;
}
$tok = preg_match('/^Bearer\s+(\S+)$/i', $auth, $m) ? $m[1] : (string)($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{40}$/', (string)cfg('api_token'))) resp(503, ['erro' => 'API desativada: api_token do config.php precisa ter 40 caracteres hex']);
if ($tok === '' || !hash_equals(cfg('api_token'), $tok)) resp(401, ['erro' => 'token inválido']);

$rota = $_GET['r'] ?? trim($_SERVER['PATH_INFO'] ?? '', '/');
$metodo = $_SERVER['REQUEST_METHOD'];
$pdo = db();
$admin = (int)$pdo->query('SELECT id FROM pessoas WHERE is_admin = 1 AND ativo = 1 ORDER BY id LIMIT 1')->fetchColumn();
$corpo = $metodo === 'POST' ? json_decode(file_get_contents('php://input') ?: '', true) : null;
if ($metodo === 'POST' && !is_array($corpo)) resp(400, ['erro' => 'corpo JSON inválido']);

switch ($rota) {
    case 'estado':
        $pessoas = $pdo->query('SELECT id, nome, papel, lado, is_admin, ativo FROM pessoas ORDER BY id')->fetchAll();
        $tarefas = $pdo->query('SELECT t.*, p.nome AS responsavel FROM tarefas t JOIN pessoas p ON p.id = t.responsavel_id ORDER BY t.prazo, t.id')->fetchAll();
        $tarefas = com_equipe($tarefas);
        $hist = [];
        foreach ($pdo->query('SELECT c.tarefa_id, c.tipo, c.texto, c.created_at, p.nome AS autor FROM comentarios c JOIN pessoas p ON p.id = c.pessoa_id ORDER BY c.created_at, c.id') as $c) {
            $hist[$c['tarefa_id']][] = ['tipo' => $c['tipo'], 'autor' => $c['autor'], 'texto' => $c['texto'], 'em' => $c['created_at']];
        }
        $hoje = date('Y-m-d'); $atrasadas = 0; $abertas = 0;
        foreach ($tarefas as &$t) {
            $t['historico'] = $hist[$t['id']] ?? [];
            if ($t['status'] !== 'feito') { $abertas++; if ($t['prazo'] < $hoje) $atrasadas++; }
        }
        unset($t);
        resp(200, [
            'gerado_em' => date('c'),
            'contagem' => ['tarefas_abertas' => $abertas, 'atrasadas' => $atrasadas],
            'pessoas' => $pessoas,
            'tarefas' => $tarefas,
            'decisoes' => $pdo->query('SELECT * FROM decisoes ORDER BY data DESC, id DESC')->fetchAll(),
            'reunioes' => $pdo->query('SELECT * FROM reunioes ORDER BY data DESC, id DESC')->fetchAll(),
            'paginas' => $pdo->query('SELECT slug, secao, titulo, conteudo, ordem, updated_at FROM paginas ORDER BY ordem, id')->fetchAll(),
        ]);

    case 'importar':
        if ($metodo !== 'POST') resp(405, ['erro' => 'use POST']);
        $r = importar_dados($corpo, $admin, isset($_GET['dry']));
        resp($r['ok'] ? 200 : 422, $r);

    case 'comentario':
        if ($metodo !== 'POST') resp(405, ['erro' => 'use POST']);
        $id = (int)($corpo['tarefa_id'] ?? 0); $txt = trim((string)($corpo['texto'] ?? ''));
        $ok = $pdo->prepare('SELECT 1 FROM tarefas WHERE id = ?'); $ok->execute([$id]);
        if (!$ok->fetchColumn() || $txt === '') resp(422, ['erro' => 'tarefa_id inexistente ou texto vazio']);
        registrar($id, $admin, 'comentario', $txt);
        resp(200, ['ok' => true]);

    case 'status':
        if ($metodo !== 'POST') resp(405, ['erro' => 'use POST']);
        $id = (int)($corpo['tarefa_id'] ?? 0); $novo = (string)($corpo['status'] ?? '');
        $ok = $pdo->prepare('SELECT 1 FROM tarefas WHERE id = ?'); $ok->execute([$id]);
        if (!$ok->fetchColumn() || !isset(STATUS[$novo]) || ($novo === 'validacao' && !recursos_novos())) resp(422, ['erro' => 'tarefa_id ou status inválido']);
        mudar_status($id, $admin, $novo, trim((string)($corpo['nota'] ?? '')));
        resp(200, ['ok' => true]);

    default:
        resp(404, ['erro' => 'rota desconhecida', 'rotas' => ['estado', 'importar', 'comentario', 'status']]);
}
