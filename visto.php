<?php
// Sinal discreto da Agenda: a ficha de uma tarefa foi aberta no calendário (conta como "abriu a tarefa" se a pessoa for da equipe dela).
require __DIR__ . '/inc/bootstrap.php';
$eu = exigir_login();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_ok()) {
    $id = (int)($_POST['tarefa_id'] ?? 0);
    if ($id > 0 && na_equipe($id, (int)$eu['id'])) evento('abriu_tarefa', (int)$eu['id'], $id);
}
http_response_code(204);