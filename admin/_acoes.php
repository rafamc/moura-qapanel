<?php
// Tratamento dos formulários do admin (POST). Incluído por index.php.
function admin_post(array $eu): void {
    require_once __DIR__ . '/../inc/anexos.php';
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { aviso('Os arquivos passaram do limite de envio do servidor. Envie menos arquivos por vez.', 'erro'); voltar(url('admin/')); }
    $pdo = db();
    if (!csrf_ok()) { aviso('Sessão expirada. Recarregue a página.', 'erro'); voltar(url('admin/')); }
    $a = (string)($_POST['a'] ?? '');
    $v = (string)($_POST['v'] ?? 'tarefas');
    $f = in_array($_POST['f'] ?? '', ['abertas', 'atrasadas', 'travadas', 'concluidas', 'todas'], true) ? (string)$_POST['f'] : '';
    $dest = url('admin/?v=' . urlencode($v) . ($f !== '' ? '&f=' . $f : ''));
    $data = fn($s) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$s) ? $s : null;

    switch ($a) {
        case 'tarefa_nova':
            $titulo = trim((string)$_POST['titulo']); $resp = (int)$_POST['responsavel_id']; $prazo = $data($_POST['prazo'] ?? '');
            if ($titulo === '' || !$resp || !$prazo) { aviso('Tarefa precisa de título, responsável e prazo. Sem isso é só ideia.', 'erro'); break; }
            $pdo->prepare('INSERT INTO tarefas (titulo, descricao, responsavel_id, prazo, reuniao_id) VALUES (?,?,?,?,?)')
                ->execute([$titulo, trim((string)$_POST['descricao']) ?: null, $resp, $prazo, (int)($_POST['reuniao_id'] ?? 0) ?: null]);
            $novoId = (int)$pdo->lastInsertId();
            registrar($novoId, (int)$eu['id'], 'status', 'tarefa criada');
            evento('atribuida', $resp, $novoId, null, 0);
            salvar_equipe($novoId, $resp, $_POST['participantes'] ?? [], (int)$eu['id']);
            if (in_array($_POST['bola'] ?? '', LADOS, true)) definir_bola($novoId, (string)$_POST['bola']); else bola_pelo_responsavel($novoId);
            $ax = anexos_salvar($novoId, (int)$eu['id'], $_FILES['anexos'] ?? []);
            aviso('Tarefa criada.' . anexos_resumo($ax), $ax['erros'] ? 'erro' : 'ok'); break;
        case 'tarefa_editar':
            $id = (int)$_POST['id']; $prazo = $data($_POST['prazo'] ?? ''); $resp = (int)$_POST['responsavel_id'];
            if (trim((string)$_POST['titulo']) === '' || !$resp || !$prazo) { aviso('Título, responsável e prazo são obrigatórios.', 'erro'); break; }
            $respAntes = (int)$pdo->query('SELECT responsavel_id FROM tarefas WHERE id = ' . $id)->fetchColumn();
            $pdo->prepare('UPDATE tarefas SET titulo=?, descricao=?, responsavel_id=?, prazo=?, reuniao_id=? WHERE id=?')
                ->execute([trim($_POST['titulo']), trim((string)$_POST['descricao']) ?: null, $resp, $prazo, (int)($_POST['reuniao_id'] ?? 0) ?: null, $id]);
            if ($resp !== $respAntes) evento('atribuida', $resp, $id, null, 0);
            salvar_equipe($id, $resp, $_POST['participantes'] ?? [], (int)$eu['id']);
            $novoSt = (string)($_POST['situacao'] ?? '');
            $atualSt = $pdo->prepare('SELECT status FROM tarefas WHERE id = ?'); $atualSt->execute([$id]);
            if (in_array($novoSt, ['a_fazer', 'fazendo', 'feito', 'travado'], true) && $novoSt !== $atualSt->fetchColumn()) mudar_status($id, (int)$eu['id'], $novoSt);
            if ($pdo->query('SELECT status FROM tarefas WHERE id = ' . $id)->fetchColumn() !== 'feito') {
                if (in_array($_POST['bola'] ?? '', LADOS, true)) definir_bola($id, (string)$_POST['bola']);
                elseif ($resp !== $respAntes) bola_pelo_responsavel($id);
            }
            registrar($id, (int)$eu['id'], 'status', 'editou a tarefa');
            $ax = anexos_salvar($id, (int)$eu['id'], $_FILES['anexos'] ?? []);
            aviso('Tarefa atualizada.' . anexos_resumo($ax), $ax['erros'] ? 'erro' : 'ok'); break;
        case 'tarefa_status':
            $id = (int)$_POST['id']; $novo = (string)($_POST['novo'] ?? '');
            if (!$id || !in_array($novo, ['a_fazer', 'fazendo', 'feito', 'travado'], true)) break;
            mudar_status($id, (int)$eu['id'], $novo);
            aviso($novo === 'feito' ? 'Tarefa concluída.' : 'Tarefa reaberta.'); break;
        case 'tarefa_apagar':
            anexos_apagar_da_tarefa((int)$_POST['id']);
            $pdo->prepare('DELETE FROM tarefas WHERE id = ?')->execute([(int)$_POST['id']]); aviso('Tarefa apagada.'); break;
        case 'decisao_nova':
            $d = $data($_POST['data'] ?? '');
            if (trim((string)$_POST['texto']) === '' || !$d) { aviso('Texto e data são obrigatórios.', 'erro'); break; }
            $pdo->prepare('INSERT INTO decisoes (texto, data, reuniao_id) VALUES (?,?,?)')
                ->execute([trim($_POST['texto']), $d, (int)($_POST['reuniao_id'] ?? 0) ?: null]);
            aviso('Decisão registrada.'); break;
        case 'decisao_apagar':
            $pdo->prepare('DELETE FROM decisoes WHERE id = ?')->execute([(int)$_POST['id']]); aviso('Decisão apagada.'); break;
        case 'reuniao_nova':
            $d = $data($_POST['data'] ?? '');
            if (trim((string)$_POST['titulo']) === '' || !$d) { aviso('Título e data são obrigatórios.', 'erro'); break; }
            $pdo->prepare('INSERT INTO reunioes (data, titulo, resumo) VALUES (?,?,?)')->execute([$d, trim($_POST['titulo']), trim((string)$_POST['resumo']) ?: null]);
            aviso('Reunião registrada.'); break;
        case 'reuniao_apagar':
            $pdo->prepare('DELETE FROM reunioes WHERE id = ?')->execute([(int)$_POST['id']]); aviso('Reunião apagada. Tarefas e decisões ligadas foram mantidas.'); break;
        case 'historico_novo':
            $d = $data($_POST['data'] ?? '');
            if (trim((string)$_POST['titulo']) === '' || !$d) { aviso('Título e data são obrigatórios.', 'erro'); break; }
            $pdo->prepare('INSERT INTO historico (data, titulo, descricao) VALUES (?,?,?)')->execute([$d, trim($_POST['titulo']), trim((string)$_POST['descricao']) ?: null]);
            aviso('Marco registrado.'); break;
        case 'historico_apagar':
            $pdo->prepare('DELETE FROM historico WHERE id = ?')->execute([(int)$_POST['id']]); aviso('Marco apagado.'); break;
        case 'pagina_salvar':
            $slug = strtolower(trim((string)$_POST['slug']));
            if (!preg_match('/^[a-z0-9-]{2,80}$/', $slug) || trim((string)$_POST['titulo']) === '' || trim((string)$_POST['conteudo']) === '') { aviso('Slug (a-z, 0-9, hífen), título e conteúdo são obrigatórios.', 'erro'); break; }
            $pdo->prepare('INSERT INTO paginas (slug, secao, titulo, conteudo, ordem) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE secao=VALUES(secao), titulo=VALUES(titulo), conteudo=VALUES(conteudo), ordem=VALUES(ordem)')
                ->execute([$slug, trim((string)$_POST['secao']) ?: 'Projeto', trim($_POST['titulo']), $_POST['conteudo'], (int)$_POST['ordem']]);
            aviso('Página salva.'); break;
        case 'pagina_apagar':
            $pdo->prepare('DELETE FROM paginas WHERE id = ?')->execute([(int)$_POST['id']]); aviso('Página apagada.'); break;
        case 'pessoa_nova':
            if (trim((string)$_POST['nome']) === '') { aviso('Nome obrigatório.', 'erro'); break; }
            $em = email_ou_null($_POST['email'] ?? '');
            if ($em === false) { aviso('E-mail inválido.', 'erro'); break; }
            $senhaIni = (string)($_POST['senha'] ?? '');
            if ($senhaIni !== '' && mb_strlen($senhaIni) < 4) { aviso('A senha precisa ter pelo menos 4 caracteres.', 'erro'); break; }
            try {
                $pdo->prepare('INSERT INTO pessoas (nome, email, senha_hash, senha_provisoria, papel, lado, token) VALUES (?,?,?,?,?,?,?)')
                    ->execute([trim($_POST['nome']), $em, ($em && $senhaIni !== '') ? password_hash($senhaIni, PASSWORD_DEFAULT) : null, ($em && $senhaIni !== '') ? 1 : 0, trim((string)$_POST['papel']), $_POST['lado'] === 'moura' ? 'moura' : 'cliente', bin2hex(random_bytes(20))]);
            } catch (PDOException $ex) { aviso('Esse e-mail já está em uso por outra pessoa.', 'erro'); break; }
            aviso(!$em ? 'Pessoa criada. Falta o e-mail para ela poder entrar.' : ($senhaIni !== '' ? 'Pessoa criada. Ela já pode entrar com o e-mail e a senha que você definiu.' : 'Pessoa criada. Agora defina a senha.')); break;
        case 'pessoa_editar':
            $em = email_ou_null($_POST['email'] ?? '');
            if ($em === false) { aviso('E-mail inválido.', 'erro'); break; }
            try {
                $pdo->prepare('UPDATE pessoas SET nome=?, email=?, papel=? WHERE id=?')->execute([trim($_POST['nome']), $em, trim((string)$_POST['papel']), (int)$_POST['id']]);
            } catch (PDOException $ex) { aviso('Esse e-mail já está em uso por outra pessoa.', 'erro'); break; }
            aviso('Pessoa atualizada.'); break;
        case 'pessoa_senha_def':
            $id = (int)$_POST['id']; $s = (string)($_POST['senha'] ?? '');
            $q = $pdo->prepare('SELECT nome, email FROM pessoas WHERE id = ?'); $q->execute([$id]); $alvo = $q->fetch();
            if (!$alvo || empty($alvo['email'])) { aviso('Cadastre o e-mail da pessoa antes de definir a senha.', 'erro'); break; }
            if (mb_strlen($s) < 4) { aviso('A senha precisa ter pelo menos 4 caracteres.', 'erro'); break; }
            $pdo->prepare('UPDATE pessoas SET senha_hash=?, senha_provisoria=?, falhas=0, bloqueado_ate=NULL WHERE id=?')->execute([password_hash($s, PASSWORD_DEFAULT), ($id === (int)$eu['id']) ? 0 : 1, $id]);
            $atualHash = hash('sha256', (string)($_COOKIE[cookie_nome('s')] ?? ''));
            $pdo->prepare('DELETE FROM sessoes WHERE pessoa_id = ? AND token_hash <> ?')->execute([$id, $atualHash]);
            aviso(($id === (int)$eu['id']) ? ('Senha de ' . $alvo['nome'] . ' definida.') : ('Senha inicial de ' . $alvo['nome'] . ' definida. No primeiro acesso, ela escolhe a própria.')); break;
        case 'pessoa_senha':
            $id = (int)$_POST['id'];
            $q = $pdo->prepare('SELECT nome, email FROM pessoas WHERE id = ?'); $q->execute([$id]); $alvo = $q->fetch();
            if (!$alvo || empty($alvo['email'])) { aviso('Cadastre o e-mail da pessoa antes de gerar a senha.', 'erro'); break; }
            $alf = 'abcdefghjkmnpqrstuvwxyz23456789'; $nova = '';
            for ($k = 0; $k < 8; $k++) $nova .= $alf[random_int(0, strlen($alf) - 1)];
            $pdo->prepare('UPDATE pessoas SET senha_hash=?, senha_provisoria=1, falhas=0, bloqueado_ate=NULL WHERE id=?')->execute([password_hash($nova, PASSWORD_DEFAULT), $id]);
            $pdo->prepare('DELETE FROM sessoes WHERE pessoa_id = ?')->execute([$id]);
            $host = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $_SESSION['zap'] = ['nome' => $alvo['nome'], 'texto' =>
                'Oi, ' . primeiro_nome($alvo['nome']) . '! Seu acesso ao painel do projeto:' . "\n\n"
                . 'Endereço: ' . $host . url('login.php') . "\n"
                . 'E-mail: ' . $alvo['email'] . "\n"
                . 'Senha provisória: ' . $nova . "\n\n"
                . 'Na primeira vez, o painel pede para você criar a sua própria senha. Qualquer dúvida, me chama.'];
            aviso('Senha provisória gerada para ' . $alvo['nome'] . '. Copie a mensagem abaixo e envie por WhatsApp.'); break;
        case 'pessoa_novo_link':
            $pdo->prepare('UPDATE pessoas SET token=? WHERE id=?')->execute([bin2hex(random_bytes(20)), (int)$_POST['id']]);
            aviso('Novo link gerado. O link antigo deixou de funcionar.'); break;
        case 'pessoa_ativo':
            $id = (int)$_POST['id'];
            if ($id === (int)$eu['id']) { aviso('Você não pode desativar o próprio acesso.', 'erro'); break; }
            $pdo->prepare('UPDATE pessoas SET ativo = 1 - ativo WHERE id = ?')->execute([$id]); aviso('Acesso atualizado.'); break;
        case 'obs_nova':
            $pid = (int)($_POST['pessoa_id'] ?? 0); $tid = (int)($_POST['tarefa_id'] ?? 0); $txt = trim((string)($_POST['texto'] ?? ''));
            $dest = url('admin/?v=engajamento&p=' . $pid);
            $q = $pdo->prepare("SELECT 1 FROM pessoas WHERE id = ? AND lado = 'cliente' AND is_admin = 0"); $q->execute([$pid]);
            if (!$q->fetchColumn()) { aviso('Pessoa inválida.', 'erro'); voltar(url('admin/?v=engajamento')); }
            if ($txt === '') { aviso('Escreva o que você observou.', 'erro'); break; }
            if (mb_strlen($txt) > 2000) { aviso('Observação longa demais (máximo 2000 caracteres).', 'erro'); break; }
            $titulo = null;
            if ($tid) {
                $q = $pdo->prepare('SELECT titulo FROM tarefas WHERE id = ?'); $q->execute([$tid]);
                $titulo = $q->fetchColumn();
                if ($titulo === false) { aviso('Tarefa não encontrada.', 'erro'); break; }
            }
            $pdo->prepare('INSERT INTO observacoes (pessoa_id, tarefa_id, tarefa_titulo, texto) VALUES (?,?,?,?)')
                ->execute([$pid, $tid ?: null, $titulo, $txt]);
            aviso('Observação registrada.'); break;
        case 'obs_apagar':
            $dest = url('admin/?v=engajamento&p=' . (int)($_POST['pessoa_id'] ?? 0));
            $pdo->prepare('DELETE FROM observacoes WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            aviso('Observação apagada.'); break;
        case 'ajuste_salvar':
            $dest = url('admin/?v=engajamento');
            try { $pdo->query('SELECT 1 FROM ajustes LIMIT 1'); } catch (Throwable $ex) { aviso('A tabela dos limites ainda não existe. Rode migrations/008_ajustes.sql no phpMyAdmin.', 'erro'); break; }
            $vals = [];
            foreach (['nao_abriu', 'sem_acao', 'sem_acesso'] as $k) {
                $n = (int)($_POST['lim_' . $k] ?? 0);
                if ($n < 1 || $n > 90) { aviso('Os limites precisam ficar entre 1 e 90 dias.', 'erro'); break 2; }
                $vals[$k] = $n;
            }
            foreach ($vals as $k => $n) $pdo->prepare('INSERT INTO ajustes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)')->execute(['eng_' . $k, (string)$n]);
            aviso('Limites salvos.'); break;
        case 'arquivo_enviar':
            require_once __DIR__ . '/../inc/arquivos.php';
            $erro = arquivo_salvar($_FILES['arquivo'] ?? [], trim((string)($_POST['categoria'] ?? '')), trim((string)($_POST['titulo'] ?? '')), trim((string)($_POST['descricao'] ?? '')), (int)$eu['id']);
            aviso($erro ?? 'Arquivo enviado. O cliente já pode baixar.', $erro ? 'erro' : 'ok'); break;
        case 'arquivo_apagar':
            require_once __DIR__ . '/../inc/arquivos.php';
            arquivo_apagar((int)($_POST['id'] ?? 0)); aviso('Arquivo apagado.'); break;
        case 'desempacar_novo':
            $d = $data($_POST['data'] ?? ''); $desc = trim((string)($_POST['descricao'] ?? '')); $tipo = trim((string)($_POST['tipo'] ?? ''));
            if (!$d || $desc === '' || $tipo === '') { aviso('Data, tipo do problema e o que foi feito são obrigatórios.', 'erro'); break; }
            $pdo->prepare('INSERT INTO acoes_desempacar (data, tipo_problema, descricao) VALUES (?,?,?)')->execute([$d, mb_substr($tipo, 0, 80), $desc]);
            aviso('Ação registrada. Marque o resultado quando souber.'); break;
        case 'desempacar_resultado':
            $r = (string)($_POST['resultado'] ?? '');
            if (!in_array($r, ['sim', 'nao', 'em_andamento'], true)) break;
            $pdo->prepare('UPDATE acoes_desempacar SET resultado = ?, avaliado_em = ?, nota = ? WHERE id = ?')
                ->execute([$r, $r === 'em_andamento' ? null : date('Y-m-d'), trim((string)($_POST['nota'] ?? '')) ?: null, (int)($_POST['id'] ?? 0)]);
            aviso('Resultado salvo.'); break;
        case 'desempacar_apagar':
            $pdo->prepare('DELETE FROM acoes_desempacar WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]); aviso('Ação apagada.'); break;
        case 'importar':
            $json = json_decode((string)$_POST['json'], true);
            if (!is_array($json)) { $_SESSION['imp'] = ['ok' => false, 'erros' => ['JSON inválido: ' . json_last_error_msg()], 'texto' => $_POST['json']]; voltar($dest); }
            require_once __DIR__ . '/../inc/importar.php';
            $r = importar_dados($json, (int)$eu['id'], isset($_POST['validar']));
            $r['texto'] = isset($_POST['validar']) || !$r['ok'] ? $_POST['json'] : '';
            $_SESSION['imp'] = $r; voltar($dest);
        default:
            aviso('Ação desconhecida.', 'erro');
    }
    voltar($dest);
}
