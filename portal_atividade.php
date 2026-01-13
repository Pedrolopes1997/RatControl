<?php
// portal_atividade.php - Detalhes de uma Tarefa Específica
require 'config/db.php';

// 1. SEGURANÇA
$token = $_GET['t'] ?? '';
$tid   = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$token || !$tid) die("Acesso inválido.");

// Valida se a tarefa pertence a um cliente com este token
$sql = "SELECT t.*, p.nome as nome_projeto, c.nome as nome_cliente, c.logo 
        FROM tarefas t
        JOIN clientes c ON t.cliente_id = c.id
        LEFT JOIN projetos p ON t.projeto_id = p.id
        WHERE c.token_acesso = :t AND t.id = :tid";
$stmt = $pdo->prepare($sql);
$stmt->execute([':t' => $token, ':tid' => $tid]);
$tarefa = $stmt->fetch();

if (!$tarefa) die("Atividade não encontrada ou acesso negado.");

// 2. BUSCA LOGS DE TEMPO (Detalhe dos horários)
$stmtLogs = $pdo->prepare("SELECT * FROM tempo_logs WHERE tarefa_id = :tid ORDER BY inicio ASC");
$stmtLogs->execute([':tid' => $tid]);
$logs = $stmtLogs->fetchAll();

// 3. CÁLCULO TOTAL
$total_segundos = 0;
foreach($logs as $log) {
    $fim = $log['fim'] ? strtotime($log['fim']) : time();
    $ini = strtotime($log['inicio']);
    $total_segundos += ($fim - $ini);
}

function fmtHoras($seg) {
    $h = floor($seg / 3600);
    $m = floor(($seg % 3600) / 60);
    return sprintf("%02dh %02dm", $h, $m);
}

// Avatar ou Ícone
$clienteLogo = !empty($tarefa['logo']) ? $tarefa['logo'] : 'https://ui-avatars.com/api/?name='.urlencode($tarefa['nome_cliente'])."&background=fff&color=0d6efd";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Atividade</title>
    <link rel="icon" href="<?php echo $clienteLogo; ?>" />
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root { --brand-gradient: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%); }
        body { background-color: #f3f5f9; font-family: 'Inter', sans-serif; color: #495057; }
        
        .header-bg { background: var(--brand-gradient); padding: 2rem 0; color: white; margin-bottom: -2rem; }
        
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        
        .btn-voltar { color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem; transition: 0.2s; display: inline-flex; align-items: center; }
        .btn-voltar:hover { color: white; transform: translateX(-3px); }
        
        /* Timeline Vertical */
        .timeline { margin-left: 10px; border-left: 2px solid #e9ecef; padding-bottom: 10px; }
        .timeline-item { padding-left: 25px; padding-bottom: 25px; position: relative; }
        .timeline-item::before { 
            content: ''; width: 14px; height: 14px; background: #fff; 
            border: 3px solid #0d6efd; border-radius: 50%; 
            position: absolute; left: -8px; top: 2px; z-index: 2;
        }
        .timeline-item:last-child { border-left: transparent; padding-bottom: 0; }
        
        .badge-time { font-family: monospace; font-size: 0.9rem; letter-spacing: -0.5px; }
    </style>
</head>
<body>

<div class="header-bg">
    <div class="container pb-5">
        <div class="mb-3">
            <?php 
                $linkVoltar = $tarefa['projeto_id'] 
                    ? "portal_detalhes.php?t=$token&p={$tarefa['projeto_id']}" 
                    : "portal.php?t=$token";
            ?>
            <a href="<?php echo $linkVoltar; ?>" class="btn-voltar"><i class="fas fa-arrow-left me-2"></i> Voltar</a>
        </div>
        <div class="d-flex align-items-start gap-3">
            <div class="bg-white rounded-3 p-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; flex-shrink: 0;">
                <i class="fas fa-check-circle fa-2x text-success"></i>
            </div>
            <div>
                <span class="badge bg-white text-dark bg-opacity-75 mb-2 px-2 fw-normal">Detalhes da Atividade</span>
                <h2 class="fw-bold mb-0 text-white" style="line-height: 1.2; word-wrap: break-word;"><?php echo htmlspecialchars($tarefa['descricao']); ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="container" style="margin-top: 4rem;">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <div class="card mb-4 p-4">
                <div class="row text-center g-3">
                    <div class="col-4 border-end">
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Data</small>
                        <div class="fs-5 fw-bold text-dark"><?php echo date('d/m/Y', strtotime($tarefa['data_criacao'])); ?></div>
                    </div>
                    <div class="col-4 border-end">
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Duração Total</small>
                        <div class="fs-5 fw-bold text-primary"><?php echo fmtHoras($total_segundos); ?></div>
                    </div>
                    <div class="col-4">
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Projeto</small>
                        <div class="fs-5 fw-bold text-dark" style="line-height: 1.2;">
                            <?php echo $tarefa['nome_projeto'] ?: 'Avulso'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white py-3 fw-bold border-bottom">
                    <i class="fas fa-history me-2 text-muted"></i> Registro de Tempo (Logs)
                </div>
                <div class="card-body p-4">
                    <?php if(count($logs) > 0): ?>
                        <div class="timeline ms-2">
                            <?php foreach($logs as $log): 
                                $inicio = strtotime($log['inicio']);
                                $fim = $log['fim'] ? strtotime($log['fim']) : time();
                                $duracao = $fim - $inicio;
                            ?>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold text-dark mb-1">Execução de Tarefa</div>
                                        <div class="text-muted small">
                                            <i class="far fa-clock me-1"></i>
                                            <?php echo date('H:i', $inicio); ?> até <?php echo $log['fim'] ? date('H:i', $fim) : 'Agor...'; ?>
                                        </div>
                                    </div>
                                    <span class="badge bg-light text-secondary border badge-time">
                                        + <?php echo fmtHoras($duracao); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">Nenhum registro de tempo detalhado.</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>