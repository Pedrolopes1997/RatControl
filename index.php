<?php
require 'config/db.php';
require 'includes/header.php';

$inicio_mes = date('Y-m-01');
$fim_mes    = date('Y-m-t');
$uid        = $_SESSION['usuario_id'];

// --- TRADUÇÃO DE MÊS (Simples e Infalível) ---
$meses_pt = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];
$mes_atual_extenso = $meses_pt[(int)date('n')] . '/' . date('Y');

// --- 1. DADOS PARA O GRÁFICO ---
$meses_grafico = [];
$receitas_grafico = [];
$despesas_grafico = [];

for ($i = 5; $i >= 0; $i--) {
    $dt = new DateTime("first day of this month");
    $dt->modify("-$i months");
    
    $chave_mes = $dt->format('Y-m');
    $label_mes = $dt->format('M/y'); // Ex: Jan/24
    
    // Tradução Label Gráfico
    $mes_curto = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    $meses_grafico[] = $mes_curto[(int)$dt->format('n') - 1] . '/' . $dt->format('y');
    
    $receitas_grafico[$chave_mes] = 0;
    $despesas_grafico[$chave_mes] = 0;
}

// Busca Receita
$sqlRec = "SELECT DATE_FORMAT(tl.inicio, '%Y-%m') as mes, 
           SUM((TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW())) / 3600) * c.valor_hora) as valor
           FROM tempo_logs tl 
           JOIN tarefas t ON tl.tarefa_id = t.id 
           JOIN clientes c ON t.cliente_id = c.id
           WHERE t.usuario_id = :uid 
           AND t.status_pagamento = 'pago' 
           AND tl.inicio >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 5 MONTH)
           GROUP BY mes";
$stmt = $pdo->prepare($sqlRec);
$stmt->execute([':uid' => $uid]);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if(isset($receitas_grafico[$row['mes']])) {
        $receitas_grafico[$row['mes']] = (float)$row['valor'];
    }
}

// Busca Despesas
$sqlDesp = "SELECT DATE_FORMAT(data_despesa, '%Y-%m') as mes, SUM(valor) as valor
            FROM despesas
            WHERE usuario_id = :uid 
            AND data_despesa >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 5 MONTH)
            GROUP BY mes";
$stmt = $pdo->prepare($sqlDesp);
$stmt->execute([':uid' => $uid]);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if(isset($despesas_grafico[$row['mes']])) {
        $despesas_grafico[$row['mes']] = (float)$row['valor'];
    }
}

// --- 2. KPI CARDS ---
// A Receber
$stmtAReceber = $pdo->prepare("SELECT c.moeda, SUM((TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW())) / 3600) * c.valor_hora) as valor
                               FROM tempo_logs tl JOIN tarefas t ON tl.tarefa_id = t.id JOIN clientes c ON t.cliente_id = c.id
                               WHERE DATE(tl.inicio) BETWEEN :ini AND :fim AND t.usuario_id = :uid 
                               AND t.status_pagamento IN ('pendente', 'faturado') GROUP BY c.moeda");
$stmtAReceber->execute([':ini' => $inicio_mes, ':fim' => $fim_mes, ':uid' => $uid]);
$areceber = $stmtAReceber->fetchAll(PDO::FETCH_KEY_PAIR);

// Recebido
$stmtPago = $pdo->prepare("SELECT c.moeda, SUM((TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW())) / 3600) * c.valor_hora) as valor
                           FROM tempo_logs tl JOIN tarefas t ON tl.tarefa_id = t.id JOIN clientes c ON t.cliente_id = c.id
                           WHERE DATE(tl.inicio) BETWEEN :ini AND :fim AND t.usuario_id = :uid 
                           AND t.status_pagamento = 'pago' GROUP BY c.moeda");
$stmtPago->execute([':ini' => $inicio_mes, ':fim' => $fim_mes, ':uid' => $uid]);
$pago = $stmtPago->fetchAll(PDO::FETCH_KEY_PAIR);

// Horas Totais
$stmtHoras = $pdo->prepare("SELECT SUM(TIMESTAMPDIFF(SECOND, inicio, IFNULL(fim, NOW()))) FROM tempo_logs tl 
                            JOIN tarefas t ON tl.tarefa_id = t.id WHERE DATE(inicio) BETWEEN :ini AND :fim AND t.usuario_id = :uid");
$stmtHoras->execute([':ini' => $inicio_mes, ':fim' => $fim_mes, ':uid' => $uid]);
$segundos_totais = $stmtHoras->fetchColumn() ?: 0;

// --- 3. LISTAS ---
$stmtRec = $pdo->prepare("SELECT t.cliente_id, t.projeto_id, t.descricao, c.nome as nome_cliente, p.nome as nome_projeto, MAX(t.data_criacao) as ultima_data
                          FROM tarefas t JOIN clientes c ON t.cliente_id = c.id LEFT JOIN projetos p ON t.projeto_id = p.id
                          WHERE t.usuario_id = :uid GROUP BY t.descricao, t.cliente_id, t.projeto_id ORDER BY ultima_data DESC LIMIT 5");
$stmtRec->execute([':uid' => $uid]);
$recentes = $stmtRec->fetchAll();

$sqlProjetos = "SELECT p.*, c.nome as nome_cliente,
                (SELECT SUM(TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW()))) 
                 FROM tempo_logs tl JOIN tarefas t ON tl.tarefa_id = t.id WHERE t.projeto_id = p.id) as segundos_usados
                FROM projetos p JOIN clientes c ON p.cliente_id = c.id
                WHERE p.status = 'ativo' ORDER BY segundos_usados DESC LIMIT 5";
$projetos_ativos = $pdo->query($sqlProjetos)->fetchAll();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .card-kpi { border: none; border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; background: white; }
    .card-kpi:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05) !important; }
    .icon-kpi { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    
    /* BOTÃO PLAY AJUSTADO */
    .btn-play-sm { 
        width: 34px; 
        height: 34px; 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        padding: 0;
        padding-left: 2px; /* Ajuste ótico para centralizar o triângulo */
        transition: all 0.2s; 
        border: none;
    }
    .btn-play-sm:hover { transform: scale(1.1); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    
    .table-clean td { padding-top: 12px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0; }
    .table-clean tr:last-child td { border-bottom: none; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-0">Olá, <?php echo explode(' ', $_SESSION['usuario_nome'])[0]; ?>! 👋</h4>
        <p class="text-muted small mb-0">Aqui está o resumo de <strong><?php echo $mes_atual_extenso; ?></strong>.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="manual.php" class="btn btn-white border shadow-sm fw-bold"><i class="fas fa-plus me-2"></i> Manual</a>
        <a href="timer.php" class="btn btn-primary shadow-sm fw-bold"><i class="fas fa-stopwatch me-2"></i> Timer</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card card-kpi shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon-kpi bg-primary bg-opacity-10 text-primary me-3"><i class="far fa-clock"></i></div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase">Horas Trabalhadas</div>
                    <div class="fs-4 fw-bold text-dark font-monospace">
                        <?php echo sprintf("%02d:%02d", floor($segundos_totais/3600), floor(($segundos_totais%3600)/60)); ?> <small class="fs-6 text-muted">h</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card card-kpi shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body d-flex align-items-center">
                <div class="icon-kpi bg-warning bg-opacity-10 text-warning me-3"><i class="fas fa-hourglass-half"></i></div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase">A Receber (Est.)</div>
                    <div class="fs-5 fw-bold text-dark lh-1">
                        <?php 
                        if(empty($areceber)) echo "0,00";
                        foreach($areceber as $moeda => $val) {
                            echo '<div class="mb-1"><small class="text-muted">'.$moeda.'</small> '.number_format($val, 2, ',', '.').'</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card card-kpi shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body d-flex align-items-center">
                <div class="icon-kpi bg-success bg-opacity-10 text-success me-3"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase">Recebido (Caixa)</div>
                    <div class="fs-5 fw-bold text-dark lh-1">
                        <?php 
                        if(empty($pago)) echo "0,00";
                        foreach($pago as $moeda => $val) {
                            echo '<div class="mb-1"><small class="text-muted">'.$moeda.'</small> '.number_format($val, 2, ',', '.').'</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold m-0 text-dark"><i class="fas fa-chart-area me-2 text-primary"></i>Fluxo Financeiro (6 Meses)</h6>
            </div>
            <div class="card-body">
                <div style="height: 280px; width: 100%;">
                    <canvas id="financeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold m-0 text-dark"><i class="fas fa-history me-2 text-secondary"></i>Retomar Recentes</h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach($recentes as $rec): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-3 py-3 border-bottom">
                        <div style="overflow: hidden; width: 80%;">
                            <div class="fw-bold text-dark text-truncate" title="<?php echo htmlspecialchars($rec['descricao']); ?>" style="font-size: 0.9rem;">
                                <?php echo $rec['descricao']; ?>
                            </div>
                            <div class="small text-muted text-truncate">
                                <?php echo $rec['nome_cliente']; ?> 
                                <?php if($rec['nome_projeto']) echo " • <span class='text-primary'>{$rec['nome_projeto']}</span>"; ?>
                            </div>
                        </div>
                        <button class="btn btn-success btn-play-sm shadow-sm" 
                                onclick="reiniciarTarefa(<?php echo $rec['cliente_id']; ?>, <?php echo $rec['projeto_id'] ?: 'null'; ?>, '<?php echo addslashes($rec['descricao']); ?>')">
                            <i class="fas fa-play fa-xs text-white"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    <?php if(empty($recentes)): ?>
                        <div class="text-center py-5 text-muted small">Nenhuma tarefa recente.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold m-0 text-dark"><i class="fas fa-crown me-2 text-warning"></i>Projetos Mais Ativos</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-clean mb-0 align-middle">
                    <thead class="bg-light small text-muted text-uppercase">
                        <tr>
                            <th class="ps-4">Projeto</th>
                            <th>Cliente</th>
                            <th>Consumo</th>
                            <th class="pe-4" style="width: 30%;">Status da Meta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($projetos_ativos as $p): 
                            $usado = $p['segundos_usados'] / 3600;
                            $meta = $p['horas_estimadas'];
                            $perc = ($meta > 0) ? min(($usado / $meta) * 100, 100) : 0;
                            $cor = ($perc > 90) ? 'bg-danger' : (($perc > 70) ? 'bg-warning' : 'bg-success');
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark"><?php echo $p['nome']; ?></td>
                            <td class="text-muted small"><?php echo $p['nome_cliente']; ?></td>
                            <td class="font-monospace fw-bold text-dark"><?php echo number_format($usado, 1, ',', '.'); ?> h</td>
                            <td class="pe-4">
                                <?php if($meta > 0): ?>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                            <div class="progress-bar <?php echo $cor; ?>" style="width: <?php echo $perc; ?>%"></div>
                                        </div>
                                        <span class="small text-muted" style="width: 35px;"><?php echo number_format($perc, 0); ?>%</span>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border">Recorrente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// --- GRÁFICO ---
const ctx = document.getElementById('financeChart');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_values($meses_grafico)); ?>,
        datasets: [
            {
                label: 'Receita',
                data: <?php echo json_encode(array_values($receitas_grafico)); ?>,
                borderColor: '#0e2a47', // Azul WeCare
                backgroundColor: 'rgba(14, 42, 71, 0.05)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                label: 'Despesas',
                data: <?php echo json_encode(array_values($despesas_grafico)); ?>,
                borderColor: '#dc3545',
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [5, 5],
                tension: 0.4,
                pointRadius: 0
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'top', align: 'end' },
            tooltip: {
                backgroundColor: '#0e2a47',
                titleFont: { size: 13 },
                bodyFont: { size: 13 },
                callbacks: {
                    label: function(c) {
                        return c.dataset.label + ': ' + c.parsed.y.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
                    }
                }
            }
        },
        scales: {
            y: { grid: { borderDash: [4, 4], color: '#f0f0f0' }, beginAtZero: true },
            x: { grid: { display: false } }
        }
    }
});

// --- REINICIAR TAREFA ---
function reiniciarTarefa(cliId, projId, desc) {
    if(!confirm("Retomar: " + desc + "?")) return;
    
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ 
            acao: 'iniciar', 
            cliente_id: cliId, 
            projeto_id: (projId === null ? '' : projId), 
            descricao: desc, 
            tags: [] 
        })
    })
    .then(r => r.json())
    .then(d => {
        if(d.sucesso) window.location.href = 'timer.php?msg=iniciado';
        else alert(d.msg);
    });
}
</script>

<?php require 'includes/footer.php'; ?>