<?php
require 'config/db.php';
require_once 'includes/auth.php';

// Filtros
$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim    = $_GET['fim']    ?? date('Y-m-d');
$cliente_id  = $_GET['cliente'] ?? '';

// --- LÓGICA DE BUSCA ---
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
    // Nome do Cliente para o título
    $stmtCli = $pdo->prepare("SELECT nome FROM clientes WHERE id = :id");
    $stmtCli->execute([':id' => $cliente_id]);
    $nome_cliente_filtro = $stmtCli->fetchColumn();
} else {
    $nome_cliente_filtro = "Todos os Clientes";
}

$sql .= " ORDER BY t.data_criacao ASC"; // Ordem cronológica faz mais sentido em relatório

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$atividades = $stmt->fetchAll();

// Helper de Tempo
function formatarSegundos($segundos) {
    if (!$segundos) return '00:00:00';
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $segs = $segundos % 60;
    return sprintf('%02d:%02d:%02d', $horas, $minutos, $segs);
}

// Totais
$totais_financeiros = [];
$total_horas_geral = 0;

foreach ($atividades as $a) {
    $horas_decimais = $a['segundos_totais'] / 3600;
    $faturamento = $horas_decimais * $a['valor_hora'];
    $total_horas_geral += $a['segundos_totais'];
    
    if (!isset($totais_financeiros[$a['moeda']])) {
        $totais_financeiros[$a['moeda']] = 0;
    }
    $totais_financeiros[$a['moeda']] += $faturamento;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>RAT - <?php echo date('d/m/Y'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --cor-primaria: #0e2a47; /* Azul WeCare */
            --cor-fundo: #f8f9fa;
            --cor-borda: #dee2e6;
        }
        
        body { 
            font-family: 'Inter', sans-serif; 
            font-size: 11px; 
            color: #333; 
            margin: 0; 
            padding: 20px; 
            background: white; 
        }

        /* Botões de Ação (Não saem na impressão) */
        .no-print { 
            background: #333; 
            padding: 10px; 
            text-align: center; 
            position: fixed; 
            top: 0; left: 0; right: 0; 
            z-index: 999;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .btn-print { 
            background: #fff; 
            color: #333; 
            padding: 8px 15px; 
            text-decoration: none; 
            border-radius: 4px; 
            font-weight: bold; 
            margin: 0 10px;
        }
        
        /* Layout de Página A4 */
        .page {
            max-width: 210mm;
            margin: 60px auto 0; /* Espaço para barra topo */
            background: white;
        }

        /* Cabeçalho */
        .header { 
            display: flex; 
            justify-content: space-between; 
            border-bottom: 3px solid var(--cor-primaria); 
            padding-bottom: 15px; 
            margin-bottom: 20px; 
        }
        .header-title h1 { 
            margin: 0; 
            font-size: 24px; 
            color: var(--cor-primaria); 
            text-transform: uppercase; 
            letter-spacing: 1px;
        }
        .header-title p { margin: 2px 0 0; color: #666; font-size: 12px; }
        
        /* Caixa de Informações */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #f8f9fa;
            border: 1px solid #eee;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-item strong { display: block; color: #666; font-size: 10px; text-transform: uppercase; }
        .info-item span { font-size: 13px; font-weight: 600; color: #333; }

        /* Tabela */
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { 
            background-color: var(--cor-primaria); 
            color: white; 
            text-align: left; 
            padding: 10px 8px; 
            text-transform: uppercase; 
            font-size: 10px; 
        }
        td { 
            border-bottom: 1px solid #eee; 
            padding: 8px; 
            vertical-align: top;
        }
        tr:nth-child(even) { background-color: #fcfcfc; }

        /* Totais */
        .totais-box {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .total-card {
            background: var(--cor-primaria);
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-align: right;
        }
        .total-card small { display: block; opacity: 0.8; font-size: 10px; text-transform: uppercase; }
        .total-card .value { font-size: 18px; font-weight: bold; }

        /* Assinaturas */
        .assinaturas { 
            margin-top: 80px; 
            display: flex; 
            justify-content: space-between; 
            page-break-inside: avoid; 
        }
        .assinatura-line {
            width: 40%;
            border-top: 1px solid #999;
            text-align: center;
            padding-top: 10px;
        }
        .assinatura-line strong { display: block; font-size: 12px; }
        .assinatura-line span { color: #666; font-size: 10px; }

        /* Impressão */
        @media print {
            .no-print { display: none; }
            .page { margin: 0; max-width: 100%; }
            body { background: white; padding: 0; }
            table { font-size: 10px; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <a href="javascript:window.print()" class="btn-print">🖨️ Imprimir / Salvar PDF</a>
        <a href="javascript:window.close()" style="color: #ccc; margin-left: 10px; text-decoration: none;">Fechar</a>
    </div>

    <div class="page">
        <div class="header">
            <div class="header-title">
                <h1>Relatório Técnico</h1>
                <p>RAT - Registro de Atividades</p>
            </div>
            <div style="text-align: right;">
                <img src="https://ui-avatars.com/api/?name=WeCare&background=0e2a47&color=fff" style="height: 40px; border-radius: 4px;">
                <div style="font-size: 10px; color: #999; margin-top: 5px;">WeCare Consultoria</div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <strong>Cliente</strong>
                <span><?php echo $nome_cliente_filtro; ?></span>
                
                <div style="margin-top: 10px;">
                    <strong>Consultor Responsável</strong>
                    <span><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></span>
                </div>
            </div>
            <div class="info-item" style="text-align: right;">
                <strong>Período</strong>
                <span><?php echo date('d/m/Y', strtotime($data_inicio)); ?> a <?php echo date('d/m/Y', strtotime($data_fim)); ?></span>
                
                <div style="margin-top: 10px;">
                    <strong>Data de Emissão</strong>
                    <span><?php echo date('d/m/Y \à\s H:i'); ?></span>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">Data</th>
                    <th>Projeto / Atividade</th>
                    <th style="width: 150px;">Consultor</th>
                    <th style="text-align: right; width: 80px;">Duração</th>
                    <th style="text-align: right; width: 100px;">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($atividades as $a): 
                    $horas = $a['segundos_totais'] / 3600;
                    $total = $horas * $a['valor_hora'];
                    
                    // Descrição com Projeto
                    $desc = nl2br(htmlspecialchars($a['descricao']));
                    if($a['nome_projeto']) {
                        $desc = "<strong style='color:var(--cor-primaria)'>[{$a['nome_projeto']}]</strong><br>" . $desc;
                    }
                ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($a['data_criacao'])); ?></td>
                    <td><?php echo $desc; ?></td>
                    <td><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></td>
                    <td style="text-align: right; font-weight: bold;"><?php echo formatarSegundos($a['segundos_totais']); ?></td>
                    <td style="text-align: right;">
                        <?php echo $a['moeda'] . ' ' . number_format($total, 2, ',', '.'); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(empty($atividades)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px; color: #999;">Nenhuma atividade encontrada neste período.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totais-box">
            <div class="total-card">
                <small>Tempo Total</small>
                <div class="value" style="margin-bottom: 5px;"><?php echo formatarSegundos($total_horas_geral); ?></div>
                
                <hr style="border-color: rgba(255,255,255,0.2); margin: 5px 0;">
                
                <?php foreach($totais_financeiros as $moeda => $valor): ?>
                    <small>Total (<?php echo $moeda; ?>)</small>
                    <div class="value"><?php echo number_format($valor, 2, ',', '.'); ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="assinaturas">
            <div class="assinatura-line">
                <strong>WeCare Consultoria</strong>
                <span>Prestador de Serviço</span>
            </div>
            <div class="assinatura-line">
                <strong><?php echo $nome_cliente_filtro; ?></strong>
                <span>Aceite / Cliente</span>
            </div>
        </div>
    </div>

</body>
</html>