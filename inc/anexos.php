<?php
// Anexos das tarefas: vários arquivos por tarefa. Ficam em uploads/ (acesso direto negado) e só saem por anexo.php, com login.
const ANEXO_MAX_BYTES = 25 * 1024 * 1024;
const ANEXO_MAX_POR_ENVIO = 20;
// extensão => tipo esperado (null = só confere a extensão; esses saem sempre como download)
const ANEXO_EXT = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
    'pdf' => 'application/pdf',
    'heic' => null, 'heif' => null, 'doc' => null, 'docx' => null, 'xls' => null, 'xlsx' => null, 'csv' => null, 'txt' => null, 'zip' => null,
];
const ANEXO_INLINE = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];

function anexos_dir(): string { return dirname(__DIR__) . '/uploads'; }

function anexos_da_tarefa(int $tarefaId): array {
    try {
        $q = db()->prepare('SELECT a.*, p.nome FROM anexos a JOIN pessoas p ON p.id = a.pessoa_id WHERE a.tarefa_id = ? ORDER BY a.id');
        $q->execute([$tarefaId]);
        return $q->fetchAll();
    } catch (Throwable $e) { return []; }
}

function anexo_tamanho(int $b): string {
    if ($b >= 1048576) return number_format($b / 1048576, 1, ',', '.') . ' MB';
    return max(1, (int)round($b / 1024)) . ' KB';
}

/** Grava os arquivos enviados no campo "anexos[]". Devolve ['n' => enviados, 'erros' => [...], 'nomes' => [...]]. */
function anexos_salvar(int $tarefaId, int $pessoaId, $files): array {
    $res = ['n' => 0, 'erros' => [], 'nomes' => []];
    if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) return $res;
    $dir = anexos_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { $res['erros'][] = 'a pasta de arquivos não existe no servidor'; return $res; }
    $fi = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    foreach ($files['name'] as $i => $nomeBruto) {
        $err = (int)$files['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        $nome = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', basename(str_replace('\\', '/', (string)$nomeBruto))));
        $nome = mb_substr($nome !== '' ? $nome : 'arquivo', 0, 200);
        if ($res['n'] >= ANEXO_MAX_POR_ENVIO) { $res['erros'][] = 'no máximo ' . ANEXO_MAX_POR_ENVIO . ' arquivos por envio'; break; }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { $res['erros'][] = $nome . ' (maior que o limite do servidor)'; continue; }
        if ($err !== UPLOAD_ERR_OK) { $res['erros'][] = $nome . ' (falha no envio)'; continue; }
        $tmp = (string)$files['tmp_name'][$i]; $tam = (int)$files['size'][$i];
        if ($tam > ANEXO_MAX_BYTES) { $res['erros'][] = $nome . ' (passa de ' . (ANEXO_MAX_BYTES / 1048576) . ' MB)'; continue; }
        if ($tam <= 0) { $res['erros'][] = $nome . ' (arquivo vazio)'; continue; }
        $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        if (!array_key_exists($ext, ANEXO_EXT)) { $res['erros'][] = $nome . ' (tipo não permitido)'; continue; }
        if (!is_uploaded_file($tmp)) { $res['erros'][] = $nome . ' (envio inválido)'; continue; }
        $esperado = ANEXO_EXT[$ext];
        if ($esperado !== null && $fi) {
            $real = (string)finfo_file($fi, $tmp);
            if ($real !== $esperado) { $res['erros'][] = $nome . ' (o conteúdo não é um .' . $ext . ' válido)'; continue; }
        }
        $guardado = bin2hex(random_bytes(16));
        if (!move_uploaded_file($tmp, $dir . '/' . $guardado)) { $res['erros'][] = $nome . ' (não consegui guardar)'; continue; }
        try {
            db()->prepare('INSERT INTO anexos (tarefa_id, pessoa_id, nome_original, arquivo, mime, tamanho) VALUES (?,?,?,?,?,?)')
                ->execute([$tarefaId, $pessoaId, $nome, $guardado, $esperado ?? 'application/octet-stream', $tam]);
        } catch (Throwable $e) {
            @unlink($dir . '/' . $guardado);
            $res['erros'][] = 'a tabela de anexos ainda não existe (falta rodar a migration 003)';
            break;
        }
        $res['n']++; $res['nomes'][] = $nome;
    }
    if ($res['n'] > 0) {
        $lista = implode(', ', array_slice($res['nomes'], 0, 5)) . (count($res['nomes']) > 5 ? '…' : '');
        registrar($tarefaId, $pessoaId, 'status', 'anexou ' . $res['n'] . ($res['n'] === 1 ? ' arquivo' : ' arquivos') . ': ' . $lista);
    }
    return $res;
}

function anexos_resumo(array $r): string {
    $s = '';
    if ($r['n'] > 0) $s .= ' ' . $r['n'] . ($r['n'] === 1 ? ' arquivo anexado.' : ' arquivos anexados.');
    if ($r['erros']) $s .= ' Não enviei: ' . implode('; ', $r['erros']) . '.';
    return $s;
}

function anexo_apagar(int $id): void {
    $q = db()->prepare('SELECT arquivo FROM anexos WHERE id = ?'); $q->execute([$id]);
    $arq = $q->fetchColumn();
    if ($arq) { @unlink(anexos_dir() . '/' . $arq); @unlink(anexo_mini_caminho($arq)); }
    db()->prepare('DELETE FROM anexos WHERE id = ?')->execute([$id]);
}

function anexos_apagar_da_tarefa(int $tarefaId): void {
    foreach (anexos_da_tarefa($tarefaId) as $a) { @unlink(anexos_dir() . '/' . $a['arquivo']); @unlink(anexo_mini_caminho($a['arquivo'])); }
}

/** Onde fica a miniatura (JPEG pequeno) de uma foto. Só existe se o servidor tiver a extensão GD. */
function anexo_mini_caminho(string $arquivo): string { return anexos_dir() . '/mini/' . $arquivo . '.jpg'; }

/** Gera a miniatura (lado maior 480px, gira conforme o EXIF do celular). Devolve false se não der; aí a tela usa a foto original. */
function anexo_mini_gerar(string $origem, string $destino): bool {
    if (!function_exists('imagecreatetruecolor')) return false;
    $info = @getimagesize($origem);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40000000) return false; // foto gigante: não arrisca a memória
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($origem); break;
        case IMAGETYPE_PNG:  $im = @imagecreatefrompng($origem); break;
        case IMAGETYPE_GIF:  $im = @imagecreatefromgif($origem); break;
        case IMAGETYPE_WEBP: $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($origem) : false; break;
        default: return false;
    }
    if (!$im) return false;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data($origem); $o = (int)($ex['Orientation'] ?? 1);
        $graus = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($graus) { $g = @imagerotate($im, $graus, 0); if ($g) { imagedestroy($im); $im = $g; } }
    }
    $w = imagesx($im); $h = imagesy($im); $k = min(1, 480 / max($w, $h));
    $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
    $d = imagecreatetruecolor($nw, $nh);
    imagefill($d, 0, 0, imagecolorallocate($d, 255, 255, 255)); // PNG transparente vira fundo branco
    imagecopyresampled($d, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $tmp = $destino . '.tmp';
    $ok = imagejpeg($d, $tmp, 78) && @rename($tmp, $destino);
    imagedestroy($im); imagedestroy($d);
    return $ok;
}

/** Campo de envio (vários arquivos) com prévia das fotos antes de enviar. O formulário que o contém precisa de enctype="multipart/form-data". */
function anexos_campo_html(): string {
    $ext = implode(',', array_keys(ANEXO_EXT));
    return '<div class="campo-arquivos" data-anexos data-max="' . ANEXO_MAX_BYTES . '" data-qtd="' . ANEXO_MAX_POR_ENVIO . '" data-ext="' . e($ext) . '">'
        . '<span class="lbl">Arquivos (opcional) ' . ajuda('anexos') . '</span>'
        . '<label class="zona"><input type="file" name="anexos[]" multiple accept="' . e('.' . str_replace(',', ',.', $ext)) . '">'
        . '<span class="zona-mais" aria-hidden="true">+</span>'
        . '<strong>Toque aqui para escolher fotos e arquivos</strong>'
        . '<span class="sm muted">Pode escolher vários de uma vez. No computador, também dá para arrastar para cá.</span></label>'
        . '<ul class="previa" hidden></ul><p class="previa-msg" role="status" hidden></p>'
        . '<p class="sm muted" style="margin:6px 0 0">Até ' . ANEXO_MAX_POR_ENVIO . ' arquivos por envio, ' . (ANEXO_MAX_BYTES / 1048576) . ' MB cada. Fotos, PDF, Word, Excel, CSV, TXT e ZIP.</p></div>';
}

/** Galeria dos arquivos da tarefa: fotos em miniatura (toca e amplia), os outros como cartão com o tipo. Apagar: quem enviou ou admin. */
function anexos_lista_html(array $lista, array $eu, string $volta): string {
    if (!$lista) return '<p class="muted sm">Nenhum arquivo ainda.</p>';
    $out = '<ul class="galeria" data-galeria>';
    foreach ($lista as $a) {
        $id = (int)$a['id'];
        $link = e(url('anexo.php?id=' . $id));
        $mini = e(url('anexo.php?id=' . $id . '&mini=1'));
        $nome = e($a['nome_original']);
        $img = strpos($a['mime'], 'image/') === 0;
        $pdf = $a['mime'] === 'application/pdf';
        $ext = strtoupper(pathinfo($a['nome_original'], PATHINFO_EXTENSION));
        $out .= '<li class="gi">';
        if ($img) {
            $out .= '<a class="gi-foto" href="' . $link . '" data-ampliar data-nome="' . $nome . '" target="_blank" rel="noopener" aria-label="Ampliar a foto ' . $nome . '"><img loading="lazy" src="' . $mini . '" alt="' . $nome . '"></a>';
        } else {
            $out .= '<a class="gi-foto gi-ico" href="' . $link . '" target="_blank" rel="noopener" aria-label="' . ($pdf ? 'Abrir ' : 'Baixar ') . $nome . '"><b>' . e($ext !== '' ? $ext : 'ARQ') . '</b><span>' . ($pdf ? 'Abrir' : 'Baixar') . '</span></a>';
        }
        $out .= '<div class="gi-info"><strong title="' . $nome . '">' . $nome . '</strong>'
            . '<span>' . e(anexo_tamanho((int)$a['tamanho'])) . ' · ' . e(primeiro_nome($a['nome'])) . '</span>'
            . '<span>' . e(dt($a['created_at'], true)) . '</span></div>';
        $out .= '<div class="gi-acoes"><a class="btn sec mini" href="' . $link . '" target="_blank" rel="noopener">' . ($img || $pdf ? 'Abrir' : 'Baixar') . '</a>';
        if (!empty($eu['is_admin']) || (int)$a['pessoa_id'] === (int)$eu['id']) {
            $out .= '<form method="post" action="' . e(url('acao.php')) . '" onsubmit="return confirm(\'Apagar este arquivo? Não dá para desfazer.\')">' . csrf_campo()
                . '<input type="hidden" name="acao" value="anexo_apagar"><input type="hidden" name="tarefa_id" value="' . (int)$a['tarefa_id'] . '"><input type="hidden" name="anexo_id" value="' . $id . '"><input type="hidden" name="volta" value="' . e($volta) . '">'
                . '<button class="btn perigo mini" type="submit">Apagar</button></form>';
        }
        $out .= '</div></li>';
    }
    return $out . '</ul>';
}