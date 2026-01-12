<?php
// api.php - Versão Corrigida para Uploads
require 'config/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['sucesso' => false, 'msg' => 'Não autorizado']); exit;
}

$uid = $_SESSION['usuario_id'];

// --- CORREÇÃO AQUI: LER JSON OU POST (UPLOAD) ---
$json_data = json_decode(file_get_contents('php://input'), true);

if ($json_data) {
    // Se veio JSON (Timer, Kanban, Checklist)
    $input = $json_data;
} else {
    // Se veio FormData (Upload de Arquivo)
    $input = $_POST;
}

$acao = $input['acao'] ?? '';

try {
    switch ($acao) {
        
        // --- TIMER: INICIAR ---
        case 'iniciar':
            $pdo->prepare("UPDATE tempo_logs SET fim = NOW() WHERE fim IS NULL AND tarefa_id IN (SELECT id FROM tarefas WHERE usuario_id = :uid)")->execute([':uid' => $uid]);
            $pdo->prepare("UPDATE tarefas SET status = 'pausado' WHERE status = 'em_andamento' AND usuario_id = :uid")->execute([':uid' => $uid]);

            $stmt = $pdo->prepare("INSERT INTO tarefas (usuario_id, cliente_id, projeto_id, descricao, status) VALUES (:uid, :cid, :pid, :desc, 'em_andamento')");
            $stmt->execute([':uid' => $uid, ':cid' => $input['cliente_id'], ':pid' => !empty($input['projeto_id'])?$input['projeto_id']:NULL, ':desc' => $input['descricao']]);
            $tid = $pdo->lastInsertId();

            if (!empty($input['tags'])) {
                $stmtTag = $pdo->prepare("INSERT INTO tarefa_tags (tarefa_id, tag_id) VALUES (:tid, :tagid)");
                foreach ($input['tags'] as $tagId) $stmtTag->execute([':tid' => $tid, ':tagid' => $tagId]);
            }
            $pdo->prepare("INSERT INTO tempo_logs (tarefa_id, inicio) VALUES (:tid, NOW())")->execute([':tid' => $tid]);
            echo json_encode(['sucesso' => true]);
            break;

        // --- TIMER: PAUSAR ---
        case 'pausar':
            $id = $input['tarefa_id'];
            $pdo->prepare("UPDATE tempo_logs SET fim = NOW() WHERE tarefa_id = :tid AND fim IS NULL")->execute([':tid' => $id]);
            $pdo->prepare("UPDATE tarefas SET status = 'pausado' WHERE id = :id AND usuario_id = :uid")->execute([':id' => $id, ':uid' => $uid]);
            echo json_encode(['sucesso' => true]);
            break;

        // --- TIMER: RETOMAR ---
        case 'retomar':
            $id = $input['tarefa_id'];
            $pdo->prepare("UPDATE tempo_logs SET fim = NOW() WHERE fim IS NULL AND tarefa_id IN (SELECT id FROM tarefas WHERE usuario_id = :uid)")->execute([':uid' => $uid]);
            $pdo->prepare("UPDATE tarefas SET status = 'pausado' WHERE status = 'em_andamento' AND usuario_id = :uid")->execute([':uid' => $uid]);
            $pdo->prepare("INSERT INTO tempo_logs (tarefa_id, inicio) VALUES (:tid, NOW())")->execute([':tid' => $id]);
            $pdo->prepare("UPDATE tarefas SET status = 'em_andamento' WHERE id = :id AND usuario_id = :uid")->execute([':id' => $id, ':uid' => $uid]);
            echo json_encode(['sucesso' => true]);
            break;

        // --- TIMER: FINALIZAR ---
        case 'finalizar':
            $id = $input['tarefa_id'];
            $pdo->prepare("UPDATE tempo_logs SET fim = NOW() WHERE tarefa_id = :tid AND fim IS NULL")->execute([':tid' => $id]);
            $pdo->prepare("UPDATE tarefas SET status = 'concluido' WHERE id = :id AND usuario_id = :uid")->execute([':id' => $id, ':uid' => $uid]);
            echo json_encode(['sucesso' => true]);
            break;

        // --- STATUS DO TIMER (POLLING) ---
        case 'verificar_status':
            $sql = "SELECT t.id, t.descricao, t.status, c.nome as nome_cliente, p.nome as nome_projeto FROM tarefas t JOIN tempo_logs tl ON t.id = tl.tarefa_id JOIN clientes c ON t.cliente_id = c.id LEFT JOIN projetos p ON t.projeto_id = p.id WHERE t.usuario_id = :uid AND tl.fim IS NULL LIMIT 1";
            $tarefa = $pdo->prepare($sql); $tarefa->execute([':uid' => $uid]); $tarefa = $tarefa->fetch(PDO::FETCH_ASSOC);

            if ($tarefa) {
                $segundos = $pdo->prepare("SELECT SUM(TIMESTAMPDIFF(SECOND, inicio, IFNULL(fim, NOW()))) FROM tempo_logs WHERE tarefa_id = ?");
                $segundos->execute([$tarefa['id']]);
                echo json_encode(['ativo' => true, 'tarefa' => $tarefa, 'segundos_totais' => $segundos->fetchColumn()]);
            } else {
                echo json_encode(['ativo' => false]);
            }
            break;

        // --- FINANCEIRO: ATUALIZAR STATUS ---
        case 'atualizar_status_pagamento':
            $id = filter_var($input['id'], FILTER_VALIDATE_INT);
            $st = $input['status'];
            if ($id && in_array($st, ['pendente', 'faturado', 'pago'])) {
                $pdo->prepare("UPDATE tarefas SET status_pagamento = :st WHERE id = :id AND usuario_id = :uid")->execute([':st' => $st, ':id' => $id, ':uid' => $uid]);
                echo json_encode(['sucesso' => true]);
            } else echo json_encode(['sucesso' => false]);
            break;

        // --- CHECKLIST & KANBAN: ADICIONAR ---
        case 'add_checklist':
            $pid = filter_var($input['projeto_id'], FILTER_VALIDATE_INT);
            $desc = filter_var($input['descricao'], FILTER_SANITIZE_SPECIAL_CHARS);
            if ($pid && $desc) {
                $pdo->prepare("INSERT INTO checklist_projetos (projeto_id, descricao, status, concluido) VALUES (:pid, :desc, 'todo', 0)")->execute([':pid' => $pid, ':desc' => $desc]);
                echo json_encode(['sucesso' => true]);
            } else echo json_encode(['sucesso' => false]);
            break;

        // --- CHECKLIST: TOGGLE (MARCAR/DESMARCAR) ---
        case 'toggle_checklist':
            $id = filter_var($input['id'], FILTER_VALIDATE_INT);
            $check = $input['concluido'] ? 1 : 0;
            $status = $input['concluido'] ? 'done' : 'todo';
            $pdo->prepare("UPDATE checklist_projetos SET concluido = :c, status = :st WHERE id = :id")->execute([':c' => $check, ':st' => $status, ':id' => $id]);
            echo json_encode(['sucesso' => true]);
            break;

        // --- CHECKLIST: EXCLUIR ---
        case 'delete_checklist':
            $id = filter_var($input['id'], FILTER_VALIDATE_INT);
            $pdo->prepare("DELETE FROM checklist_projetos WHERE id = :id")->execute([':id' => $id]);
            echo json_encode(['sucesso' => true]);
            break;
            
        // --- CHECKLIST: BUSCAR (Para Timer) ---
        case 'get_checklist':
            $pid = filter_var($input['projeto_id'], FILTER_VALIDATE_INT);
            $stmt = $pdo->prepare("SELECT * FROM checklist_projetos WHERE projeto_id = :pid AND status != 'done' ORDER BY id DESC");
            $stmt->execute([':pid' => $pid]);
            echo json_encode(['sucesso' => true, 'itens' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // --- KANBAN: MOVER ---
        case 'mover_kanban':
            $id = filter_var($input['id'], FILTER_VALIDATE_INT);
            $st = $input['status'];
            if ($id && in_array($st, ['todo', 'doing', 'done'])) {
                $done = ($st === 'done') ? 1 : 0;
                $pdo->prepare("UPDATE checklist_projetos SET status = :st, concluido = :done WHERE id = :id")->execute([':st' => $st, ':done' => $done, ':id' => $id]);
                echo json_encode(['sucesso' => true]);
            } else echo json_encode(['sucesso' => false]);
            break;

        // --- ARQUIVOS: UPLOAD (CORRIGIDO) ---
        case 'upload_arquivo':
            // Aqui usamos $input (que vem de $_POST)
            $pid = filter_var($input['projeto_id'], FILTER_VALIDATE_INT);
            
            if ($pid && isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['arquivo'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $permitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'rar', 'txt'];
                
                if (in_array($ext, $permitidos)) {
                    $dir = 'assets/uploads/projetos/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    
                    $novoNome = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $file['name']);
                    $destino = $dir . $novoNome;
                    
                    if (move_uploaded_file($file['tmp_name'], $destino)) {
                        $pdo->prepare("INSERT INTO projeto_arquivos (projeto_id, nome_original, caminho, tamanho) VALUES (:pid, :nome, :caminho, :tam)")
                            ->execute([':pid'=>$pid, ':nome'=>$file['name'], ':caminho'=>$destino, ':tam'=>$file['size']]);
                        echo json_encode(['sucesso' => true]);
                    } else {
                        echo json_encode(['sucesso' => false, 'msg' => 'Erro ao mover arquivo']);
                    }
                } else {
                    echo json_encode(['sucesso' => false, 'msg' => 'Formato não permitido']);
                }
            } else {
                echo json_encode(['sucesso' => false, 'msg' => 'Nenhum arquivo enviado']);
            }
            break;

        // --- ARQUIVOS: EXCLUIR ---
        case 'delete_arquivo':
            $id = filter_var($input['id'], FILTER_VALIDATE_INT);
            $stmt = $pdo->prepare("SELECT caminho FROM projeto_arquivos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $arq = $stmt->fetch();
            if ($arq) {
                if (file_exists($arq['caminho'])) unlink($arq['caminho']);
                $pdo->prepare("DELETE FROM projeto_arquivos WHERE id = :id")->execute([':id' => $id]);
                echo json_encode(['sucesso' => true]);
            } else echo json_encode(['sucesso' => false]);
            break;

        // --- ARQUIVOS: VISIBILIDADE ---
        case 'toggle_visibilidade_arquivo':
            $id = filter_var($input['id'], FILTER_VALIDATE_INT);
            $visivel = $input['visivel'] ? 1 : 0;
            $pdo->prepare("UPDATE projeto_arquivos SET visivel_cliente = :v WHERE id = :id")->execute([':v' => $visivel, ':id' => $id]);
            echo json_encode(['sucesso' => true]);
            break;

        default: echo json_encode(['sucesso' => false, 'msg' => 'Ação desconhecida']);
    }
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'msg' => $e->getMessage()]);
}
?>