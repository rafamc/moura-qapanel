<?php
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/anexos.php';
$eu = exigir_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    aviso('Os arquivos passaram do limite de envio do servidor. Envie menos arquivos por vez.', 'erro');
    $ref = (string)($_SERVER['HTTP_REFERER'] ?? ''); voltar(strpos($ref, cfg('base_url')) !== false ? $ref : url());
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) { aviso('Sessão expirada. Tente de novo.', 'erro'); voltar(url()); }

$id = (int)($_POST['tarefa_id'] ?? 0);
$acao = (string)($_POST['acao'] ?? '');
$texto = trim((string)($_POST['texto'] ?? ''));
$st = db()->prepare('SELECT * FROM tarefas WHERE id = ?'); $st->execute([$id]);
$t = $st->fetch();
$volta = $_POST['volta'] ?? url();
if (!is_string($volta) || strpos($volta, cfg('base_url')) !== 0) $volta = url();
if (!$t) { aviso('Tarefa não encontrada.', 'erro'); voltar(url()); }

$pode = !empty($eu['is_admin']) || na_equipe($id, (int)$eu['id']);
if (!$pode && !in_array($acao, ['comentar', 'anexo_apagar'], true)) { aviso('Só quem participa da tarefa pode mudar o status. Você pode comentar.', 'erro'); voltar($volta); }

switch ($acao) {
    case 'feito':
        if (!$pode) break;
        mudar_status($id, (int)$eu['id'], 'feito', $texto);
        aviso('Marcada como feita. Obrigado!'); break;
    case 'travado':
        if (!$pode) break;
        if ($texto === '') { aviso('Conte o que está impedindo, para o ' . gestor() . ' conseguir ajudar.', 'erro'); break; }
        mudar_status($id, (int)$eu['id'], 'travado', $texto);
        aviso('Avisado. O ' . gestor() . ' vai ver e te ajudar.'); break;
    case 'reabrir':
        if (!$pode) break;
        mudar_status($id, (int)$eu['id'], 'a_fazer', $texto);
        aviso('Tarefa reaberta.'); break;
    case 'anexar':
        if (!$pode) break;
        $r = anexos_salvar($id, (int)$eu['id'], $_FILES['anexos'] ?? []);
        if ($r['n'] === 0 && !$r['erros']) { aviso('Escolha pelo menos um arquivo.', 'erro'); break; }
        aviso(trim(anexos_resumo($r)), $r['erros'] ? 'erro' : 'ok'); break;
    case 'anexo_apagar':
        $aid = (int)($_POST['anexo_id'] ?? 0);
        $q = db()->prepare('SELECT pessoa_id FROM anexos WHERE id = ? AND tarefa_id = ?'); $q->execute([$aid, $id]);
        $dono = $q->fetchColumn();
        if ($dono === false) { aviso('Arquivo não encontrado.', 'erro'); break; }
        if (!$eu['is_admin'] && (int)$dono !== (int)$eu['id']) { aviso('Só quem enviou o arquivo (ou o admin) pode apagar.', 'erro'); break; }
        anexo_apagar($aid); registrar($id, (int)$eu['id'], 'status', 'apagou um arquivo');
        aviso('Arquivo apagado.'); break;
    case 'comentar':
        if ($texto === '') { aviso('Escreva o comentário antes de enviar.', 'erro'); break; }
        registrar($id, (int)$eu['id'], 'comentario', $texto);
        aviso('Comentário salvo no histórico.'); break;
    default:
        aviso('Ação desconhecida.', 'erro');
}
voltar($volta);
