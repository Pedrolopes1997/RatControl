<?php
require 'config/db.php';
require 'includes/header.php';

$mensagem = '';

// --- 1. EXPORTAR (DOWNLOAD JSON) ---
if (isset($_GET['exportar'])) {
    $dados = [];
    $uid = $_SESSION['usuario_id'];

    // Busca dados de todas as tabelas relevantes
    // (Filtramos por usuario_id onde aplicável, mas clientes/projetos assumimos globais ou filtramos se tiver coluna usuario_id)
    
    $dados['clientes'] = $pdo->query("SELECT * FROM clientes")->fetchAll(PDO::FETCH_ASSOC);
    $dados['projetos'] = $pdo->query("SELECT * FROM projetos")->fetchAll(PDO::FETCH_ASSOC);
    $dados['tags'] = $pdo->prepare("SELECT * FROM tags WHERE usuario_id = ?"); 
    $dados['tags']->execute([$uid]); $dados['tags'] = $dados['tags']->fetchAll(PDO::FETCH_ASSOC);
    
    $dados['tarefas'] = $pdo->prepare("SELECT * FROM tarefas WHERE usuario_id = ?");
    $dados['tarefas']->execute([$uid]); $dados['tarefas'] = $dados['tarefas']->fetchAll(PDO::FETCH_ASSOC);
    
    // Para logs e tags de tarefas, precisamos filtrar pelas tarefas deste usuário
    $dados['tempo_logs'] = $pdo->prepare("SELECT tl.* FROM tempo_logs tl JOIN tarefas t ON tl.tarefa_id = t.id WHERE t.usuario_id = ?");
    $dados['tempo_logs']->execute([$uid]); $dados['tempo_logs'] = $dados['tempo_logs']->fetchAll(PDO::FETCH_ASSOC);
    
    $dados['tarefa_tags'] = $pdo->prepare("SELECT tt.* FROM tarefa_tags tt JOIN tarefas t ON tt.tarefa_id = t.id WHERE t.usuario_id = ?");
    $dados['tarefa_tags']->execute([$uid]); $dados['tarefa_tags'] = $dados['tarefa_tags']->fetchAll(PDO::FETCH_ASSOC);

    // Força download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="RatControl_Backup_'.date('Y-m-d').'.json"');
    echo json_encode($dados, JSON_PRETTY_PRINT);
    exit;
}

// --- 2. IMPORTAR (RESTORE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo_json'])) {
    $arquivo = $_FILES['arquivo_json']['tmp_name'];
    $conteudo = file_get_contents($arquivo);
    $dados = json_decode($conteudo, true);

    if ($dados) {
        try {
            $pdo->beginTransaction();
            
            // ATENÇÃO: Estratégia "Limpar e Restaurar" para evitar duplicidade de IDs.
            // Isso apagará dados atuais do usuário.
            $uid = $_SESSION['usuario_id'];

            // Ordem de exclusão (filhos primeiro)
            $pdo->prepare("DELETE FROM tempo_logs WHERE tarefa_id IN (SELECT id FROM tarefas WHERE usuario_id = ?)")->execute([$uid]);
            $pdo->prepare("DELETE FROM tarefa_tags WHERE tarefa_id IN (SELECT id FROM tarefas WHERE usuario_id = ?)")->execute([$uid]);
            $pdo->prepare("DELETE FROM tarefas WHERE usuario_id = ?")->execute([$uid]);
            // Clientes e Projetos podem ser compartilhados, então usamos INSERT IGNORE ou ON DUPLICATE UPDATE
            // Para simplificar, vamos reinserir Clientes ignorando IDs existentes
            
            // 1. Restaurar Clientes
            $stmt = $pdo->prepare("INSERT IGNORE INTO clientes (id, nome, status, valor_hora, moeda) VALUES (:id, :nome, :status, :val, :moeda)");
            foreach ($dados['clientes'] as $row) $stmt->execute($row);

            // 2. Restaurar Projetos
            $stmt = $pdo->prepare("INSERT IGNORE INTO projetos (id, cliente_id, nome, status, horas_estimadas) VALUES (:id, :cid, :nome, :st, :he)");
            foreach ($dados['projetos'] as $row) $stmt->execute([
                ':id'=>$row['id'], ':cid'=>$row['cliente_id'], ':nome'=>$row['nome'], ':st'=>$row['status'], ':he'=>$row['horas_estimadas']
            ]);

            // 3. Restaurar Tags
            $pdo->prepare("DELETE FROM tags WHERE usuario_id = ?")->execute([$uid]); // Limpa tags antigas
            $stmt = $pdo->prepare("INSERT INTO tags (id, nome, cor, usuario_id) VALUES (:id, :nome, :cor, :uid)");
            foreach ($dados['tags'] as $row) $stmt->execute($row);

            // 4. Restaurar Tarefas
            $stmt = $pdo->prepare("INSERT INTO tarefas (id, usuario_id, cliente_id, projeto_id, descricao, status, data_criacao, data_finalizacao, status_pagamento) VALUES (:id, :uid, :cid, :pid, :desc, :st, :dc, :df, :sp)");
            foreach ($dados['tarefas'] as $row) $stmt->execute($row);

            // 5. Restaurar Logs
            $stmt = $pdo->prepare("INSERT INTO tempo_logs (id, tarefa_id, inicio, fim) VALUES (:id, :tid, :ini, :fim)");
            foreach ($dados['tempo_logs'] as $row) $stmt->execute($row);

            // 6. Restaurar Vínculos Tags
            $stmt = $pdo->prepare("INSERT INTO tarefa_tags (tarefa_id, tag_id) VALUES (:tid, :tagid)");
            foreach ($dados['tarefa_tags'] as $row) $stmt->execute($row);

            $pdo->commit();
            $mensagem = '<div class="alert alert-success">Backup restaurado com sucesso!</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = '<div class="alert alert-danger">Erro ao restaurar: '.$e->getMessage().'</div>';
        }
    } else {
        $mensagem = '<div class="alert alert-warning">Arquivo inválido.</div>';
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white"><i class="fas fa-download"></i> Exportar Dados</div>
            <div class="card-body text-center">
                <p>Baixe um arquivo JSON com todo o histórico de tarefas, clientes e configurações.</p>
                <a href="?exportar=true" class="btn btn-outline-primary w-100">Baixar Backup (.json)</a>
            </div>
        </div>

        <div class="card shadow border-danger">
            <div class="card-header bg-danger text-white"><i class="fas fa-upload"></i> Restaurar Backup</div>
            <div class="card-body">
                <?php echo $mensagem; ?>
                <p class="text-danger small"><strong>Atenção:</strong> Restaurar um backup irá substituir os dados atuais.</p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label>Arquivo de Backup (.json)</label>
                        <input type="file" name="arquivo_json" class="form-control" accept=".json" required>
                    </div>
                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Tem certeza? Isso pode apagar dados recentes.');">Restaurar Agora</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>