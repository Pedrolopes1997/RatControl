<?php
require 'config/db.php';
require_once 'includes/auth.php';

// Recebe os mesmos filtros da página anterior
$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim = $_GET['fim'] ?? date('Y-m-d');
$cliente_id = $_GET['cliente'] ?? '';

// --- LÓGICA DE BUSCA (A mesma do relatorios.php) ---
$sql = "SELECT t.*, c.nome as nome_cliente, c.valor_hora, c.moeda,
        (SELECT SUM(TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW()))) 
         FROM tempo_logs tl WHERE tl.tarefa_id = t.id) as segundos_totais
        FROM tarefas t
        JOIN clientes c ON t.cliente_id = c.id
        WHERE DATE(t.data_criacao) BETWEEN :inicio AND :fim";

$params = [':inicio' => $data_inicio, ':fim' => $data_fim];

if (!empty($cliente_id)) {
    $sql .= " AND t.cliente_id = :cid";
    $params[':cid'] = $cliente_id;
    
    // Busca nome do cliente para o título
    $stmtCli = $pdo->prepare("SELECT nome FROM clientes WHERE id = :id");
    $stmtCli->execute([':id' => $cliente_id]);
    $nome_cliente_filtro = $stmtCli->fetchColumn();
} else {
    $nome_cliente_filtro = "Todos os Clientes";
}

$sql .= " ORDER BY t.data_criacao DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$atividades = $stmt->fetchAll();

// Função auxiliar
function formatarSegundos($segundos) {
    if (!$segundos) return '00:00:00';
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $segs = $segundos % 60;
    return sprintf('%02d:%02d:%02d', $horas, $minutos, $segs);
}

// Totais Financeiros
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
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; }
        .header p { margin: 5px 0 0; color: #666; }
        
        .info-box { display: flex; justify-content: space-between; margin-bottom: 20px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #eee; text-transform: uppercase; font-size: 11px; }
        
        .totais { text-align: right; margin-top: 20px; font-size: 14px; }
        .totais strong { display: block; margin-bottom: 5px; }
        
        .assinaturas { margin-top: 60px; display: flex; justify-content: space-between; page-break-inside: avoid; }
        .assinatura-box { width: 40%; border-top: 1px solid #000; text-align: center; padding-top: 10px; }
        
        /* Oculta botão de imprimir na hora da impressão */
        @media print {
            .no-print { display: none; }
        }
        .btn-print { padding: 10px 20px; background: #333; color: #fff; text-decoration: none; border-radius: 5px; display: inline-block; margin-bottom: 20px; }
    </style>
</head>
<body>

    <div class="no-print" style="text-align: center;">
        <a href="javascript:window.print()" class="btn-print">🖨️ Salvar como PDF / Imprimir</a>
        <a href="javascript:window.close()" style="margin-left: 20px; color: #666;">Fechar</a>
    </div>

    <div class="header">
        <h1>RatControl</h1>
        <p>Relatório de Atividade Técnica (RAT)</p>
    </div>

    <div class="info-box">
        <div>
            <strong>Consultor:</strong> <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?><br>
            <strong>Data Emissão:</strong> <?php echo date('d/m/Y H:i'); ?>
        </div>
        <div style="text-align: right;">
            <strong>Cliente:</strong> <?php echo $nome_cliente_filtro; ?><br>
            <strong>Período:</strong> <?php echo date('d/m/Y', strtotime($data_inicio)); ?> até <?php echo date('d/m/Y', strtotime($data_fim)); ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Cliente</th>
                <th style="width: 40%;">Descrição da Atividade</th>
                <th style="text-align: right;">Duração</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($atividades as $a): 
                $horas = $a['segundos_totais'] / 3600;
                $total = $horas * $a['valor_hora'];
            ?>
            <tr>
                <td><?php echo date('d/m/Y', strtotime($a['data_criacao'])); ?></td>
                <td><?php echo $a['nome_cliente']; ?></td>
                <td><?php echo nl2br(htmlspecialchars($a['descricao'])); ?></td>
                <td style="text-align: right;"><?php echo formatarSegundos($a['segundos_totais']); ?></td>
                <td style="text-align: right; white-space: nowrap;">
                    <?php echo $a['moeda'] . ' ' . number_format($total, 2, ',', '.'); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totais">
        <strong>Tempo Total: <?php echo formatarSegundos($total_horas_geral); ?></strong>
        <hr style="width: 200px; margin-left: auto;">
        <?php foreach($totais_financeiros as $moeda => $valor): ?>
            <div style="font-size: 16px; font-weight: bold;">
                Total a Faturar (<?php echo $moeda; ?>): <?php echo number_format($valor, 2, ',', '.'); ?>
            </div>
        <?php endforeach; ?>
        <?php if(empty($totais_financeiros)) echo "Sem valores faturáveis."; ?>
    </div>

    <div class="assinaturas">
        <div class="assinatura-box">
            <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?><br>
            <small>Consultor Técnico</small>
        </div>
        <div class="assinatura-box">
            Responsável<br>
            <small>Aprovação do Cliente</small>
        </div>
    </div>

    <script>
        // Opcional: Abre a caixa de impressão automaticamente ao carregar
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>