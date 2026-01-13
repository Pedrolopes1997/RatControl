<?php
// portal.php - Área do Cliente
require 'config/db.php';

// 1. SEGURANÇA E VALIDAÇÃO
$token = $_GET['t'] ?? '';
if (empty($token)) die("<div style='padding:50px;text-align:center;font-family:sans-serif;color:#666;'>🔒 Acesso Negado. Token ausente.</div>");

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE token_acesso = :t AND status = 'ativo'");
$stmt->execute([':t' => $token]);
$cliente = $stmt->fetch();

if (!$cliente) die("<div style='padding:50px;text-align:center;font-family:sans-serif;color:#666;'>🚫 Link inválido ou expirado.</div>");

// 2. FILTROS
$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fim    = $_GET['fim']    ?? date('Y-m-t');
$filtro_proj = $_GET['projeto'] ?? '';

// 3. DADOS: PROJETOS (Progresso)
$sqlProjetos = "SELECT p.*, 
                (SELECT SUM(TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW()))) 
                 FROM tempo_logs tl JOIN tarefas t ON tl.tarefa_id = t.id 
                 WHERE t.projeto_id = p.id) as segundos_totais
                FROM projetos p 
                WHERE p.cliente_id = :cid AND p.status = 'ativo' ORDER BY p.nome";
$stmtP = $pdo->prepare($sqlProjetos);
$stmtP->execute([':cid' => $cliente['id']]);
$projetos_lista = $stmtP->fetchAll();

// 4. DADOS: ATIVIDADES (Histórico)
$sqlAtiv = "SELECT t.id, t.descricao, t.data_criacao, t.projeto_id, p.nome as nome_projeto,
            (SELECT SUM(TIMESTAMPDIFF(SECOND, inicio, IFNULL(fim, NOW()))) FROM tempo_logs WHERE tarefa_id = t.id) as duracao
            FROM tarefas t
            LEFT JOIN projetos p ON t.projeto_id = p.id
            WHERE t.cliente_id = :cid 
            AND DATE(t.data_criacao) BETWEEN :inicio AND :fim";

$params = [':cid' => $cliente['id'], ':inicio' => $inicio, ':fim' => $fim];

if (!empty($filtro_proj)) {
    $sqlAtiv .= " AND t.projeto_id = :pid";
    $params[':pid'] = $filtro_proj;
}

$sqlAtiv .= " ORDER BY t.data_criacao DESC";
$stmtA = $pdo->prepare($sqlAtiv);
$stmtA->execute($params);
$atividades = $stmtA->fetchAll();

// 5. CÁLCULOS KPI E GRÁFICO
$total_segundos_periodo = 0;
$dados_grafico = [];

// Inicializa gráfico vazio para o período (opcional, aqui faremos apenas dias com atividade)
foreach ($atividades as $a) {
    $duracao = $a['duracao'] ?? 0;
    $total_segundos_periodo += $duracao;
    
    $dia = date('d/m', strtotime($a['data_criacao']));
    if (!isset($dados_grafico[$dia])) $dados_grafico[$dia] = 0;
    $dados_grafico[$dia] += ($duracao / 3600);
}
// Ordena gráfico cronologicamente (o loop veio reverso do banco)
$dados_grafico = array_reverse($dados_grafico);

function fmtHoras($seg) { 
    return number_format($seg / 3600, 1, ',', '.'); 
}

// Avatar do Cliente
$clienteLogo = !empty($cliente['logo']) 
    ? $cliente['logo'] 
    : "https://ui-avatars.com/api/?name=".urlencode($cliente['nome'])."&background=fff&color=0d6efd&size=128&font-size=0.5";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal <?php echo htmlspecialchars($cliente['nome']); ?></title>
    <link rel="icon" href="<?php echo $clienteLogo; ?>" />
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root { 
            --brand-color: #0d6efd; 
            --brand-gradient: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%); 
            --card-shadow: 0 10px 20px rgba(0,0,0,0.03); 
        }
        body { background-color: #f3f5f9; font-family: 'Inter', sans-serif; color: #495057; }
        
        /* Header Hero */
        .header-bg { 
            background: var(--brand-gradient); 
            padding: 3rem 0; 
            color: white; 
            margin-bottom: -3rem; /* Efeito sobreposto */
        }
        
        .card { border: none; border-radius: 12px; box-shadow: var(--card-shadow); transition: transform 0.2s; }
        .card:hover { transform: translateY(-2px); }
        
        .kpi-value { font-size: 2rem; font-weight: 700; letter-spacing: -1px; }
        .progress-slim { height: 6px; border-radius: 4px; background-color: #e9ecef; }
        
        a.card-link { text-decoration: none; color: inherit; display: block; }
        a.card-link:hover .text-dark { color: var(--brand-color) !important; }
        
        tr[onclick] { cursor: pointer; transition: background 0.1s; }
        tr[onclick]:hover { background-color: #f8f9fa !important; }
    </style>
</head>
<body>

<div class="header-bg">
    <div class="container pb-5">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white rounded-4 p-1 shadow d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                    <img src="<?php echo $clienteLogo; ?>" class="rounded-3" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <div>
                    <span class="badge bg-white text-dark bg-opacity-75 mb-1 px-3 py-1 rounded-pill fw-normal">Área do Cliente</span>
                    <h2 class="fw-bold mb-0 text-white"><?php echo htmlspecialchars($cliente['nome']); ?></h2>
                </div>
            </div>
            <div class="text-end text-white-50 d-none d-md-block">
                <small class="d-block text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Última Atualização</small>
                <strong class="text-white"><?php echo date('d/m/Y'); ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="container" style="margin-top: 2rem;">
    
    <div class="card p-4 mb-4 shadow-sm">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="t" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold text-uppercase">Início</label>
                <input type="date" name="inicio" class="form-control bg-light border-0 fw-bold" value="<?php echo $inicio; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold text-uppercase">Fim</label>
                <input type="date" name="fim" class="form-control bg-light border-0 fw-bold" value="<?php echo $fim; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold text-uppercase">Projeto</label>
                <select name="projeto" class="form-select bg-light border-0 fw-bold">
                    <option value="">Todos os Projetos</option>
                    <?php foreach($projetos_lista as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($filtro_proj == $p['id']) ? 'selected' : ''; ?>><?php echo $p['nome']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100 fw-bold py-2"><i class="fas fa-filter me-1"></i> Filtrar</button>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100 p-4 border-start border-4 border-primary">
                <h6 class="text-muted text-uppercase small fw-bold">Horas Consumidas</h6>
                <div class="kpi-value text-primary"><?php echo fmtHoras($total_segundos_periodo); ?>h</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 p-4 border-start border-4 border-success">
                <h6 class="text-muted text-uppercase small fw-bold">Entregas Realizadas</h6>
                <div class="kpi-value text-success"><?php echo count($atividades); ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 p-3 d-flex justify-content-center align-items-center">
                <?php if(count($dados_grafico) > 0): ?>
                    <div style="width: 100%; height: 80px;">
                        <canvas id="miniGrafico"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-muted small">Sem dados para exibir.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-pie me-2 text-primary"></i>Status dos Projetos</h6>
                </div>
                <div class="card-body">
                    <?php foreach($projetos_lista as $p): 
                        $usado = $p['segundos_totais'] ?? 0;
                        $meta = $p['horas_estimadas'];
                        // Cálculo de Porcentagem Seguro
                        $perc = ($meta > 0) ? min(($usado/3600 / $meta)*100, 100) : 0;
                        $corBarra = ($perc > 100) ? 'bg-danger' : (($perc > 80) ? 'bg-warning' : 'bg-primary');
                        
                        // Link para detalhes
                        $urlDetalhe = "portal_detalhes.php?t={$token}&p={$p['id']}";
                    ?>
                    <div class="mb-4">
                        <a href="<?php echo $urlDetalhe; ?>" class="card-link" title="Ver detalhes">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-bold text-dark small text-truncate" style="max-width: 70%;"><?php echo $p['nome']; ?></span>
                                <span class="small fw-bold text-muted"><?php echo fmtHoras($usado); ?>h</span>
                            </div>
                            <?php if($meta > 0): ?>
                                <div class="progress progress-slim">
                                    <div class="progress-bar <?php echo $corBarra; ?>" style="width: <?php echo $perc; ?>%"></div>
                                </div>
                                <div class="text-end mt-1"><small class="text-muted" style="font-size: 0.65rem;"><?php echo number_format($perc, 0); ?>% da meta</small></div>
                            <?php else: ?>
                                <div class="progress progress-slim"><div class="progress-bar bg-info" style="width: 100%"></div></div>
                                <div class="text-end mt-1"><small class="text-muted" style="font-size: 0.65rem;">Recorrente</small></div>
                            <?php endif; ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if(empty($projetos_lista)): ?>
                        <div class="text-center text-muted small py-4">Nenhum projeto ativo.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-list-ul me-2 text-primary"></i>Histórico de Atividades</h6>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light small text-muted text-uppercase">
                            <tr>
                                <th class="ps-4">Data</th>
                                <th>Descrição</th>
                                <th class="text-end pe-4">Duração</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($atividades as $a): ?>
                            <tr onclick="window.location='portal_detalhes.php?t=<?php echo $token; ?>&p=<?php echo $a['projeto_id'] ?: 0; ?>'" title="Ver Projeto">
                                <td class="ps-4 text-secondary small fw-bold" style="white-space: nowrap;">
                                    <?php echo date('d/m/Y', strtotime($a['data_criacao'])); ?>
                                </td>
                                <td class="py-3">
                                    <?php if($a['nome_projeto']): ?>
                                        <span class="badge bg-light text-dark border mb-1 fw-normal"><?php echo $a['nome_projeto']; ?></span>
                                        <br>
                                    <?php endif; ?>
                                    <span class="text-dark" style="font-size: 0.95rem;"><?php echo $a['descricao']; ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <span class="badge bg-primary bg-opacity-10 text-primary font-monospace px-3 py-1 rounded-pill">
                                        <?php echo fmtHoras($a['duracao']); ?> h
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($atividades)): ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted">Nenhuma atividade registrada neste período.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gráfico Mini
<?php if(count($dados_grafico) > 0): ?>
new Chart(document.getElementById('miniGrafico'), {
    type: 'bar',
    data: { 
        labels: <?php echo json_encode(array_keys($dados_grafico)); ?>, 
        datasets: [{ 
            data: <?php echo json_encode(array_values($dados_grafico)); ?>, 
            backgroundColor: '#0d6efd', 
            borderRadius: 4,
            barThickness: 6
        }] 
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { legend: { display: false }, tooltip: { enabled: true } }, 
        scales: { x: { display: false }, y: { display: false } } 
    }
});
<?php endif; ?>
</script>

</body>
</html>