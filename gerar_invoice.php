<?php
require 'config/db.php';
require_once 'includes/auth.php';

// Verifica biblioteca
if (!file_exists('fpdf/fpdf.php')) {
    die("Erro: FPDF não encontrado na pasta 'fpdf/'.");
}
require('fpdf/fpdf.php');

// --- CONFIGURAÇÕES ---
$minha_empresa = "WeCare Consultoria";
$meu_email     = "pedro@wcic.com.br";
$meu_site      = "www.wecareconsultoria.com.br";
$cor_primaria  = [14, 42, 71]; // Azul (#0e2a47)

// Filtros
$data_inicio = $_GET['inicio'] ?? date('Y-m-01');
$data_fim    = $_GET['fim']    ?? date('Y-m-d');
$cliente_id  = $_GET['cliente'] ?? '';

if (empty($cliente_id)) die("Erro: Cliente não selecionado.");

// Busca Cliente
$stmtCli = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
$stmtCli->execute([':id' => $cliente_id]);
$cliente = $stmtCli->fetch();

// 1. BUSCA TUDO (Tarefas Concluídas)
$sql = "SELECT t.*, p.nome as nome_projeto 
        FROM tarefas t 
        LEFT JOIN projetos p ON t.projeto_id = p.id
        WHERE t.cliente_id = :cid 
        AND DATE(t.data_criacao) BETWEEN :inicio AND :fim
        AND t.status IN ('concluido', 'finalizado') 
        ORDER BY p.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':cid' => $cliente_id, ':inicio' => $data_inicio, ':fim' => $data_fim]);
$atividades = $stmt->fetchAll();

// 2. BUSCA TEMPOS REAIS
$sqlLogs = "SELECT tarefa_id, SUM(TIMESTAMPDIFF(SECOND, inicio, IFNULL(fim, NOW()))) as segundos
            FROM tempo_logs 
            WHERE tarefa_id IN (SELECT id FROM tarefas WHERE cliente_id = :cid)
            GROUP BY tarefa_id";
$stmtLogs = $pdo->prepare($sqlLogs);
$stmtLogs->execute([':cid' => $cliente_id]);
$mapa_tempos = $stmtLogs->fetchAll(PDO::FETCH_KEY_PAIR);

// 3. LÓGICA DE AGRUPAMENTO (Transforma Lista em Fatura)
$fatura_itens = [];

foreach ($atividades as $a) {
    $segundos = $mapa_tempos[$a['id']] ?? 0;
    if ($segundos <= 0) continue; // Pula se não teve tempo trabalhado

    // Define a chave de agrupamento (Nome do Projeto ou Avulso)
    $chave = $a['nome_projeto'] ? $a['nome_projeto'] : "Consultoria Avulsa / Suporte Geral";
    
    if (!isset($fatura_itens[$chave])) {
        $fatura_itens[$chave] = [
            'descricao' => $chave,
            'segundos'  => 0,
            'valor_hora'=> $cliente['valor_hora'] // Assume taxa do cliente (pode ser ajustado por projeto se tiver)
        ];
    }
    
    // Soma o tempo neste projeto
    $fatura_itens[$chave]['segundos'] += $segundos;
}

// --- GERAÇÃO DO PDF ---
class PDF extends FPDF {
    function Header() {
        global $minha_empresa, $meu_site, $cor_primaria;
        
        // Faixa Lateral
        $this->SetFillColor($cor_primaria[0], $cor_primaria[1], $cor_primaria[2]);
        $this->Rect(0, 0, 10, 297, 'F');
        
        $this->SetY(15);
        $this->SetX(20);
        
        // Título EM MAIÚSCULO DIRETO (Resolve problema do ç)
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor($cor_primaria[0], $cor_primaria[1], $cor_primaria[2]);
        $this->Cell(100, 10, utf8_decode("FATURA DE SERVIÇOS PRESTADOS"), 0, 0);
        
        // Dados Empresa
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->SetXY(-70, 15);
        $this->MultiCell(60, 5, utf8_decode("$minha_empresa\n$meu_site\nRatControl System"), 0, 'R');
        
        $this->Ln(20);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetLeftMargin(20);

// --- DADOS DO CLIENTE ---
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(50);

$pdf->Cell(100, 6, utf8_decode("CLIENTE:"), 0, 0);
$pdf->Cell(0, 6, utf8_decode("DETALHES:"), 0, 1);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(100, 6, utf8_decode($cliente['nome']), 0, 0);
$pdf->Cell(0, 6, utf8_decode("Emissão: " . date('d/m/Y')), 0, 1);

$pdf->SetFont('Arial', '', 10);
if($cliente['documento']) $pdf->Cell(100, 6, "CNPJ/CPF: " . $cliente['documento'], 0, 0);
else $pdf->Cell(100, 6, "", 0, 0);

$pdf->Cell(0, 6, utf8_decode("Período: " . date('d/m', strtotime($data_inicio)) . ' a ' . date('d/m/Y', strtotime($data_fim))), 0, 1);

$pdf->Ln(15);

// --- TABELA RESUMIDA (Tipo Fatura) ---
$pdf->SetFillColor(245, 245, 245);
$pdf->SetDrawColor(220, 220, 220);
$pdf->SetLineWidth(0.2);

// Cabeçalho
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor($cor_primaria[0], $cor_primaria[1], $cor_primaria[2]);

// Colunas ajustadas para Fatura (Sem Data, Foco no Serviço)
$pdf->Cell(115, 10, utf8_decode('SERVIÇO / PROJETO'), 1, 0, 'L', true);
$pdf->Cell(25, 10, 'HORAS', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'TAXA', 1, 0, 'C', true);
$pdf->Cell(25, 10, 'SUBTOTAL', 1, 1, 'R', true);

// Itens
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0);

$total_geral = 0;
$total_segundos = 0;

foreach ($fatura_itens as $item) {
    // Cálculos
    $horas = $item['segundos'] / 3600;
    $valor_item = $horas * $item['valor_hora'];
    
    $total_geral += $valor_item;
    $total_segundos += $item['segundos'];

    // Formatação
    $nome_servico = utf8_decode($item['descricao']);
    $horas_fmt = number_format($horas, 2, ',', '.');
    $taxa_fmt  = number_format($item['valor_hora'], 2, ',', '.');
    $total_fmt = number_format($valor_item, 2, ',', '.');

    // Imprime Linha (Altura fixa pois nomes de projeto costumam ser curtos)
    // Se quiser quebra de linha em nome de projeto longo, pode usar MultiCell, 
    // mas geralmente em fatura uma linha basta.
    $pdf->Cell(115, 10, $nome_servico, 1, 0, 'L');
    $pdf->Cell(25, 10, $horas_fmt, 1, 0, 'C');
    $pdf->Cell(25, 10, $taxa_fmt, 1, 0, 'C');
    $pdf->Cell(25, 10, $total_fmt, 1, 1, 'R');
}

// Se vazio
if (empty($fatura_itens)) {
    $pdf->Cell(190, 10, utf8_decode('Nenhum serviço faturável encontrado neste período.'), 1, 1, 'C');
}

// --- TOTAIS ---
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor($cor_primaria[0], $cor_primaria[1], $cor_primaria[2]);

// Converte total de segundos para HH:MM visual
$horas_visuais = floor($total_segundos / 3600) . ':' . gmdate("i", $total_segundos);

$pdf->Cell(140, 12, utf8_decode("Total Horas: " . $horas_visuais), 0, 0, 'R');

// Caixa de Total em Destaque
$pdf->SetFillColor($cor_primaria[0], $cor_primaria[1], $cor_primaria[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(50, 12, $cliente['moeda'] . ' ' . number_format($total_geral, 2, ',', '.'), 0, 1, 'C', true);

// --- DADOS BANCÁRIOS ---
$pdf->Ln(20);
$pdf->SetTextColor(0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 8, utf8_decode('DADOS PARA PAGAMENTO'), 0, 1);
$pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->GetX()+190, $pdf->GetY());
$pdf->Ln(5);

$pdf->SetFont('Arial', '', 10);
// Fundo leve
$pdf->SetFillColor(248);
$pdf->Rect($pdf->GetX(), $pdf->GetY(), 190, 25, 'F');

$pdf->SetX($pdf->GetX() + 5);
$pdf->Cell(0, 8, utf8_decode("Banco: Sicredi (748)"), 0, 1);
$pdf->SetX($pdf->GetX() + 5);
$pdf->Cell(0, 6, utf8_decode("Agência: 0001  |  Conta: 12345-6"), 0, 1);
$pdf->SetX($pdf->GetX() + 5);
$pdf->Cell(0, 6, utf8_decode("Chave PIX (CNPJ): 35.201.658/0001-46 ($minha_empresa)"), 0, 1);

// Nota de Rodapé
$pdf->Ln(25);
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(100);
$pdf->MultiCell(0, 4, utf8_decode("Nota: O relatório detalhado das atividades (RAT) está disponível no portal do cliente ou mediante solicitação.\nEste documento é uma fatura de serviços e não substitui a Nota Fiscal oficial."), 0, 'C');

// Download
$nome_arquivo = "Fatura_" . preg_replace('/[^A-Za-z0-9]/', '', $cliente['nome']) . "_" . date('m-Y') . ".pdf";
$pdf->Output('D', $nome_arquivo);
?>