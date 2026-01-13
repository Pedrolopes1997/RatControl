<?php
require 'config/db.php';
require_once 'includes/auth.php';

$usuario_id = $_SESSION['usuario_id'];
$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim    = $_GET['fim']    ?? date('Y-m-d');
$cliente_id  = $_GET['cliente'] ?? '';

// --- 0. LÓGICA DE EXCLUSÃO ---
if (isset($_GET['excluir_tarefa'])) {
    $id_del = filter_input(INPUT_GET, 'excluir_tarefa', FILTER_VALIDATE_INT);
    if ($id_del) {
        $stmtDel = $pdo->prepare("DELETE FROM tarefas WHERE id = :id AND usuario_id = :uid");
        $stmtDel->execute([':id' => $id_del, ':uid' => $usuario_id]);
        $pdo->prepare("DELETE FROM tempo_logs WHERE tarefa_id = :id")->execute([':id' => $id_del]); // Limpa logs
        
        $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Atividade excluída com sucesso.'];
        
        $qs = http_build_query(['inicio'=>$data_inicio, 'fim'=>$data_fim, 'cliente'=>$cliente_id]);
        header("Location: relatorios.php?$qs"); exit;
    }
}

// --- 1. EXPORTAÇÃO EXCEL ---
if (isset($_GET['exportar']) && $_GET['exportar'] == 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_' . date('Ymd') . '.csv');
    $output = fopen('php://output', 'w');
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['Data', 'Cliente', 'Projeto', 'Descrição', 'Horas', 'Valor Hora', 'Total', 'Status'], ';');

    $sqlEx = "SELECT t.*, c.nome as nome_cliente, p.nome as nome_projeto, c.valor_hora, c.moeda,
              (SELECT SUM(TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW()))) FROM tempo_logs tl WHERE tl.tarefa_id = t.id) as segundos
              FROM tarefas t
              JOIN clientes c ON t.cliente_id = c.id
              LEFT JOIN projetos p ON t.projeto_id = p.id
              WHERE t.usuario_id = :uid AND DATE(t.data_criacao) BETWEEN :inicio AND :fim";
    
    $paramsEx = [':uid' => $usuario_id, ':inicio' => $data_inicio, ':fim' => $data_fim];
    if(!empty($cliente_id)) { $sqlEx .= " AND t.cliente_id = :cid"; $paramsEx[':cid'] = $cliente_id; }
    
    $stmtEx = $pdo->prepare($sqlEx);
    $stmtEx->execute($paramsEx);
    
    while($row = $stmtEx->fetch(PDO::FETCH_ASSOC)) {
        $horasDec = $row['segundos'] / 3600;
        $valorTotal = $horasDec * $row['valor_hora'];
        fputcsv($output, [
            date('d/m/Y', strtotime($row['data_criacao'])),
            $row['nome_cliente'],
            $row['nome_projeto'] ?? '-',
            $row['descricao'],
            number_format($horasDec, 2, ',', '.'),
            $row['moeda'] . ' ' . number_format($row['valor_hora'], 2, ',', '.'),
            $row['moeda'] . ' ' . number_format($valorTotal, 2, ',', '.'),
            ucfirst($row['status_pagamento'])
        ], ';');
    }
    fclose($output); exit;
}

require 'includes/header.php';

// --- 2. CONSULTAS ---
$sql = "SELECT t.*, c.nome as nome_cliente, c.valor_hora, c.moeda, p.nome as nome_projeto,
        (SELECT SUM(TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW()))) 
         FROM tempo_logs tl WHERE tl.tarefa_id = t.id) as segundos_totais
        FROM tarefas t
        JOIN clientes c ON t.cliente_id = c.id
        LEFT JOIN projetos p ON t.projeto_id = p.id
        WHERE t.usuario_id = :uid AND DATE(t.data_criacao) BETWEEN :inicio AND :fim";

$params = [':uid' => $usuario_id, ':inicio' => $data_inicio, ':fim' => $data_fim];
if (!empty($cliente_id)) { $sql .= " AND t.cliente_id = :cid"; $params[':cid'] = $cliente_id; }
$sql .= " ORDER BY t.data_criacao DESC";
$atividades = $pdo->prepare($sql);
$atividades->execute($params);
$atividades = $atividades->fetchAll();

$sqlDesp = "SELECT d.*, c.moeda, p.nome as nome_projeto 
            FROM despesas d 
            JOIN clientes c ON d.cliente_id = c.id 
            LEFT JOIN projetos p ON d.projeto_id = p.id
            WHERE d.usuario_id = :uid AND d.data_despesa BETWEEN :inicio AND :fim";
$paramsDesp = [':uid' => $usuario_id, ':inicio' => $data_inicio, ':fim' => $data_fim];
if (!empty($cliente_id)) { $sqlDesp .= " AND d.cliente_id = :cid"; $paramsDesp[':cid'] = $cliente_id; }
$stmtDesp = $pdo->prepare($sqlDesp);
$stmtDesp->execute($paramsDesp);
$despesas_lista = $stmtDesp->fetchAll();

$lista_clientes = $pdo->query("SELECT * FROM clientes WHERE status='ativo' ORDER BY nome")->fetchAll();

// --- 3. KPIS ---
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

function fmtSeg($s) { if(!$s) return '00:00'; return sprintf('%02d:%02d', floor($s/3600), floor(($s%3600)/60)); }
?>

<style>
    /* FILTROS */
    .filter-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 15px rgba(0,0,0,0.03); }
    
    /* CARDS KPI */
    .kpi-card { 
        background: #fff; border-radius: 12px; padding: 25px; 
        box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05);
        transition: transform 0.2s; position: relative; overflow: hidden;
    }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
    .kpi-card::after { content:''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #ccc; }
    .kpi-success::after { background: #198754; }
    .kpi-danger::after { background: #dc3545; }
    
    /* ABAS CUSTOMIZADAS (CORREÇÃO DE COR) */
    .nav-pills-custom .nav-link { 
        border-radius: 50px; padding: 8px 25px; font-weight: 600; color: #6c757d; margin-right: 10px; transition: all 0.3s; background: #fff;
    }
    
    /* Aba Receitas Ativa (Azul) */
    .nav-pills-custom .nav-link#pills-receitas-tab.active { 
        background-color: #0e2a47; 
        color: #ffffff !important; /* Força branco */
        box-shadow: 0 4px 10px rgba(14, 42, 71, 0.3); 
    }
    
    /* Aba Despesas Ativa (Vermelho) */
    .nav-pills-custom .nav-link#pills-despesas-tab.active { 
        background-color: #dc3545; 
        color: #ffffff !important; /* Força branco */
        box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3); 
    }

    /* TABELA */
    .table-custom th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #8898aa; border-bottom: 1px solid #f0f0f0; padding-bottom: 15px; }
    .table-custom td { padding: 15px 10px; border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; }
    .table-custom tr:last-child td { border-bottom: none; }
    
    .btn-icon { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.2s; }
    .btn-icon:hover { background-color: #f0f0f0; transform: scale(1.1); }
    
    @media print {
        .no-print { display: none !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
    }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-end mb-4 no-print gap-3">
    <div>
        <h2 class="fw-bold text-dark mb-1">Relatórios</h2>
        <p class="text-muted mb-0">Visão financeira e analítica.</p>
    </div>
    
    <div class="d-flex gap-2">
        <?php $qs = http_build_query(['inicio'=>$data_inicio, 'fim'=>$data_fim, 'cliente'=>$cliente_id]); ?>
        
        <a href="?exportar=excel&<?php echo $qs; ?>" class="btn btn-success text-white fw-bold shadow-sm rounded-pill px-3">
            <i class="fas fa-file-excel me-2"></i> Excel
        </a>
        
        <?php if($cliente_id): ?>
            <a href="gerar_invoice.php?<?php echo $qs; ?>" target="_blank" class="btn btn-dark fw-bold shadow-sm rounded-pill px-3">
                <i class="fas fa-file-invoice-dollar me-2"></i> Fatura
            </a>
        <?php endif; ?>

        <a href="imprimir_relatorio.php?<?php echo $qs; ?>" target="_blank" class="btn btn-outline-secondary fw-bold shadow-sm rounded-pill px-3">
            <i class="fas fa-print me-2"></i> RAT
        </a>
    </div>
</div>

<div class="filter-card mb-4 no-print">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="small fw-bold text-muted text-uppercase mb-1">Data Início</label>
            <input type="date" name="inicio" class="form-control fw-bold border-light bg-light" value="<?php echo $data_inicio; ?>">
        </div>
        <div class="col-md-3">
            <label class="small fw-bold text-muted text-uppercase mb-1">Data Fim</label>
            <input type="date" name="fim" class="form-control fw-bold border-light bg-light" value="<?php echo $data_fim; ?>">
        </div>
        <div class="col-md-4">
            <label class="small fw-bold text-muted text-uppercase mb-1">Cliente</label>
            <select name="cliente" class="form-select fw-bold border-light bg-light">
                <option value="">Todos os Clientes</option>
                <?php foreach($lista_clientes as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($cliente_id==$c['id']?'selected':''); ?>><?php echo $c['nome']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">
                <i class="fas fa-filter me-2"></i> Filtrar
            </button>
        </div>
    </form>
</div>

<div class="row g-3 mb-5">
    <?php if(empty($balanco)): ?>
        <div class="col-12"><div class="alert alert-light border text-center text-muted py-4 rounded-3"><i class="fas fa-search me-2"></i> Nenhuma movimentação encontrada.</div></div>
    <?php endif; ?>

    <?php foreach($balanco as $moeda => $v): 
        $lucro = $v['receita'] - $v['despesa'];
        $classe = $lucro >= 0 ? 'kpi-success' : 'kpi-danger';
        $corTexto = $lucro >= 0 ? 'text-success' : 'text-danger';
    ?>
    <div class="col-md-4">
        <div class="kpi-card <?php echo $classe; ?>">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="badge bg-dark rounded-pill px-3"><?php echo $moeda; ?></span>
                <span class="small text-muted fw-bold text-uppercase ls-1">Saldo Líquido</span>
            </div>
            <h2 class="fw-bold mb-3 <?php echo $corTexto; ?>"><?php echo number_format($lucro, 2, ',', '.'); ?></h2>
            <div class="d-flex justify-content-between small pt-3 border-top border-light">
                <div class="text-success fw-bold"><i class="fas fa-arrow-up me-1"></i> <?php echo number_format($v['receita'], 2, ',', '.'); ?></div>
                <div class="text-danger fw-bold"><i class="fas fa-arrow-down me-1"></i> <?php echo number_format($v['despesa'], 2, ',', '.'); ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="mb-4 no-print">
    <ul class="nav nav-pills nav-pills-custom" id="pills-tab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="pills-receitas-tab" data-bs-toggle="pill" data-bs-target="#pills-receitas" type="button">
                <i class="fas fa-clock me-2"></i> Receitas & Horas
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="pills-despesas-tab" data-bs-toggle="pill" data-bs-target="#pills-despesas" type="button">
                <i class="fas fa-receipt me-2"></i> Despesas
            </button>
        </li>
    </ul>
</div>

<div class="tab-content" id="pills-tabContent">
    
    <div class="tab-pane fade show active" id="pills-receitas">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-custom align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Data</th>
                                <th>Cliente / Projeto</th>
                                <th>Atividade</th>
                                <th class="text-center">Status Pagamento</th>
                                <th class="text-end">Tempo</th>
                                <th class="text-end pe-4">Valor Estimado</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($atividades as $a): 
                                $val = ($a['segundos_totais'] / 3600) * $a['valor_hora'];
                                
                                // Status
                                $st = $a['status_pagamento'];
                                $btnStyle = match($st) {
                                    'faturado' => 'background:#e3f2fd; color:#0c5460; border:1px solid #b6d4fe;',
                                    'pago' => 'background:#d1e7dd; color:#0f5132; border:1px solid #badbcc;',
                                    default => 'background:#fff3cd; color:#856404; border:1px solid #ffecb5;'
                                };
                                $iconStatus = match($st) { 'faturado'=>'fa-file-invoice', 'pago'=>'fa-check-circle', default=>'fa-clock' };
                            ?>
                            <tr>
                                <td class="ps-4 text-muted font-monospace small"><?php echo date('d/m', strtotime($a['data_criacao'])); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo $a['nome_cliente']; ?></div>
                                    <small class="text-muted"><?php echo $a['nome_projeto'] ?: 'Avulso'; ?></small>
                                </td>
                                <td class="text-truncate" style="max-width: 280px; color: #555;">
                                    <?php echo htmlspecialchars($a['descricao']); ?>
                                </td>
                                
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm rounded-pill fw-bold px-3 no-print" type="button" data-bs-toggle="dropdown" style="<?php echo $btnStyle; ?>" id="btn-status-<?php echo $a['id']; ?>">
                                            <i class="fas <?php echo $iconStatus; ?> me-1"></i> <?php echo ucfirst($st); ?>
                                        </button>
                                        <span class="d-none d-print-inline fw-bold"><?php echo ucfirst($st); ?></span>
                                        
                                        <ul class="dropdown-menu shadow border-0">
                                            <li><h6 class="dropdown-header">Alterar para:</h6></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="mudarStatus(<?php echo $a['id']; ?>, 'pendente')">🕒 Pendente</a></li>
                                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="mudarStatus(<?php echo $a['id']; ?>, 'faturado')">📄 Faturado</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item fw-bold text-success" href="javascript:void(0)" onclick="mudarStatus(<?php echo $a['id']; ?>, 'pago')">✅ PAGO</a></li>
                                        </ul>
                                    </div>
                                </td>

                                <td class="text-end font-monospace text-secondary fw-bold"><?php echo fmtSeg($a['segundos_totais']); ?></td>
                                <td class="text-end fw-bold text-success pe-4">
                                    <?php echo $a['moeda'] . ' ' . number_format($val, 2, ',', '.'); ?>
                                </td>
                                
                                <td class="text-end pe-3">
                                    <a href="editar_tarefa.php?id=<?php echo $a['id']; ?>" class="btn-icon text-primary me-1" title="Editar"><i class="fas fa-pencil-alt"></i></a>
                                    
                                    <?php $linkDel = "?excluir_tarefa={$a['id']}&inicio=$data_inicio&fim=$data_fim&cliente=$cliente_id"; ?>
                                    <a href="<?php echo $linkDel; ?>" class="btn-icon text-danger" title="Excluir Definitivamente" onclick="return confirm('Tem certeza absoluta? Isso apagará a tarefa e todos os logs de tempo associados.');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($atividades)) echo '<tr><td colspan="7" class="text-center py-5 text-muted">Nenhuma atividade encontrada.</td></tr>'; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="pills-despesas">
        <div class="card shadow-sm border-0 border-start border-danger border-3">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-custom align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Data</th>
                            <th>Cliente / Projeto</th>
                            <th>Descrição do Custo</th>
                            <th class="text-center">Comp.</th>
                            <th class="text-end pe-4">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($despesas_lista as $d): ?>
                        <tr>
                            <td class="ps-4 text-muted font-monospace small"><?php echo date('d/m', strtotime($d['data_despesa'])); ?></td>
                            <td>
                                <div class="fw-bold text-dark">
                                    <?php 
                                        $key = array_search($d['cliente_id'], array_column($lista_clientes, 'id'));
                                        echo ($key !== false) ? $lista_clientes[$key]['nome'] : '-';
                                    ?>
                                </div>
                                <small class="text-muted"><?php echo $d['nome_projeto'] ?? '-'; ?></small>
                            </td>
                            <td style="color: #555;"><?php echo $d['descricao']; ?></td>
                            
                            <td class="text-center">
                                <?php if(!empty($d['comprovante'])): ?>
                                    <a href="<?php echo $d['comprovante']; ?>" target="_blank" class="btn btn-sm btn-light border text-primary" title="Ver Arquivo"><i class="fas fa-paperclip"></i></a>
                                <?php else: ?>
                                    <span class="text-muted opacity-25">-</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end fw-bold text-danger pe-4">
                                - <?php echo $d['moeda'] . ' ' . number_format($d['valor'], 2, ',', '.'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($despesas_lista)) echo '<tr><td colspan="5" class="text-center py-5 text-muted">Nenhuma despesa encontrada.</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
function mudarStatus(id, novoStatus) {
    const btn = document.getElementById('btn-status-' + id);
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.classList.add('disabled');

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao: 'atualizar_status_pagamento', id: id, status: novoStatus })
    })
    .then(r => r.json())
    .then(data => {
        if(data.sucesso) window.location.reload(); 
        else { alert('Erro: ' + data.msg); btn.innerHTML = originalHtml; btn.classList.remove('disabled'); }
    });
}
</script>

<?php require 'includes/footer.php'; ?>