<?php
declare(strict_types=1);
/**
 * Importador: recebe a estrutura (array) gerada a partir de uma reunião e grava tudo de uma vez.
 * Formato:
 * { "reuniao": {"data":"2026-10-08","titulo":"...","resumo":"..."},
 *   "decisoes": [{"texto":"...","data":"2026-10-08"}],
 *   "tarefas":  [{"titulo":"...","descricao":"...","responsavel":"Nome","prazo":"2026-10-15"}],
 *   "paginas":  [{"slug":"...","secao":"...","titulo":"...","conteudo":"...","ordem":1}],
 *   "historico":[{"data":"2026-05-12","titulo":"...","descricao":"..."}] }  // exige a tabela da migration 002
 * Retorna ['ok'=>bool,'erros'=>[],'resumo'=>[]]. Com $simular=true valida sem gravar.
 */
function importar_dados(array $d, int $pessoaId, bool $simular = false): array {
    $pdo = db();
    $erros = [];

    $pessoas = $pdo->query('SELECT id, nome FROM pessoas WHERE ativo = 1')->fetchAll();
    $achar = function (string $nome) use ($pessoas): ?int {
        $n = mb_strtolower(trim($nome));
        if ($n === '') return null;
        foreach ($pessoas as $p) if (mb_strtolower($p['nome']) === $n) return (int)$p['id'];
        $hit = [];
        foreach ($pessoas as $p) if (mb_strpos(mb_strtolower($p['nome']), $n) !== false) $hit[] = (int)$p['id'];
        return count($hit) === 1 ? $hit[0] : null;
    };
    $dataOk = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int)substr($v,5,2), (int)substr($v,8,2), (int)substr($v,0,4));

    $reuniao = $d['reuniao'] ?? null;
    if ($reuniao !== null) {
        if (!$dataOk($reuniao['data'] ?? null)) $erros[] = 'reuniao.data inválida (use AAAA-MM-DD).';
        if (trim((string)($reuniao['titulo'] ?? '')) === '') $erros[] = 'reuniao.titulo vazio.';
    }
    $decisoes = $d['decisoes'] ?? [];
    foreach ($decisoes as $i => $x) {
        if (trim((string)($x['texto'] ?? '')) === '') $erros[] = "decisoes[$i]: texto vazio.";
        if (isset($x['data']) && !$dataOk($x['data'])) $erros[] = "decisoes[$i]: data inválida.";
    }
    $tarefas = $d['tarefas'] ?? [];
    $resp = [];
    foreach ($tarefas as $i => $x) {
        $n = $i + 1;
        if (trim((string)($x['titulo'] ?? '')) === '') $erros[] = "tarefas[$n]: título vazio.";
        $id = $achar((string)($x['responsavel'] ?? ''));
        if ($id === null) $erros[] = "tarefas[$n] (" . ($x['titulo'] ?? '?') . "): responsável \"" . ($x['responsavel'] ?? '') . "\" não encontrado ou ambíguo.";
        if (!$dataOk($x['prazo'] ?? null)) $erros[] = "tarefas[$n] (" . ($x['titulo'] ?? '?') . "): prazo ausente ou inválido. Sem data não é tarefa.";
        $resp[$i] = $id;
    }
    $paginas = $d['paginas'] ?? [];
    foreach ($paginas as $i => $x) {
        if (!preg_match('/^[a-z0-9-]{2,80}$/', (string)($x['slug'] ?? ''))) $erros[] = "paginas[$i]: slug inválido (a-z, 0-9, hífen).";
        if (trim((string)($x['titulo'] ?? '')) === '' || trim((string)($x['conteudo'] ?? '')) === '') $erros[] = "paginas[$i]: título e conteúdo são obrigatórios.";
    }

    $historico = $d['historico'] ?? [];
    foreach ($historico as $i => $x) {
        if (!$dataOk($x['data'] ?? null)) $erros[] = "historico[$i]: data inválida (use AAAA-MM-DD).";
        if (trim((string)($x['titulo'] ?? '')) === '') $erros[] = "historico[$i]: título vazio.";
    }
    if ($historico) {
        try { $pdo->query('SELECT 1 FROM historico LIMIT 1'); }
        catch (Throwable $t) { $erros[] = 'A tabela "historico" não existe. Rode migrations/002_historico.sql no phpMyAdmin antes.'; }
    }

    $resumo = ['reuniao' => $reuniao ? 1 : 0, 'decisoes' => count($decisoes), 'tarefas' => count($tarefas), 'paginas' => count($paginas), 'historico' => count($historico)];
    if ($erros || $simular) return ['ok' => !$erros, 'erros' => $erros, 'resumo' => $resumo, 'simulado' => $simular];

    $pdo->beginTransaction();
    try {
        $reuniaoId = null;
        if ($reuniao) {
            $pdo->prepare('INSERT INTO reunioes (data, titulo, resumo) VALUES (?,?,?)')
                ->execute([$reuniao['data'], trim($reuniao['titulo']), $reuniao['resumo'] ?? null]);
            $reuniaoId = (int)$pdo->lastInsertId();
        }
        $hoje = $reuniao['data'] ?? date('Y-m-d');
        foreach ($decisoes as $x) {
            $pdo->prepare('INSERT INTO decisoes (texto, data, reuniao_id) VALUES (?,?,?)')
                ->execute([trim($x['texto']), $x['data'] ?? $hoje, $reuniaoId]);
        }
        foreach ($tarefas as $i => $x) {
            $pdo->prepare('INSERT INTO tarefas (titulo, descricao, responsavel_id, prazo, reuniao_id) VALUES (?,?,?,?,?)')
                ->execute([trim($x['titulo']), $x['descricao'] ?? null, $resp[$i], $x['prazo'], $reuniaoId]);
            $novaTarefa = (int)$pdo->lastInsertId();
            registrar($novaTarefa, $pessoaId, 'status', 'tarefa criada' . ($reuniaoId ? ' a partir da reunião' : ''));
            evento('atribuida', (int)$resp[$i], $novaTarefa, null, 0);
            bola_pelo_responsavel($novaTarefa);
        }
        foreach ($paginas as $x) {
            $pdo->prepare('INSERT INTO paginas (slug, secao, titulo, conteudo, ordem) VALUES (?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE secao=VALUES(secao), titulo=VALUES(titulo), conteudo=VALUES(conteudo), ordem=VALUES(ordem)')
                ->execute([$x['slug'], $x['secao'] ?? 'Projeto', trim($x['titulo']), $x['conteudo'], (int)($x['ordem'] ?? 0)]);
        }
        foreach ($historico as $x) {
            $pdo->prepare('INSERT INTO historico (data, titulo, descricao) VALUES (?,?,?)')
                ->execute([$x['data'], trim($x['titulo']), trim((string)($x['descricao'] ?? '')) ?: null]);
        }
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        return ['ok' => false, 'erros' => ['Erro ao gravar: ' . $t->getMessage()], 'resumo' => $resumo];
    }
    return ['ok' => true, 'erros' => [], 'resumo' => $resumo, 'reuniao_id' => $reuniaoId];
}
