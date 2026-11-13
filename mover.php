<?php
/* Move uma tarefa de coluna no quadro (arrastar e soltar). Responde JSON. */
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
function resp_json(bool $ok, string $msg = '', int $code = 200): void {
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
$eu = pessoa_atual();
if (!$eu) resp_json(false, 'Sessão expirada. Entre de novo.', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) resp_json(false, 'Sessão expirada. Recarregue a página.', 403);

$id = (int)($_POST['tarefa_id'] ?? 0);
$novo = (string)($_POST['status'] ?? '');
$texto = trim((string)($_POST['texto'] ?? ''));
if (!in_array($novo, ['a_fazer', 'fazendo', 'travado', 'feito'], true)) resp_json(false, 'Coluna inválida.', 422);

$st = db()->prepare('SELECT * FROM tarefas WHERE id = ?');
$st->execute([$id]);
$t = $st->fetch();
if (!$t) resp_json(false, 'Tarefa não encontrada.', 404);
if (!(!empty($eu['is_admin']) || na_equipe($id, (int)$eu['id']))) resp_json(false, 'Só quem participa da tarefa pode movê-la.', 403);
if ($novo === 'travado' && $texto === '') resp_json(false, 'Conte o que está impedindo.', 422);
if ($t['status'] === $novo) resp_json(true);

// O banco precisa ter o status "fazendo" (migração 004). Sem isso, o MySQL gravaria vazio.
if ($novo === 'fazendo') {
    $col = db()->query("SHOW COLUMNS FROM tarefas LIKE 'status'")->fetch();
    if (!$col || strpos((string)($col['Type'] ?? ''), 'fazendo') === false) {
        resp_json(false, 'O banco ainda não tem a coluna "Fazendo" (rodar migrations/004_fazendo.sql).', 500);
    }
}
try {
    mudar_status($id, (int)$eu['id'], $novo, $texto);
} catch (Throwable $e) {
    resp_json(false, 'Não foi possível mover a tarefa.', 500);
}
resp_json(true);
