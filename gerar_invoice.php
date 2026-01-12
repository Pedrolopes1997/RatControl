<?php
require 'config/db.php';
require_once 'includes/auth.php';

// Verifica se a biblioteca foi instalada
if (!file_exists('fpdf/fpdf.php')) {
    die("Erro: A biblioteca FPDF não foi encontrada na pasta 'fpdf/'. Por favor, faça o upload.");
}
require('fpdf/fpdf.php');

// Filtros
$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim = $_GET['fim'] ?? date('Y-m-d');
$cliente_id = $_GET['cliente'] ?? '';

// Validação: Invoice precisa de um cliente específico
if (empty($cliente_id)) {
    die("<h3>Erro: Selecione um Cliente</h3><p>Para gerar uma fatura (Invoice), você deve filtrar por um cliente específico na tela de relatórios.</p><a href='relatorios.php'>Voltar</a>");
}

// Busca Dados do Cliente
$stmtCli = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
$stmtCli->execute([':id' => $cliente_id]);
$cliente = $stmtCli->fetch();

// Busca Atividades
$sql = "SELECT t.*, p.nome as nome_projeto 
        FROM tarefas t 
        LEFT JOIN projetos p ON t.projeto_id = p.id
        WHERE t.cliente_id = :cid 
        AND DATE(t.data_criacao) BETWEEN :inicio AND :fim
        ORDER BY t.data_criacao ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':cid' => $cliente_id, ':inicio' => $data_inicio, ':fim' => $data_fim]);
$atividades = $stmt->fetchAll();

// Busca Logs de tempo para cálculo preciso
$sqlLogs = "SELECT tarefa_id, SUM(TIMESTAMPDIFF(SECOND, inicio, IFNULL(fim, NOW()))) as segundos
            FROM tempo_logs 
            WHERE tarefa_id IN (SELECT id FROM tarefas WHERE cliente_id = :cid)
            GROUP BY tarefa_id";
$stmtLogs = $pdo->prepare($sqlLogs);
$stmtLogs->execute([':cid' => $cliente_id]);
$mapa_tempos = $stmtLogs->fetchAll(PDO::FETCH_KEY_PAIR);

// --- CRIAÇÃO DO PDF ---
class PDF extends FPDF {
    // Cabeçalho
    function Header() {
        // Logo (Se tiver um arquivo logo.png, descomente a linha abaixo)
        // $this->Image('assets/logo.png',10,6,30);
        
        $this->SetFont('Arial','B',15);
        $this->Cell(80);
        $this->Cell(30,10,'INVOICE / FATURA',0,0,'C');
        $this->Ln(20);
    }

    // Rodapé
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'RatControl - Sistema de Gestao',0,0,'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial','',12);

// --- INFO DO EMISSOR (VOCÊ) ---
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,6,utf8_decode($_SESSION['usuario_nome']),0,1);
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,5,'Consultor',0,1);
$pdf->Cell(0,5,'Seu Email: pedro@wcic.com.br',0,1);
$pdf->Cell(0,5,'Data de Emissao: ' . date('d/m/Y'),0,1);

$pdf->Ln(10); // Espaço

// --- INFO DO CLIENTE ---
$pdf->SetFillColor(230,230,230);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,8,'DADOS DO CLIENTE',1,1,'L',true);
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,'Cliente: ' . utf8_decode($cliente['nome']),0,1);
$pdf->Cell(0,6,'Periodo: ' . date('d/m/Y', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim)),0,1);
$pdf->Cell(0,6,'Moeda Contratada: ' . $cliente['moeda'],0,1);

$pdf->Ln(10);

// --- TABELA DE SERVIÇOS ---
$pdf->SetFont('Arial','B',10);
$pdf->Cell(25,7,'Data',1);
$pdf->Cell(85,7,utf8_decode('Descrição / Projeto'),1);
$pdf->Cell(25,7,'Horas',1,0,'R');
$pdf->Cell(25,7,'Taxa',1,0,'R');
$pdf->Cell(30,7,'Total',1,0,'R');
$pdf->Ln();

$pdf->SetFont('Arial','',9);

$total_geral = 0;
$total_horas = 0;

foreach($atividades as $a) {
    $segundos = $mapa_tempos[$a['id']] ?? 0;
    $horas = $segundos / 3600;
    $valor_item = $horas * $cliente['valor_hora'];
    
    $total_geral += $valor_item;
    $total_horas += $horas;

    // Descrição + Nome do Projeto
    $desc = $a['descricao'];
    if($a['nome_projeto']) $desc = '['.$a['nome_projeto'].'] ' . $desc;
    
    // Formatação de data e valores
    $data_fmt = date('d/m', strtotime($a['data_criacao']));
    $horas_fmt = number_format($horas, 2, ',', '.');
    $taxa_fmt = number_format($cliente['valor_hora'], 2, ',', '.');
    $total_fmt = number_format($valor_item, 2, ',', '.');

    // Imprime linha (MultiCell usado se a descrição for longa, mas aqui simplificamos com Cell e corte de texto)
    $pdf->Cell(25,7,$data_fmt,1);
    $pdf->Cell(85,7,utf8_decode(mb_strimwidth($desc, 0, 50, '...')),1);
    $pdf->Cell(25,7,$horas_fmt,1,0,'R');
    $pdf->Cell(25,7,$taxa_fmt,1,0,'R');
    $pdf->Cell(30,7,$total_fmt,1,0,'R');
    $pdf->Ln();
}

// --- TOTAIS ---
$pdf->Ln(5);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(135,10,'TOTAL A PAGAR',0,0,'R');
$pdf->Cell(55,10,$cliente['moeda'] . ' ' . number_format($total_geral, 2, ',', '.'),1,1,'R');

// --- DADOS BANCÁRIOS (Personalize aqui!) ---
$pdf->Ln(20);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,8,utf8_decode('DADOS PARA PAGAMENTO'),0,1);
$pdf->SetFont('Arial','',10);

if ($cliente['moeda'] == 'R$') {
    $pdf->Cell(0,6,'Banco: Sicredi',0,1);
    $pdf->Cell(0,6,'Chave PIX (CNPJ): 35201658000146',0,1);
    $pdf->Cell(0,6,'Nome: WeCare Consultoria',0,1);
} else {
    // Dados Internacionais
    $pdf->Cell(0,6,'Bank: Wise / Payoneer',0,1);
    $pdf->Cell(0,6,'IBAN: US123456789...',0,1);
    $pdf->Cell(0,6,'SWIFT: WISEUS...',0,1);
}

// Mensagem Final
$pdf->Ln(15);
$pdf->SetFont('Arial','I',10);
$pdf->Cell(0,10,utf8_decode('Obrigado pela parceria!'),0,1,'C');

$pdf->Output();
?>