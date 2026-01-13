<?php
// api.php - Versão Definitiva (Timer Robusto + Financeiro + Kanban)
require 'config/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['sucesso' => false, 'msg' => 'Acesso negado.']); exit;
}

$uid = $_SESSION['usuario_id'];

// LER DADOS (Suporta JSON e FormData)
$json_data = json_decode(file_get_contents('php://input'), true);
$input = $json_data ? $json_data : $_POST;
$acao = $input['acao'] ?? '';

try {
    switch ($acao) {
        
        // ============================================================
        // 1. MÓDULO TIMER (Sua lógica robusta mantida)
        // ============================================================
        
        case 'iniciar':
            // 1. Pausa anteriores
            $pdo->prepare("UPDATE tempo_logs SET fim = NOW() WHERE fim IS NULL AND tarefa_id IN (SELECT id FROM tarefas WHERE usuario_id = :uid)")->execute([':uid' => $uid]);
            $pdo->prepare("UPDATE tarefas SET status = 'pausado' WHERE status = 'em_andamento' AND usuario_id = :uid")->execute([':uid' => $uid]);

            // 2. Cria nova
            $stmt = $pdo->prepare("INSERT INTO tarefas (usuario_id, cliente_id, projeto_id, descricao, status, data_criacao) VALUES (:uid, :cid, :pid, :desc, 'em_andamento', NOW())");
            $stmt->execute([
                ':uid' => $uid, 
                ':cid' => $input['cliente_id'], 
                ':pid' => !empty($input['projeto_id']) ? $input['projeto_id'] : NULL, 
                ':desc' => $input['descricao']
            ]);
            $tid = $pdo->lastInsertId();

            // 3. Vincula Tags (Se houver)
            if (!empty($input['tags']) && is_array($input['tags'])) {
                $stmtTag = $pdo->prepare("INSERT INTO tarefa_tags (tarefa_id, tag_id) VALUES (:tid, :tagid)");
                foreach ($input['tags'] as $tagId) $stmtTag->execute([':tid' => $tid, ':tagid' => $tagId]);
            }

            // 4. Inicia Log
            $pdo->prepare("INSERT INTO tempo_logs (tarefa_id, inicio) VALUES (:tid, NOW())")->execute([':tid' => $tid]);
            
            echo json_encode(['sucesso' => true]); 
            break;

        case 'quick_start': // Usado pelo Header
            $proj_id = filter_var($input['projeto_id'], FILTER_VALIDATE_INT);
            $desc    = filter_var($input['descricao'], FILTER_SANITIZE_SPECIAL_CHARS);

            if ($proj_id && $desc) {
                $pdo->beginTransaction();
                // Descobre cliente
                $stmtCli = $pdo->prepare("SELECT cliente_id FROM projetos WHERE id = :pid");
                $stmtCli->execute([':pid' => $proj_id]);
                $cliente_id = $stmtCli->fetchColumn();

                // Pausa anteriores e cria nova (mesma lógica do iniciar)
                $pdo->prepare("UPDATE tempo_logs SET fim = NOW() WHERE fim IS NULL AND tarefa_id IN (SELECT id FROM tarefas WHERE usuario_id = :uid)")->execute([':uid' => $uid]);
                $pdo->prepare("UPDATE tarefas SET status = 'pausado' WHERE status = 'em_andamento' AND usuario_id = :uid")->execute([':uid' => $uid]);

                $stmt = $pdo->prepare("INSERT INTO tarefas (projeto_id, cliente_id, usuario_id, descricao, status, data_criacao) VALUES (:pid, :cid, :uid, :desc, 'em_andamento', NOW())");
                $stmt->execute([':pid' => $proj_id, ':cid' => $cliente_id, ':uid' => $uid, ':desc' => $desc]);
                $tid = $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO tempo_logs (tarefa_id, inicio) VALUES (:tid, NOW())")->execute([':tid' => $tid]);
                $pdo->commit();
                echo json_encode(['sucesso' => true]);
            }
            break;

        case 'pausar':
            $id = filter_var($input['tarefa_id'], FILTER_VALIDATE_INT);
            if($id) {
                $pdo->prepare("UPDATE tempo_logs SET fim = NOW() WHERE tarefa_id = :tid AND fim IS NULL")->execute([':tid' => $id]);
                $pdo->prepare("UPDATE tarefas SET status = 'pausado' WHERE id = :id AND usuario_id = :uid")->execute([':id' => $id, ':uid' => $uid]);
                echo json_encode(['sucesso' => true]);
            }
            break;

        case 'retomar':
            $id = filter_var($input['tarefa_id'], FILTER_VALIDATE_INT);
            if($id) {
                // Pausa outros
                $pdo->prepare("UPDATE tempo_logs SET fim = NOW() WHERE fim IS NULL AND tarefa_id IN (SELECT id FROM tarefas WHERE usuario_id = :uid)")->execute([':uid' => $uid]);
                $pdo->prepare("UPDATE tarefas SET status = 'pausado' WHERE status = 'em_andamento' AND usuario_id = :uid")->execute([':uid' => $uid]);
                
                // Retoma este
                $pdo->prepare("INSERT INTO tempo_logs (tarefa_id, inicio) VALUES (:tid, NOW())")->execute([':tid' => $id]);
                $pdo->prepare("UPDATE tarefas SET status = 'em_andamento' WHERE id = :id AND usuario_id = :uid")->execute([':id' => $id, ':uid' => $uid]);
                echo json_encode(['sucesso' => true]);
            }
            break;

        case 'finalizar':
            $id = filter_var($input['tarefa_id'], FILTER_VALIDATE_INT);
            if($id) {
                $pdo->prepare("UPDATE tempo_logs SET fim = NOW() WHERE tarefa_id = :tid AND fim IS NULL")->execute([':tid' => $id]);
                $pdo->prepare("UPDATE tarefas SET status = 'concluido', data_finalizacao = NOW() WHERE id = :id AND usuario_id = :uid")->execute([':id' => $id, ':uid' => $uid]);
                echo json_encode(['sucesso' => true]);
            }
            break;

        case 'verificar_status':
            $sql = "SELECT t.id, t.descricao, t.status, c.nome as nome_cliente, p.nome as nome_projeto 
                    FROM tarefas t 
                    JOIN clientes c ON t.cliente_id = c.id 
                    LEFT JOIN projetos p ON t.projeto_id = p.id 
                    WHERE t.usuario_id = :uid AND t.status IN ('em_andamento', 'pausado') 
                    ORDER BY t.data_criacao DESC LIMIT 1";
            $stmt = $pdo->prepare($sql); $stmt->execute([':uid' => $uid]);
            $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tarefa) {
                $stmtLog = $pdo->prepare("SELECT SUM(TIMESTAMPDIFF(SECOND, inicio, IFNULL(fim, NOW()))) FROM tempo_logs WHERE tarefa_id = :tid");
                $stmtLog->execute([':tid' => $tarefa['id']]);
                echo json_encode(['ativo' => true, 'tarefa' => $tarefa, 'segundos_totais' => (int)$stmtLog->fetchColumn()]);
            } else {
                echo json_encode(['ativo' => false]);
            }
            break;

        // ============================================================
        // 2. MÓDULO FINANCEIRO (CRÍTICO PARA RELATÓRIOS)
        // ============================================================
        
        case 'atualizar_status_pagamento':
            $id = filter_var($input['id'], FILTER_VALIDATE_INT);
            $status = $input['status'];
            if ($id && in_array($status, ['pendente', 'faturado', 'pago'])) {
                $pdo->prepare("UPDATE tarefas SET status_pagamento = :st WHERE id = :id AND usuario_id = :uid")
                    ->execute([':st' => $status, ':id' => $id, ':uid' => $uid]);
                echo json_encode(['sucesso' => true]);
            } else {
                echo json_encode(['sucesso' => false, 'msg' => 'Status inválido']);
            }
            break;

        // ============================================================
        // 3. MÓDULO KANBAN & CHECKLIST
        // ============================================================

        case 'get_checklist':
            $pid = filter_var($input['projeto_id'], FILTER_VALIDATE_INT);
            $itens = $pdo->query("SELECT * FROM checklist_projetos WHERE projeto_id = $pid AND (status != 'done' AND concluido = 0)")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['sucesso' => true, 'itens' => $itens]);
            break;

        case 'mover_kanban':
            $id = $input['id'];
            $status = $input['status']; // todo, doing, done
            $pdo->prepare("UPDATE checklist_projetos SET status = :st WHERE id = :id")->execute([':st' => $status, ':id' => $id]);
            echo json_encode(['sucesso' => true]);
            break;

        case 'add_checklist':
            $pid = $input['projeto_id'];
            $desc = $input['descricao'];
            if ($pid && $desc) {
                $pdo->prepare("INSERT INTO checklist_projetos (projeto_id, descricao, status) VALUES (:pid, :desc, 'todo')")->execute([':pid' => $pid, ':desc' => $desc]);
                echo json_encode(['sucesso' => true]);
            }
            break;

        case 'toggle_checklist':
            $id = $input['id'];
            $val = $input['concluido'] ? 1 : 0;
            $pdo->prepare("UPDATE checklist_projetos SET concluido = :c, status = :s WHERE id = :id")
                ->execute([':c' => $val, ':s' => ($val ? 'done' : 'todo'), ':id' => $id]);
            echo json_encode(['sucesso' => true]);
            break;

        case 'delete_checklist':
            $id = $input['id'];
            $pdo->prepare("DELETE FROM checklist_projetos WHERE id = :id")->execute([':id' => $id]);
            echo json_encode(['sucesso' => true]);
            break;

        // ============================================================
        // 4. MÓDULO ARQUIVOS E OUTROS
        // ============================================================

        case 'upload_arquivo':
            $pid = filter_var($_POST['projeto_id'] ?? 0, FILTER_VALIDATE_INT);
            if ($pid && isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['arquivo'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $permitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'txt'];
                
                if (in_array($ext, $permitidos)) {
                    $dir = 'assets/uploads/projetos/' . date('Y/m') . '/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    
                    $novoNome = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $file['name']);
                    if (move_uploaded_file($file['tmp_name'], $dir . $novoNome)) {
                        $pdo->prepare("INSERT INTO projeto_arquivos (projeto_id, nome_original, caminho, tamanho) VALUES (?, ?, ?, ?)")
                            ->execute([$pid, $file['name'], $dir.$novoNome, $file['size']]);
                        echo json_encode(['sucesso' => true]);
                    }
                }
            }
            break;

        case 'delete_arquivo':
            $id = $input['id'];
            $stmt = $pdo->prepare("SELECT caminho FROM projeto_arquivos WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $caminho = $stmt->fetchColumn();
            if ($caminho && file_exists($caminho)) unlink($caminho);
            $pdo->prepare("DELETE FROM projeto_arquivos WHERE id = :id")->execute([':id' => $id]);
            echo json_encode(['sucesso' => true]);
            break;

        default:
            echo json_encode(['sucesso' => false, 'msg' => 'Ação inválida ou não encontrada: ' . $acao]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'msg' => 'Erro no servidor: ' . $e->getMessage()]);
}
?>