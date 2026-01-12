<?php
// portal.php - Versão Visual Premium + Funcionalidades Completas
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

// 3. DADOS: PROJETOS
$sqlProjetos = "SELECT p.*, 
                (SELECT SUM(TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW()))) 
                 FROM tempo_logs tl JOIN tarefas t ON tl.tarefa_id = t.id 
                 WHERE t.projeto_id = p.id) as segundos_totais
                FROM projetos p 
                WHERE p.cliente_id = :cid AND p.status = 'ativo' ORDER BY p.nome";
$stmtP = $pdo->prepare($sqlProjetos);
$stmtP->execute([':cid' => $cliente['id']]);
$projetos_lista = $stmtP->fetchAll();

// 4. DADOS: ATIVIDADES
$sqlAtiv = "SELECT t.descricao, t.data_criacao, p.nome as nome_projeto,
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
foreach ($atividades as $a) {
    $duracao = $a['duracao'];
    $total_segundos_periodo += $duracao;
    $dia = date('d/m', strtotime($a['data_criacao']));
    if (!isset($dados_grafico[$dia])) $dados_grafico[$dia] = 0;
    $dados_grafico[$dia] += ($duracao / 3600);
}
$dados_grafico = array_reverse($dados_grafico);

function fmtHoras($seg) { return number_format($seg / 3600, 1, ',', '.'); }

// FAVICON (Logo ou Ratinho)
$favicon = !empty($cliente['logo']) ? $cliente['logo'] : 'https://fav.farm/🐭';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal <?php echo htmlspecialchars($cliente['nome']); ?></title>
    <link rel="icon" href="<?php echo $favicon; ?>" />
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --brand-gradient: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%); /* Gradiente Dark Premium */
            --card-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        body { background-color: #f3f5f9; font-family: 'Inter', sans-serif; color: #495057; }
        
        /* Cabeçalho */
        .header-bg { background: var(--brand-gradient); padding: 3rem 0; color: white; margin-bottom: -3rem; }
        
        /* Cards */
        .card { border: none; border-radius: 16px; box-shadow: var(--card-shadow); transition: transform 0.2s; }
        .card:hover { transform: translateY(-3px); }
        
        /* KPIs */
        .kpi-icon { font-size: 2.5rem; opacity: 0.2; }
        .kpi-value { font-size: 2rem; font-weight: 700; letter-spacing: -1px; }
        
        /* Tabela */
        .table-portal thead th { 
            text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; color: #adb5bd; border-bottom-width: 1px; 
        }
        .texto-limitado { max-width: 350px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: help; }
        
        /* Projetos */
        .progress-slim { height: 8px; border-radius: 4px; background-color: #e9ecef; }
    </style>
</head>
<body>

<div class="header-bg">
    <div class="container pb-5">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white rounded-4 p-2 shadow d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                    <?php if(!empty($cliente['logo'])): ?>
                        <img src="<?php echo $cliente['logo']; ?>" style="width: 100%; height: 100%; object-fit: contain;">
                    <?php else: ?>
                        <span class="fs-1 fw-bold text-dark"><?php echo strtoupper(substr($cliente['nome'], 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                
                <div>
                    <span class="badge bg-white text-dark bg-opacity-75 mb-1 px-3 py-1" style="font-weight: 500;">Área do Cliente</span>
                    <h1 class="fw-bold mb-0 text-white" style="letter-spacing: -0.5px;"><?php echo htmlspecialchars($cliente['nome']); ?></h1>
                </div>
            </div>
            
            <div class="text-end text-white-50 d-none d-md-block">
                <?php if(!empty($cliente['site'])): ?>
                    <a href="<?php echo strpos($cliente['site'], 'http') === 0 ? $cliente['site'] : 'http://'.$cliente['site']; ?>" 
                       target="_blank" class="text-white text-decoration-none d-block mb-1">
                       <i class="fas fa-external-link-alt me-1"></i> <?php echo $cliente['site']; ?>
                    </a>
                <?php endif; ?>
                <small class="d-block">Atualizado em <strong><?php echo date('d/m/Y'); ?></strong></small>
            </div>
        </div>
    </div>
</div>

<div class="container" style="margin-top: 2rem;">

    <div class="card p-4 mb-4 shadow">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="t" value="<?php echo htmlspecialchars($token); ?>">
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold text-uppercase">Início</label>
                <input type="date" name="inicio" class="form-control bg-light border-0" value="<?php echo $inicio; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small fw-bold text-uppercase">Fim</label>
                <input type="date" name="fim" class="form-control bg-light border-0" value="<?php echo $fim; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted small fw-bold text-uppercase">Projeto</label>
                <select name="projeto" class="form-select bg-light border-0">
                    <option value="">Todos os Projetos</option>
                    <?php foreach($projetos_lista as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo ($filtro_proj == $p['id']) ? 'selected' : ''; ?>>
                            <?php echo $p['nome']; ?>
                        </option>
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
            <div class="card h-100 p-4 position-relative overflow-hidden border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center position-relative z-1">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold">Horas Trabalhadas</h6>
                        <div class="kpi-value text-primary"><?php echo fmtHoras($total_segundos_periodo); ?>h</div>
                        <small class="text-muted">Neste período</small>
                    </div>
                    <i class="fas fa-clock kpi-icon text-primary"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 p-4 position-relative overflow-hidden border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center position-relative z-1">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold">Entregas Realizadas</h6>
                        <div class="kpi-value text-success"><?php echo count($atividades); ?></div>
                        <small class="text-muted">Tarefas concluídas</small>
                    </div>
                    <i class="fas fa-check-circle kpi-icon text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 p-3 d-flex justify-content-center align-items-center">
                <?php if(count($dados_grafico) > 0): ?>
                    <div style="width: 100%; height: 90px;">
                        <canvas id="miniGrafico"></canvas>
                    </div>
                <?php else: ?>
                    <div class="text-muted small">Sem dados para gráfico</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-chart-pie me-2 text-primary"></i>Progresso dos Projetos</h6>
                </div>
                <div class="card-body">
                    <?php foreach($projetos_lista as $p): 
                        $usado = $p['segundos_totais'] ?? 0;
                        $horas_totais = $usado / 3600;
                        $meta = $p['horas_estimadas'];
                        $perc = 0; $corBarra = 'bg-primary';
                        if($meta > 0) {
                            $perc = ($horas_totais / $meta) * 100;
                            if($perc > 100) { $perc = 100; $corBarra = 'bg-danger'; }
                            elseif($perc > 80) { $corBarra = 'bg-warning'; }
                        }
                    ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-bold text-dark small"><?php echo $p['nome']; ?></span>
                            <span class="small fw-bold text-muted"><?php echo fmtHoras($usado); ?>h</span>
                        </div>
                        <?php if($meta > 0): ?>
                            <div class="progress progress-slim">
                                <div class="progress-bar <?php echo $corBarra; ?>" style="width: <?php echo $perc; ?>%"></div>
                            </div>
                            <div class="text-end mt-1"><small class="text-muted" style="font-size: 0.75rem;">Meta: <?php echo $meta; ?>h</small></div>
                        <?php else: ?>
                            <div class="progress progress-slim">
                                <div class="progress-bar bg-info" style="width: 100%"></div>
                            </div>
                            <div class="text-end mt-1"><small class="text-muted" style="font-size: 0.75rem;">Contínuo</small></div>
                        <?php endif; ?>
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
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-list-ul me-2 text-primary"></i>Histórico de Atividades</h6>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-portal table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4" style="width: 15%;">Data</th>
                                <th style="width: 65%;">Descrição</th>
                                <th class="text-end pe-4" style="width: 20%;">Duração</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($atividades as $a): ?>
                            <tr>
                                <td class="ps-4 text-secondary small fw-bold">
                                    <?php echo date('d/m/Y', strtotime($a['data_criacao'])); ?>
                                </td>
                                <td class="py-3">
                                    <?php if($a['nome_projeto']): ?>
                                        <span class="badge bg-light text-dark border mb-1"><?php echo $a['nome_projeto']; ?></span>
                                        <br>
                                    <?php endif; ?>
                                    <span class="texto-limitado d-inline-block text-dark" title="<?php echo htmlspecialchars($a['descricao']); ?>">
                                        <?php echo $a['descricao']; ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <span class="badge bg-primary bg-opacity-10 text-primary font-monospace px-3 py-2 rounded-pill">
                                        <?php echo fmtHoras($a['duracao']); ?> h
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($atividades)): ?>
                                <tr><td colspan="3" class="text-center py-5 text-muted">Nenhuma atividade encontrada neste período.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="text-center py-5 mt-4 text-muted small">
    <p class="mb-0">Portal de Transparência &bull; Powered by <strong>RatControl</strong></p>
</footer>

<script>
<?php if(count($dados_grafico) > 0): ?>
const ctx = document.getElementById('miniGrafico');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_keys($dados_grafico)); ?>,
        datasets: [{ label: 'Horas', data: <?php echo json_encode(array_values($dados_grafico)); ?>, backgroundColor: '#0d6efd', borderRadius: 4, barThickness: 10 }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { legend: { display: false } }, 
        scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
        layout: { padding: 0 }
    }
});
<?php endif; ?>
</script>
</body>
</html>