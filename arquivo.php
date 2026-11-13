<?php
// Entrega um arquivo do projeto, só para quem está logado. Imagens abrem com ?ver=1; todo o resto baixa (inclui .html e .svg, que nunca rodam dentro do painel).
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/arquivos.php';
exigir_login();
exigir_modulo('arquivos');
session_write_close();
try { $q = db()->prepare('SELECT * FROM arquivos WHERE id = ?'); $q->execute([(int)($_GET['id'] ?? 0)]); $a = $q->fetch(); }
catch (Throwable $e) { $a = null; }
$caminho = $a ? arquivos_dir() . '/' . $a['arquivo'] : '';
if (!$a || !preg_match('/^[a-f0-9]{32}$/', (string)$a['arquivo']) || !is_file($caminho)) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Arquivo não encontrado.'; exit; }
$ver = isset($_GET['ver']) && in_array($a['mime'], ARQ_VER, true);
$ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $a['nome_original']);
header('Content-Type: ' . ($ver ? $a['mime'] : 'application/octet-stream'));
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: ' . ($ver ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($a['nome_original']));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, max-age=3600');
readfile($caminho);
