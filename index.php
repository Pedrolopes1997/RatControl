<?php
require 'config/db.php';
require 'includes/header.php';

$inicio_mes = date('Y-m-01');
$fim_mes = date('Y-m-t');
$usuario_id = $_SESSION['usuario_id'];

// --- FUNÇÃO DATA EM PORTUGUÊS ---
function dataPtBr($data) {
    $meses = [
        'Jan' => 'Janeiro', 'Feb' => 'Fevereiro', 'Mar' => 'Março', 'Apr' => 'Abril',
        'May' => 'Maio', 'Jun' => 'Junho', 'Jul' => 'Julho', 'Aug' => 'Agosto',
        'Sep' => 'Setembro', 'Oct' => 'Outubro', 'Nov' => 'Novembro', 'Dec' => 'Dezembro'
    ];
    $mes_ingles = date('M', strtotime($data));
    return $meses[$mes_ingles] . ' de ' . date('Y', strtotime($data));
}

// 1. QUICK RESTART
$sqlRecentes = "SELECT t.cliente_id, t.projeto_id, t.descricao, c.nome as nome_cliente, p.nome as nome_projeto, MAX(t.data_criacao) as ultima_data
                FROM tarefas t 
                JOIN clientes c ON t.cliente_id = c.id
                LEFT JOIN projetos p ON t.projeto_id = p.id
                WHERE t.usuario_id = :uid
                GROUP BY t.cliente_id, t.projeto_id, t.descricao
                ORDER BY ultima_data DESC LIMIT 5";
$stmtRec = $pdo->prepare($sqlRecentes);
$stmtRec->execute([':uid' => $usuario_id]);
$recentes = $stmtRec->fetchAll();

// 2. PROJETOS ATIVOS
$sqlProjetos = "SELECT p.*, c.nome as nome_cliente,
                (SELECT SUM(TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW()))) 
                 FROM tempo_logs tl JOIN tarefas t ON tl.tarefa_id = t.id 
                 WHERE t.projeto_id = p.id) as segundos_usados
                FROM projetos p 
                JOIN clientes c ON p.cliente_id = c.id
                WHERE p.status = 'ativo'
                ORDER BY segundos_usados DESC LIMIT 6";
$projetos_ativos = $pdo->query($sqlProjetos)->fetchAll();

// 3. CARDS FINANCEIROS
$sqlAReceber = "SELECT c.moeda, SUM((TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW())) / 3600) * c.valor_hora) as valor
                FROM tempo_logs tl JOIN tarefas t ON tl.tarefa_id = t.id JOIN clientes c ON t.cliente_id = c.id
                WHERE DATE(tl.inicio) BETWEEN :inicio AND :fim AND t.usuario_id = :uid 
                AND t.status_pagamento IN ('pendente', 'faturado') GROUP BY c.moeda";
$stmtAReceber = $pdo->prepare($sqlAReceber);
$stmtAReceber->execute([':inicio' => $inicio_mes, ':fim' => $fim_mes, ':uid' => $usuario_id]);
$areceber = $stmtAReceber->fetchAll();

$sqlPago = "SELECT c.moeda, SUM((TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW())) / 3600) * c.valor_hora) as valor
            FROM tempo_logs tl JOIN tarefas t ON tl.tarefa_id = t.id JOIN clientes c ON t.cliente_id = c.id
            WHERE DATE(tl.inicio) BETWEEN :inicio AND :fim AND t.usuario_id = :uid 
            AND t.status_pagamento = 'pago' GROUP BY c.moeda";
$stmtPago = $pdo->prepare($sqlPago);
$stmtPago->execute([':inicio' => $inicio_mes, ':fim' => $fim_mes, ':uid' => $usuario_id]);
$pago = $stmtPago->fetchAll();

$sqlHoras = "SELECT SUM(TIMESTAMPDIFF(SECOND, inicio, IFNULL(fim, NOW()))) FROM tempo_logs tl 
             JOIN tarefas t ON tl.tarefa_id = t.id WHERE DATE(inicio) BETWEEN :inicio AND :fim AND t.usuario_id = :uid";
$stmtHoras = $pdo->prepare($sqlHoras);
$stmtHoras->execute([':inicio' => $inicio_mes, ':fim' => $fim_mes, ':uid' => $usuario_id]);
$segundos_totais = $stmtHoras->fetchColumn() ?: 0;
?>

<style>
    /* Estilos Específicos para Dashboard */
    .card-dashboard { border: none; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); transition: transform 0.2s; }
    .card-dashboard:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.05); }
    .icon-box { width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; border-radius: 12px; font-size: 1.4rem; }
    
    /* BOTÃO PLAY SOFT UI (UX Melhorada) */
    .btn-quick-play {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        background-color: #e8f5e9; /* Verde bem claro */
        color: #198754; /* Verde Bootstrap */
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); /* Efeito elástico */
        padding-left: 4px; /* Correção ótica do ícone Play */
    }
    .btn-quick-play:hover {
        background-color: #198754;
        color: white;
        transform: scale(1.15); /* Cresce ao passar o mouse */
        box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
    }
    
    .list-item-hover:hover { background-color: #f8f9fa; }
</style>

<div class="row mb-4 align-items-end">
    <div class="col-md-6">
        <h2 class="fw-bold text-dark mb-1">Dashboard</h2>
        <p class="text-muted mb-0">Resumo de <strong><?php echo dataPtBr(date('Y-m-d')); ?></strong></p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center p-4">
                <div class="icon-box bg-primary bg-opacity-10 text-primary me-3">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Horas Totais</h6>
                    <h3 class="mb-0 fw-bold font-monospace"><?php echo sprintf("%02d:%02d", floor($segundos_totais/3600), floor(($segundos_totais%3600)/60)); ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center p-4">
                <div class="icon-box bg-warning bg-opacity-10 text-warning me-3">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">A Receber</h6>
                    <h4 class="mb-0 fw-bold text-dark">
                        <?php 
                        if(empty($areceber)) echo "0,00";
                        foreach($areceber as $r) echo '<div>'.$r['moeda'].' '.number_format($r['valor'], 2, ',', '.').'</div>'; 
                        ?>
                    </h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-dashboard h-100">
            <div class="card-body d-flex align-items-center p-4">
                <div class="icon-box bg-success bg-opacity-10 text-success me-3">
                    <i class="fas fa-wallet"></i>
                </div>
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Em Caixa</h6>
                    <h4 class="mb-0 fw-bold text-dark">
                        <?php 
                        if(empty($pago)) echo "0,00";
                        foreach($pago as $p) echo '<div>'.$p['moeda'].' '.number_format($p['valor'], 2, ',', '.').'</div>'; 
                        ?>
                    </h4>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-4 mb-4">
        
        <div class="d-grid gap-2 mb-4">
            <a href="timer.php" class="btn btn-primary btn-lg py-3 fw-bold shadow-sm border-0" 
               style="background: linear-gradient(45deg, #0d6efd, #0a58ca); border-radius: 12px;">
                <i class="fas fa-play me-2"></i> INICIAR TIMER
            </a>
            <div class="row g-2">
                <div class="col-6">
                    <a href="manual.php" class="btn btn-white border w-100 py-2 fw-bold shadow-sm" style="border-radius: 10px;"><i class="fas fa-pen me-1 text-muted"></i> Manual</a>
                </div>
                <div class="col-6">
                    <a href="despesas.php" class="btn btn-white border w-100 py-2 fw-bold shadow-sm" style="border-radius: 10px;"><i class="fas fa-receipt me-1 text-danger"></i> Despesa</a>
                </div>
            </div>
        </div>

        <div class="card card-dashboard">
            <div class="card-header bg-white fw-bold border-bottom py-3">
                <i class="fas fa-bolt text-warning me-2"></i> Reiniciar Recentes
            </div>
            <div class="list-group list-group-flush">
                <?php foreach($recentes as $rec): 
                    $projeto_id_safe = $rec['projeto_id'] ?: 'null';
                ?>
                <div class="list-group-item d-flex justify-content-between align-items-center p-3 border-0 border-bottom list-item-hover">
                    <div style="width: 80%; padding-right: 10px;">
                        <div class="fw-bold text-dark text-truncate" title="<?php echo htmlspecialchars($rec['descricao']); ?>">
                            <?php echo $rec['descricao']; ?>
                        </div>
                        <div class="small text-muted mt-1 text-truncate">
                            <i class="fas fa-briefcase me-1" style="font-size: 0.7rem;"></i> <?php echo $rec['nome_cliente']; ?>
                            <?php if($rec['nome_projeto']): ?>
                                &bull; <span class="text-primary"><?php echo $rec['nome_projeto']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <button class="btn-quick-play shadow-sm" 
                            onclick="reiniciarTarefa(<?php echo $rec['cliente_id']; ?>, <?php echo $projeto_id_safe; ?>, '<?php echo addslashes($rec['descricao']); ?>')"
                            title="Reiniciar tarefa">
                        <i class="fas fa-play"></i>
                    </button>
                    
                </div>
                <?php endforeach; ?>
                <?php if(empty($recentes)): ?>
                    <div class="p-4 text-center text-muted small">
                        <i class="fas fa-history fa-2x mb-2 opacity-25"></i><br>
                        Nenhuma atividade recente.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8 mb-4">
        <div class="card card-dashboard h-100">
            <div class="card-header bg-white fw-bold border-bottom py-3 d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-pie text-primary me-2"></i> Projetos Ativos</span>
                <a href="projetos.php" class="btn btn-sm btn-light text-primary fw-bold">Ver Todos</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-uppercase small text-muted">
                            <tr>
                                <th class="ps-4 py-3">Projeto / Cliente</th>
                                <th>Horas</th>
                                <th class="pe-4" style="width: 35%;">Progresso</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($projetos_ativos as $p): 
                                $usado = $p['segundos_usados'] ?? 0;
                                $horas_usadas = $usado / 3600;
                                $meta = $p['horas_estimadas'];
                                $perc = 0; $cor = 'bg-primary';
                                
                                if($meta > 0) {
                                    $perc = ($horas_usadas / $meta) * 100;
                                    if($perc > 100) { $perc = 100; $cor = 'bg-danger'; }
                                    elseif($perc > 80) { $cor = 'bg-warning'; }
                                }
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?php echo $p['nome']; ?></div>
                                    <small class="text-muted"><?php echo $p['nome_cliente']; ?></small>
                                </td>
                                <td class="font-monospace fw-bold text-secondary">
                                    <?php echo number_format($horas_usadas, 1, ',', '.'); ?>h
                                </td>
                                <td class="pe-4">
                                    <?php if($meta > 0): ?>
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="text-muted"><?php echo number_format($perc,0); ?>%</span>
                                            <span class="text-muted">Meta: <?php echo $meta; ?>h</span>
                                        </div>
                                        <div class="progress" style="height: 8px; border-radius: 4px;">
                                            <div class="progress-bar <?php echo $cor; ?>" style="width: <?php echo $perc; ?>%"></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info">Contínuo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($projetos_ativos)): ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted">Nenhum projeto ativo.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function reiniciarTarefa(clienteId, projetoId, descricao) {
    if(!confirm("Iniciar cronómetro para: " + descricao + "?")) return;
    
    // Feedback visual nos botões
    const botoes = document.querySelectorAll('.btn-quick-play');
    botoes.forEach(b => { b.disabled = true; b.style.opacity = '0.5'; });

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao: 'iniciar', cliente_id: clienteId, projeto_id: (projetoId===null?'':projetoId), descricao: descricao, tags: [] })
    })
    .then(r => r.json())
    .then(data => {
        if(data.sucesso) window.location.href = 'timer.php?msg=iniciado';
        else { alert('Erro: ' + data.msg); window.location.reload(); }
    })
    .catch(err => {
        alert('Erro de conexão.');
        window.location.reload();
    });
}
</script>

<?php require 'includes/footer.php'; ?>