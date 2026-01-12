<?php
require 'config/db.php';
require_once 'includes/auth.php';

$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim    = $_GET['fim']    ?? date('Y-m-d');
$cliente_id  = $_GET['cliente'] ?? '';

// --- 1. EXPORTAÇÃO EXCEL ---
if (isset($_GET['exportar']) && $_GET['exportar'] == 'excel') {
    // Define headers para download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_financeiro.csv');
    $output = fopen('php://output', 'w');
    
    // Cabeçalho do CSV (BOM para Excel reconhecer UTF-8)
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['Data', 'Cliente', 'Projeto', 'Descrição', 'Tempo', 'Status', 'Valor (Estimado)'], ';');

    // Busca dados para exportação
    $sqlEx = "SELECT t.*, c.nome as nome_cliente, p.nome as nome_projeto, c.valor_hora,
              (SELECT SUM(TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW()))) FROM tempo_logs tl WHERE tl.tarefa_id = t.id) as segundos
              FROM tarefas t
              JOIN clientes c ON t.cliente_id = c.id
              LEFT JOIN projetos p ON t.projeto_id = p.id
              WHERE DATE(t.data_criacao) BETWEEN :inicio AND :fim";
    $paramsEx = [':inicio' => $data_inicio, ':fim' => $data_fim];
    if(!empty($cliente_id)) { $sqlEx .= " AND t.cliente_id = :cid"; $paramsEx[':cid'] = $cliente_id; }
    
    $stmtEx = $pdo->prepare($sqlEx);
    $stmtEx->execute($paramsEx);
    
    while($row = $stmtEx->fetch(PDO::FETCH_ASSOC)) {
        $horas = number_format($row['segundos'] / 3600, 2, ',', '.');
        $valor = ($row['segundos'] / 3600) * $row['valor_hora'];
        $valorFmt = number_format($valor, 2, ',', '.');
        
        fputcsv($output, [
            date('d/m/Y', strtotime($row['data_criacao'])),
            $row['nome_cliente'],
            $row['nome_projeto'] ?? '-',
            $row['descricao'],
            $horas,
            ucfirst($row['status_pagamento']),
            $valorFmt
        ], ';');
    }
    fclose($output);
    exit;
}

require 'includes/header.php';

// --- 2. CONSULTAS PARA A TELA ---
// Receitas (Horas)
$sql = "SELECT t.*, c.nome as nome_cliente, c.valor_hora, c.moeda, p.nome as nome_projeto,
        (SELECT SUM(TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW()))) 
         FROM tempo_logs tl WHERE tl.tarefa_id = t.id) as segundos_totais
        FROM tarefas t
        JOIN clientes c ON t.cliente_id = c.id
        LEFT JOIN projetos p ON t.projeto_id = p.id
        WHERE DATE(t.data_criacao) BETWEEN :inicio AND :fim";

$params = [':inicio' => $data_inicio, ':fim' => $data_fim];
if (!empty($cliente_id)) {
    $sql .= " AND t.cliente_id = :cid";
    $params[':cid'] = $cliente_id;
}
$sql .= " ORDER BY t.data_criacao DESC";
$atividades = $pdo->prepare($sql);
$atividades->execute($params);
$atividades = $atividades->fetchAll();

// Despesas
$sqlDesp = "SELECT d.*, c.moeda, p.nome as nome_projeto 
            FROM despesas d 
            JOIN clientes c ON d.cliente_id = c.id
            LEFT JOIN projetos p ON d.projeto_id = p.id
            WHERE d.data_despesa BETWEEN :inicio AND :fim";
$paramsDesp = [':inicio' => $data_inicio, ':fim' => $data_fim];
if (!empty($cliente_id)) {
    $sqlDesp .= " AND d.cliente_id = :cid";
    $paramsDesp[':cid'] = $cliente_id;
}
$stmtDesp = $pdo->prepare($sqlDesp);
$stmtDesp->execute($paramsDesp);
$despesas_lista = $stmtDesp->fetchAll();

// Cálculo de Totais (KPIs)
$balanco = []; 
foreach ($atividades as $a) {
    $val = ($a['segundos_totais'] / 3600) * $a['valor_hora'];
    if (!isset($balanco[$a['moeda']])) $balanco[$a['moeda']] = ['receita'=>0, 'despesa'=>0];
    $balanco[$a['moeda']]['receita'] += $val;
}
foreach ($despesas_lista as $d) {
    if (!isset($balanco[$d['moeda']])) $balanco[$d['moeda']] = ['receita'=>0, 'despesa'=>0];
    $balanco[$d['moeda']]['despesa'] += $d['valor'];
}

function fmtSeg($s) { 
    if(!$s) return '00:00'; 
    return sprintf('%02d:%02d', floor($s/3600), floor(($s%3600)/60)); 
}

$lista_clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome")->fetchAll();
?>

<style>
    .nav-pills-custom {
        background-color: #e9ecef;
        padding: 0.3rem;
        border-radius: 10px;
        display: inline-flex;
    }
    .nav-pills-custom .nav-link {
        color: #6c757d;
        font-weight: 600;
        border-radius: 8px;
        padding: 0.5rem 1.2rem;
        transition: all 0.2s;
    }
    .nav-pills-custom .nav-link.active {
        background-color: #fff;
        color: var(--primary-color);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .nav-pills-custom .nav-link.active.text-danger {
        color: #dc3545 !important;
    }
    .card-kpi {
        border-left: 5px solid transparent;
    }
    .kpi-success { border-left-color: #198754; }
    .kpi-danger { border-left-color: #dc3545; }
    
    /* Modo Impressão */
    @media print {
        body { background: white; }
        .no-print, .navbar, form { display: none !important; }
        .card { border: none !important; box-shadow: none !important; border: 1px solid #ddd !important; }
        .table { width: 100% !important; border-collapse: collapse; }
        th, td { border: 1px solid #ddd !important; }
        .badge { border: 1px solid #000; color: #000 !important; }
    }
</style>

<div class="row align-items-center mb-4 no-print">
    <div class="col-md-6">
        <h3 class="fw-bold mb-0 text-dark">Financeiro</h3>
        <p class="text-muted small mb-0">Período: <?php echo date('d/m/Y', strtotime($data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($data_fim)); ?></p>
    </div>
    <div class="col-md-6 text-end">
        <div class="d-flex justify-content-end gap-2">
            <a href="?exportar=excel&inicio=<?php echo $data_inicio; ?>&fim=<?php echo $data_fim; ?>&cliente=<?php echo $cliente_id; ?>" 
               class="btn btn-success text-white shadow-sm">
               <i class="fas fa-file-excel me-1"></i> Excel
            </a>
            
            <a href="gerar_invoice.php?inicio=<?php echo $data_inicio; ?>&fim=<?php echo $data_fim; ?>&cliente=<?php echo $cliente_id; ?>" 
               target="_blank" class="btn btn-dark shadow-sm">
               <i class="fas fa-file-invoice-dollar me-1"></i> Invoice
            </a>

            <button onclick="window.print()" class="btn btn-outline-secondary shadow-sm">
                <i class="fas fa-print me-1"></i> Imprimir/PDF
            </button>
        </div>
    </div>
</div>

<div class="card mb-4 no-print border-0 shadow-sm">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-muted">Início</label>
                <input type="date" name="inicio" class="form-control form-control-sm" value="<?php echo $data_inicio; ?>">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted">Fim</label>
                <input type="date" name="fim" class="form-control form-control-sm" value="<?php echo $data_fim; ?>">
            </div>
            <div class="col-md-4">
                <label class="small fw-bold text-muted">Cliente</label>
                <select name="cliente" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach($lista_clientes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($cliente_id==$c['id']?'selected':''); ?>><?php echo $c['nome']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Atualizar</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php if(empty($balanco)): ?>
        <div class="col-12"><div class="alert alert-light border text-center text-muted">Sem movimentação financeira no período selecionado.</div></div>
    <?php endif; ?>

    <?php foreach($balanco as $moeda => $v): 
        $lucro = $v['receita'] - $v['despesa'];
        $classe = $lucro >= 0 ? 'kpi-success' : 'kpi-danger';
    ?>
    <div class="col-md-4">
        <div class="card card-kpi <?php echo $classe; ?> shadow-sm h-100">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-dark text-white"><?php echo $moeda; ?></span>
                    <span class="small text-muted fw-bold text-uppercase">Resultado Líquido</span>
                </div>
                <h3 class="fw-bold mb-3 <?php echo $lucro>=0?'text-success':'text-danger'; ?>">
                    <?php echo number_format($lucro, 2, ',', '.'); ?>
                </h3>
                
                <div class="d-flex justify-content-between small border-top pt-2">
                    <span class="text-success"><i class="fas fa-arrow-up"></i> <?php echo number_format($v['receita'], 2, ',', '.'); ?></span>
                    <span class="text-danger"><i class="fas fa-arrow-down"></i> <?php echo number_format($v['despesa'], 2, ',', '.'); ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="mb-3 no-print">
    <div class="nav nav-pills-custom" id="v-pills-tab" role="tablist">
        <button class="nav-link active" id="tab-receitas" data-bs-toggle="pill" data-bs-target="#receitas" type="button">
            <i class="fas fa-clock me-1"></i> Receitas & Horas
        </button>
        <button class="nav-link text-danger" id="tab-despesas" data-bs-toggle="pill" data-bs-target="#despesas" type="button">
            <i class="fas fa-receipt me-1"></i> Despesas
        </button>
    </div>
</div>

<div class="tab-content" id="v-pills-tabContent">
    
    <div class="tab-pane fade show active" id="receitas">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary">
                        <tr>
                            <th class="ps-4">Data</th>
                            <th>Cliente / Projeto</th>
                            <th>Atividade</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Tempo</th>
                            <th class="text-end pe-4">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($atividades as $a): 
                            $val = ($a['segundos_totais'] / 3600) * $a['valor_hora'];
                            
                            // Lógica de Cores do Botão Status
                            $st = $a['status_pagamento'];
                            $btnClass = 'btn-warning'; // Amarelo (Pendente)
                            $icone = 'fa-clock';
                            
                            if($st == 'faturado') { $btnClass = 'btn-info text-white'; $icone = 'fa-file-invoice'; }
                            if($st == 'pago') { $btnClass = 'btn-success text-white'; $icone = 'fa-check-circle'; }
                        ?>
                        <tr>
                            <td class="ps-4 text-muted"><?php echo date('d/m', strtotime($a['data_criacao'])); ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo $a['nome_cliente']; ?></div>
                                <?php if($a['nome_projeto']): ?>
                                    <small class="badge bg-light text-secondary border"><?php echo $a['nome_projeto']; ?></small>
                                <?php endif; ?>
                            </td>
                            
                            <td class="texto-limitado" style="max-width: 300px;" title="<?php echo htmlspecialchars($a['descricao']); ?>">
                                <?php echo $a['descricao']; ?>
                            </td>

                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn <?php echo $btnClass; ?> btn-sm dropdown-toggle rounded-pill px-3 fw-bold no-print" 
                                            type="button" data-bs-toggle="dropdown" aria-expanded="false" 
                                            id="btn-status-<?php echo $a['id']; ?>">
                                        <i class="fas <?php echo $icone; ?> me-1"></i> <?php echo ucfirst($st); ?>
                                    </button>
                                    
                                    <span class="d-none d-print-inline fw-bold"><?php echo ucfirst($st); ?></span>

                                    <ul class="dropdown-menu shadow border-0">
                                        <li><h6 class="dropdown-header">Alterar Status:</h6></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="alterarStatus(<?php echo $a['id']; ?>, 'pendente')">
                                            <i class="fas fa-clock text-warning me-2"></i> Pendente
                                        </a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" onclick="alterarStatus(<?php echo $a['id']; ?>, 'faturado')">
                                            <i class="fas fa-file-invoice text-info me-2"></i> Faturado
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item fw-bold text-success" href="javascript:void(0)" onclick="alterarStatus(<?php echo $a['id']; ?>, 'pago')">
                                            <i class="fas fa-check-circle me-2"></i> PAGO
                                        </a></li>
                                    </ul>
                                </div>
                            </td>

                            <td class="text-end font-monospace text-secondary">
                                <?php echo fmtSeg($a['segundos_totais']); ?>
                            </td>
                            <td class="text-end fw-bold text-success pe-4">
                                <?php echo $a['moeda'] . ' ' . number_format($val, 2, ',', '.'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($atividades)) echo '<tr><td colspan="6" class="text-center py-5 text-muted">Nenhum registro encontrado.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="despesas">
        <div class="card shadow-sm border-0 border-start border-danger border-3">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary">
                        <tr>
                            <th class="ps-4">Data</th>
                            <th>Cliente</th>
                            <th>Projeto</th>
                            <th>Descrição</th>
                            <th class="text-end pe-4">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($despesas_lista as $d): ?>
                        <tr>
                            <td class="ps-4 text-muted"><?php echo date('d/m/Y', strtotime($d['data_despesa'])); ?></td>
                            <td class="fw-bold"><?php echo $lista_clientes[array_search($d['cliente_id'], array_column($lista_clientes, 'id'))]['nome']; ?></td>
                            <td><?php echo $d['nome_projeto'] ?? '-'; ?></td>
                            <td class="texto-limitado" style="max-width: 350px;" title="<?php echo htmlspecialchars($d['descricao']); ?>">
                                <?php echo $d['descricao']; ?>
                            </td>
                            <td class="text-end fw-bold text-danger pe-4">
                                - <?php echo $d['moeda'] . ' ' . number_format($d['valor'], 2, ',', '.'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($despesas_lista)) echo '<tr><td colspan="5" class="text-center py-5 text-muted">Nenhuma despesa lançada.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function alterarStatus(id, novoStatus) {
    const btn = document.getElementById('btn-status-' + id);
    const textoOriginal = btn.innerHTML;
    
    // Feedback de carregamento
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.classList.add('disabled');

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao: 'atualizar_status_pagamento', id: id, status: novoStatus })
    })
    .then(r => r.json())
    .then(data => {
        if(data.sucesso) {
            // Recarrega a página para atualizar os TOTAIS lá em cima e a cor do botão
            window.location.reload();
        } else {
            alert('Erro: ' + (data.msg || 'Falha ao atualizar'));
            btn.innerHTML = textoOriginal;
            btn.classList.remove('disabled');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Erro de conexão');
        btn.innerHTML = textoOriginal;
        btn.classList.remove('disabled');
    });
}
</script>

<?php require 'includes/footer.php'; ?>