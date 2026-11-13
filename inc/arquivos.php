<?php
// Arquivos do projeto para o cliente baixar (pack de logo, Media Kit, manuais). Quem sobe é o admin.
// Ficam em uploads/arquivos/ (acesso direto negado pelo .htaccess) e só saem por arquivo.php, com login.
require_once __DIR__ . '/anexos.php'; // reaproveita anexo_tamanho()

const ARQ_MAX_BYTES = 60 * 1024 * 1024;
// extensão => tipo esperado (null = só confere a extensão). Tudo o que não é imagem sai sempre como download.
const ARQ_EXT = [
    'zip' => null, 'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif',
    'svg' => null, 'ai' => null, 'eps' => null, 'psd' => null, 'html' => null, 'htm' => null, 'docx' => null, 'xlsx' => null, 'pptx' => null, 'txt' => null,
];
const ARQ_VER = ['image/png', 'image/jpeg', 'image/webp', 'image/gif']; // só imagens abrem no navegador

function arquivos_dir(): string { return dirname(__DIR__) . '/uploads/arquivos'; }

/** Arquivos agrupados por categoria: ['Logos' => [linha, ...], ...] */
function arquivos_agrupados(): array {
    try { $rows = db()->query('SELECT * FROM arquivos ORDER BY categoria, ordem, id')->fetchAll(); }
    catch (Throwable $e) { return []; }
    $g = [];
    foreach ($rows as $r) $g[$r['categoria']][] = $r;
    return $g;
}

/** Guarda um arquivo enviado. Devolve null se deu certo, ou a mensagem de erro. */
function arquivo_salvar($f, string $categoria, string $titulo, string $descricao, int $pessoaId): ?string {
    if (!is_array($f) || !isset($f['error']) || is_array($f['error']) || (int)$f['error'] === UPLOAD_ERR_NO_FILE) return 'Escolha um arquivo para enviar.';
    if ($categoria === '' || $titulo === '') return 'Categoria e título são obrigatórios.';
    $err = (int)$f['error'];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return 'O arquivo passou do limite de envio do servidor (upload_max_filesize no PHP).';
    if ($err !== UPLOAD_ERR_OK) return 'Falha no envio. Tente de novo.';
    $tam = (int)$f['size'];
    if ($tam <= 0) return 'Arquivo vazio.';
    if ($tam > ARQ_MAX_BYTES) return 'O arquivo passa de ' . (ARQ_MAX_BYTES / 1048576) . ' MB.';
    $nome = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', basename(str_replace('\\', '/', (string)$f['name']))));
    $nome = mb_substr($nome !== '' ? $nome : 'arquivo', 0, 200);
    $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
    if (!array_key_exists($ext, ARQ_EXT)) return 'Tipo não permitido (.' . $ext . '). Aceita: ' . implode(', ', array_keys(ARQ_EXT)) . '.';
    $tmp = (string)$f['tmp_name'];
    if (!is_uploaded_file($tmp)) return 'Envio inválido.';
    $esperado = ARQ_EXT[$ext];
    if ($esperado !== null && function_exists('finfo_open')) {
        $real = (string)finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp);
        if ($real !== $esperado) return 'O conteúdo não é um .' . $ext . ' válido.';
    }
    $dir = arquivos_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return 'A pasta de arquivos não existe e não consegui criar.';
    $guardado = bin2hex(random_bytes(16));
    if (!move_uploaded_file($tmp, $dir . '/' . $guardado)) return 'Não consegui guardar o arquivo.';
    try {
        db()->prepare('INSERT INTO arquivos (categoria, titulo, descricao, nome_original, arquivo, mime, tamanho, pessoa_id) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([mb_substr($categoria, 0, 80), mb_substr($titulo, 0, 200), mb_substr($descricao, 0, 300) ?: null, $nome, $guardado, $esperado ?? 'application/octet-stream', $tam, $pessoaId]);
    } catch (Throwable $e) {
        @unlink($dir . '/' . $guardado);
        return 'A tabela "arquivos" não existe. Rode o schema.sql.';
    }
    return null;
}

function arquivo_apagar(int $id): void {
    $q = db()->prepare('SELECT arquivo FROM arquivos WHERE id = ?'); $q->execute([$id]);
    $arq = $q->fetchColumn();
    if ($arq && preg_match('/^[a-f0-9]{32}$/', (string)$arq)) @unlink(arquivos_dir() . '/' . $arq);
    db()->prepare('DELETE FROM arquivos WHERE id = ?')->execute([$id]);
}
