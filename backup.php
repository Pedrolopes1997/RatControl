<?php
// backup.php
require 'config/db.php';

// Inicia sessão manualmente pois o header.php ainda não foi carregado
if (session_status() === PHP_SESSION_NONE) session_start();

// Verifica segurança manualmente para o download
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$uid = $_SESSION['usuario_id'];
$mensagem = '';

// --- 1. EXPORTAR (DOWNLOAD JSON) ---
// ESTA LÓGICA AGORA FICA NO TOPO, ANTES DE QUALQUER HTML
if (isset($_GET['exportar'])) {
    $dados = [];

    // Globais (Clientes e Projetos)
    $dados['clientes'] = $pdo->query("SELECT * FROM clientes")->fetchAll(PDO::FETCH_ASSOC);
    $dados['projetos'] = $pdo->query("SELECT * FROM projetos")->fetchAll(PDO::FETCH_ASSOC);
    
    // Específicos do Usuário
    $stmt = $pdo->prepare("SELECT * FROM tags WHERE usuario_id = ?"); 
    $stmt->execute([$uid]); $dados['tags'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT * FROM tarefas WHERE usuario_id = ?"); 
    $stmt->execute([$uid]); $dados['tarefas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Orçamentos
    $stmt = $pdo->prepare("SELECT * FROM orcamentos WHERE usuario_id = ?");
    $stmt->execute([$uid]); $dados['orcamentos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Logs de Tempo
    $dados['tempo_logs'] = $pdo->prepare("SELECT tl.* FROM tempo_logs tl JOIN tarefas t ON tl.tarefa_id = t.id WHERE t.usuario_id = ?");
    $dados['tempo_logs']->execute([$uid]); $dados['tempo_logs'] = $dados['tempo_logs']->fetchAll(PDO::FETCH_ASSOC);

    // Tags de Tarefas
    $dados['tarefa_tags'] = $pdo->prepare("SELECT tt.* FROM tarefa_tags tt JOIN tarefas t ON tt.tarefa_id = t.id WHERE t.usuario_id = ?");
    $dados['tarefa_tags']->execute([$uid]); $dados['tarefa_tags'] = $dados['tarefa_tags']->fetchAll(PDO::FETCH_ASSOC);

    // Checklists e Metadados de Arquivos
    $dados['checklist_projetos'] = $pdo->query("SELECT * FROM checklist_projetos")->fetchAll(PDO::FETCH_ASSOC);
    $dados['projeto_arquivos'] = $pdo->query("SELECT * FROM projeto_arquivos")->fetchAll(PDO::FETCH_ASSOC);
    $dados['projeto_comentarios'] = $pdo->query("SELECT * FROM projeto_comentarios")->fetchAll(PDO::FETCH_ASSOC);

    // Limpa qualquer buffer de saída anterior para garantir que só o JSON seja baixado
    if (ob_get_length()) ob_clean();

    // Força download
    $filename = 'RatControl_Backup_' . date('Y-m-d_H-i') . '.json';
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Pragma: no-cache');
    
    echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit; // ENCERRA O SCRIPT AQUI PARA NÃO BAIXAR O HTML DO SITE JUNTO
}

// --- AGORA SIM CARREGAMOS O HTML DO SITE ---
require 'includes/header.php';

// --- 2. IMPORTAR (RESTORE) ---
// A lógica de POST pode ficar aqui embaixo pois ela devolve HTML (mensagem de sucesso)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo_json'])) {
    $arquivo = $_FILES['arquivo_json']['tmp_name'];
    
    // Verifica se o arquivo foi enviado corretamente
    if (file_exists($arquivo)) {
        $conteudo = file_get_contents($arquivo);
        $dados = json_decode($conteudo, true);

        if ($dados) {
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $pdo->beginTransaction();
                
                // Limpeza
                $pdo->prepare("DELETE FROM tempo_logs WHERE tarefa_id IN (SELECT id FROM tarefas WHERE usuario_id = ?)")->execute([$uid]);
                $pdo->prepare("DELETE FROM tarefa_tags WHERE tarefa_id IN (SELECT id FROM tarefas WHERE usuario_id = ?)")->execute([$uid]);
                $pdo->prepare("DELETE FROM tarefas WHERE usuario_id = ?")->execute([$uid]);
                $pdo->prepare("DELETE FROM tags WHERE usuario_id = ?")->execute([$uid]);
                $pdo->prepare("DELETE FROM orcamentos WHERE usuario_id = ?")->execute([$uid]);
                
                // Restauração (Mesma lógica anterior, mantida)
                if(!empty($dados['clientes'])) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO clientes (id, nome, email, telefone, documento, status, valor_hora, moeda, logo) VALUES (:id, :nome, :email, :telefone, :doc, :status, :val, :moeda, :logo)");
                    foreach ($dados['clientes'] as $row) {
                        $stmt->execute([':id'=>$row['id'], ':nome'=>$row['nome'], ':email'=>$row['email']??null, ':telefone'=>$row['telefone']??null, ':doc'=>$row['documento']??null, ':status'=>$row['status'], ':val'=>$row['valor_hora'], ':moeda'=>$row['moeda'], ':logo'=>$row['logo']??null]);
                    }
                }

                if(!empty($dados['projetos'])) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO projetos (id, cliente_id, nome, descricao, status, horas_estimadas, data_inicio, data_fim) VALUES (:id, :cid, :nome, :desc, :st, :he, :di, :df)");
                    foreach ($dados['projetos'] as $row) {
                        $stmt->execute([':id'=>$row['id'], ':cid'=>$row['cliente_id'], ':nome'=>$row['nome'], ':desc'=>$row['descricao']??'', ':st'=>$row['status'], ':he'=>$row['horas_estimadas'], ':di'=>$row['data_inicio']??null, ':df'=>$row['data_fim']??null]);
                    }
                }

                if(!empty($dados['orcamentos'])) {
                    $stmt = $pdo->prepare("INSERT INTO orcamentos (id, usuario_id, cliente_id, titulo, descricao, tipo_cobranca, valor_total, valor_hora, horas_estimadas, prazo_entrega, status, data_criacao) VALUES (:id, :uid, :cid, :tit, :desc, :tipo, :vt, :vh, :he, :pz, :st, :dc)");
                    foreach ($dados['orcamentos'] as $row) {
                        $stmt->execute([':id'=>$row['id'], ':uid'=>$uid, ':cid'=>$row['cliente_id'], ':tit'=>$row['titulo'], ':desc'=>$row['descricao'], ':tipo'=>$row['tipo_cobranca']??'fixo', ':vt'=>$row['valor_total'], ':vh'=>$row['valor_hora']??0, ':he'=>$row['horas_estimadas']??0, ':pz'=>$row['prazo_entrega']??null, ':st'=>$row['status'], ':dc'=>$row['data_criacao']]);
                    }
                }

                if(!empty($dados['tags'])) {
                    $stmt = $pdo->prepare("INSERT INTO tags (id, nome, cor, usuario_id) VALUES (:id, :nome, :cor, :uid)");
                    foreach ($dados['tags'] as $row) $stmt->execute([':id'=>$row['id'], ':nome'=>$row['nome'], ':cor'=>$row['cor'], ':uid'=>$uid]);
                }

                if(!empty($dados['tarefas'])) {
                    $stmt = $pdo->prepare("INSERT INTO tarefas (id, usuario_id, cliente_id, projeto_id, descricao, status, data_criacao, data_finalizacao, status_pagamento) VALUES (:id, :uid, :cid, :pid, :desc, :st, :dc, :df, :sp)");
                    foreach ($dados['tarefas'] as $row) $stmt->execute([':id'=>$row['id'], ':uid'=>$uid, ':cid'=>$row['cliente_id'], ':pid'=>$row['projeto_id'], ':desc'=>$row['descricao'], ':st'=>$row['status'], ':dc'=>$row['data_criacao'], ':df'=>$row['data_finalizacao'], ':sp'=>$row['status_pagamento']]);
                }

                if(!empty($dados['tempo_logs'])) {
                    $stmt = $pdo->prepare("INSERT INTO tempo_logs (id, tarefa_id, inicio, fim) VALUES (:id, :tid, :ini, :fim)");
                    foreach ($dados['tempo_logs'] as $row) $stmt->execute([':id'=>$row['id'], ':tid'=>$row['tarefa_id'], ':ini'=>$row['inicio'], ':fim'=>$row['fim']]);
                }

                if(!empty($dados['tarefa_tags'])) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO tarefa_tags (tarefa_id, tag_id) VALUES (:tid, :tagid)");
                    foreach ($dados['tarefa_tags'] as $row) $stmt->execute([':tid'=>$row['tarefa_id'], ':tagid'=>$row['tag_id']]);
                }
                
                if(!empty($dados['checklist_projetos'])) {
                    $pdo->exec("DELETE FROM checklist_projetos");
                    $stmt = $pdo->prepare("INSERT INTO checklist_projetos (id, projeto_id, descricao, status, concluido) VALUES (:id, :pid, :desc, :st, :c)");
                    foreach ($dados['checklist_projetos'] as $row) $stmt->execute([':id'=>$row['id'], ':pid'=>$row['projeto_id'], ':desc'=>$row['descricao'], ':st'=>$row['status'], ':c'=>$row['concluido']]);
                }
                
                if(!empty($dados['projeto_arquivos'])) {
                    $pdo->exec("DELETE FROM projeto_arquivos");
                    $stmt = $pdo->prepare("INSERT INTO projeto_arquivos (id, projeto_id, nome_original, caminho, tamanho, visivel_cliente, data_upload) VALUES (:id, :pid, :nome, :cam, :tam, :vis, :dt)");
                    foreach ($dados['projeto_arquivos'] as $row) $stmt->execute([':id'=>$row['id'], ':pid'=>$row['projeto_id'], ':nome'=>$row['nome_original'], ':cam'=>$row['caminho'], ':tam'=>$row['tamanho'], ':vis'=>$row['visivel_cliente']??0, ':dt'=>$row['data_upload']??null]);
                }

                $pdo->commit();
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $mensagem = '<div class="alert alert-success mt-3"><i class="fas fa-check-circle"></i> Backup restaurado com sucesso!</div>';
            } catch (Exception $e) {
                $pdo->rollBack();
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $mensagem = '<div class="alert alert-danger mt-3">Erro ao restaurar: '.$e->getMessage().'</div>';
            }
        } else {
            $mensagem = '<div class="alert alert-warning mt-3">O arquivo JSON está vazio ou inválido.</div>';
        }
    } else {
        $mensagem = '<div class="alert alert-warning mt-3">Erro no upload do arquivo.</div>';
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-database me-2"></i>Backup & Restauração</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">
                        Gere cópias de segurança de todos os seus dados (Clientes, Projetos, Tarefas, Orçamentos e Configurações).
                    </p>

                    <div class="d-grid mb-4">
                        <a href="?exportar=true" class="btn btn-primary fw-bold py-2">
                            <i class="fas fa-download me-2"></i> Baixar Backup Completo (.json)
                        </a>
                        <div class="form-text text-center mt-2">
                            * Arquivos físicos (PDFs, Imagens) da pasta <code>assets/uploads</code> <b>NÃO</b> são incluídos neste arquivo JSON. Faça backup manual dessa pasta.
                        </div>
                    </div>

                    <hr>

                    <h6 class="fw-bold text-danger mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Restaurar Dados</h6>
                    <?php echo $mensagem; ?>
                    
                    <form method="POST" enctype="multipart/form-data" class="border border-danger border-opacity-25 rounded p-3 bg-danger bg-opacity-10">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-danger">Selecione o arquivo .json</label>
                            <input type="file" name="arquivo_json" class="form-control" accept=".json" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger fw-bold" onclick="return confirm('ATENÇÃO: Isso irá substituir seus dados atuais pelos do backup. Deseja continuar?');">
                                <i class="fas fa-upload me-2"></i> Restaurar Backup
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>