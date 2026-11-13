<?php
// Entrega um anexo de tarefa, só para quem está logado. Imagens e PDF abrem no navegador; o resto baixa.
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/anexos.php';
exigir_login();
session_write_close();
try { $q = db()->prepare('SELECT * FROM anexos WHERE id = ?'); $q->execute([(int)($_GET['id'] ?? 0)]); $a = $q->fetch(); }
catch (Throwable $e) { $a = null; }
$caminho = $a ? anexos_dir() . '/' . $a['arquivo'] : '';
if (!$a || !preg_match('/^[a-f0-9]{32}$/', (string)$a['arquivo']) || !is_file($caminho)) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Arquivo não encontrado.'; exit; }
if (isset($_GET['mini']) && in_array($a['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
    $mini = anexo_mini_caminho($a['arquivo']);
    if (!is_file($mini)) { @mkdir(dirname($mini), 0755, true); anexo_mini_gerar($caminho, $mini); }
    if (is_file($mini)) { // sem GD no servidor cai para a foto original, logo abaixo
        header('Content-Type: image/jpeg'); header('Content-Length: ' . filesize($mini));
        header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=86400');
        readfile($mini); exit;
    }
}
$inline = in_array($a['mime'], ANEXO_INLINE, true);
$ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $a['nome_original']);
header('Content-Type: ' . ($inline ? $a['mime'] : 'application/octet-stream'));
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($a['nome_original']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($caminho);