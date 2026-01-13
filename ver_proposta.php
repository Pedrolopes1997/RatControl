<?php
// Configurações
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
require 'config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) die("Acesso negado.");

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$uid = $_SESSION['usuario_id'];

$sql = "SELECT o.*, c.nome as nome_cliente, c.email, c.logo as logo_cliente, c.moeda, c.telefone, c.documento 
        FROM orcamentos o 
        JOIN clientes c ON o.cliente_id = c.id 
        WHERE o.id = :id AND o.usuario_id = :uid";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id, ':uid' => $uid]);
$orc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orc) die("Proposta não encontrada.");

$minha_empresa = "WeCare Consultoria"; 
$meu_email     = $_SESSION['usuario_email'] ?? 'contato@wecare.com.br';
$meu_site      = "www.wecareconsultoria.com.br";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Proposta #<?php echo str_pad($orc['id'], 4, '0', STR_PAD_LEFT); ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --cor-primaria: #0e2a47; 
            --cor-fundo: #525659;
            --fonte-titulo: 'Montserrat', sans-serif;
            --fonte-texto: 'Inter', sans-serif;
        }

        body { background-color: var(--cor-fundo); font-family: var(--fonte-texto); color: #333; }

        /* --- PÁGINA A4 --- */
        .page {
            background: white; 
            width: 210mm; 
            min-height: 297mm; 
            display: block; 
            margin: 20px auto; 
            box-shadow: 0 0 15px rgba(0,0,0,0.2); 
            position: relative; 
            overflow: hidden; 
        }

        .brand-strip { position: absolute; left: 0; top: 0; bottom: 0; width: 12px; background: var(--cor-primaria); z-index: 10; height: 100%; }
        .content-padding { padding: 2cm 2cm 2cm 2.5cm; }

        /* Estilos Gerais */
        .header-section { border-bottom: 2px solid #f0f0f0; padding-bottom: 1.5rem; margin-bottom: 2rem; }
        .proposta-id { font-family: var(--fonte-titulo); font-size: 2.2rem; font-weight: 700; color: var(--cor-primaria); line-height: 1; }
        .badge-tipo { background: #e9ecef; color: #555; padding: 4px 10px; border-radius: 4px; font-size: 0.7rem; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; }

        .info-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; color: #999; font-weight: 700; margin-bottom: 4px; display: block; }
        .info-value { font-weight: 600; color: #333; font-size: 1rem; }
        
        .project-title { font-family: var(--fonte-titulo); font-size: 1.3rem; font-weight: 700; color: #222; margin-bottom: 0.5rem; }
        .prazo-tag { display: inline-block; background: #e3f2fd; color: #0d47a1; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; margin-bottom: 1rem; }
        .description-box { line-height: 1.6; color: #444; text-align: justify; font-size: 0.9rem; margin-bottom: 2rem; }

        .no-break { page-break-inside: avoid; }

        .price-card { background: #f8f9fa; border-left: 5px solid var(--cor-primaria); padding: 20px; }
        .total-value { font-family: var(--fonte-titulo); font-weight: 700; font-size: 2rem; color: var(--cor-primaria); }
        .sub-total { font-size: 0.85rem; color: #666; }
        
        .table-finan td { padding: 5px 0; border-bottom: 1px dashed #ddd; font-size: 0.85rem; }
        .table-finan tr:last-child td { border-bottom: none; }
        .tf-label { color: #666; }
        .tf-value { font-weight: 600; text-align: right; color: #333; }

        .footer { position: absolute; bottom: 0; left: 0; right: 0; background: #fff; padding: 10px 2.5cm; font-size: 0.7rem; color: #888; border-top: 1px solid #eee; display: flex; justify-content: space-between; z-index: 20; }

        /* --- IMPRESSÃO --- */
        @media print {
            @page { margin: 0; size: A4; }
            body { margin: 0; padding: 0; background: white; }
            .page { width: 100%; height: 100%; margin: 0; box-shadow: none; border: none; min-height: auto; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .no-print { display: none !important; }
            .content-padding { padding: 1.5cm 1.5cm 1.5cm 2cm; }
            .footer { position: fixed; bottom: 0; }
        }
    </style>
</head>
<body>

<div class="no-print text-center py-4 sticky-top" style="background: rgba(50,50,50,0.9); backdrop-filter: blur(5px);">
    <button onclick="window.print()" class="btn btn-primary fw-bold px-4 rounded-pill shadow">🖨️ Imprimir / Salvar PDF</button>
    <button onclick="window.close()" class="btn btn-outline-light ms-2 px-4 rounded-pill">Fechar</button>
</div>

<div class="page">
    <div class="brand-strip"></div>

    <div class="content-padding">
        
        <div class="header-section d-flex justify-content-between align-items-start">
            <div>
                <span class="badge-tipo">Proposta Comercial</span>
                <div class="proposta-id">#<?php echo str_pad($orc['id'], 4, '0', STR_PAD_LEFT); ?></div>
                <div class="text-muted small mt-1">Data: <?php echo date('d/m/Y', strtotime($orc['data_criacao'])); ?></div>
            </div>
            <div class="text-end">
                <h4 class="fw-bold m-0" style="color: var(--cor-primaria); font-family: var(--fonte-titulo);">
                    <?php echo htmlspecialchars($minha_empresa); ?>
                </h4>
                <div class="small text-muted mt-1">
                    <?php echo htmlspecialchars($meu_email); ?><br>
                    <?php echo htmlspecialchars($meu_site); ?>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-8">
                <span class="info-label">PREPARADO PARA</span>
                <div class="info-value"><?php echo htmlspecialchars($orc['nome_cliente']); ?></div>
                <div class="small text-secondary mt-1" style="line-height: 1.4;">
                    <?php if(!empty($orc['documento'])): ?>
                        <strong>Doc:</strong> <?php echo htmlspecialchars($orc['documento']); ?><br>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($orc['email']); ?><br>
                    <?php echo htmlspecialchars($orc['telefone']); ?>
                </div>
            </div>
            <div class="col-4 text-end">
                <?php if(!empty($orc['logo_cliente'])): ?>
                    <img src="<?php echo htmlspecialchars($orc['logo_cliente']); ?>" alt="Logo" style="max-height: 50px; max-width: 140px;">
                <?php endif; ?>
            </div>
        </div>

        <div class="mb-4">
            <h2 class="project-title"><?php echo htmlspecialchars($orc['titulo']); ?></h2>
            
            <?php if(!empty($orc['prazo_entrega'])): ?>
                <?php 
                    $prazo = $orc['prazo_entrega'];
                    // Lógica Inteligente: Se for só número, adiciona "Meses"
                    if (is_numeric($prazo)) {
                        $prazo .= ($prazo == 1) ? ' Mês' : ' Meses';
                    }
                ?>
                <div class="prazo-tag">
                    <i class="far fa-clock me-1"></i> Prazo estimado: <?php echo htmlspecialchars($prazo); ?>
                </div>
            <?php endif; ?>
            
            <div class="description-box">
                <?php echo nl2br(htmlspecialchars($orc['descricao'])); ?>
            </div>
        </div>

        <div class="row justify-content-end mb-5 no-break">
            <div class="col-md-6">
                <div class="price-card">
                    
                    <?php if($orc['tipo_cobranca'] === 'hora'): 
                        $mensal = $orc['valor_hora'] * $orc['horas_estimadas'];
                    ?>
                        <div class="mb-2 border-bottom pb-2">
                            <span class="text-uppercase fw-bold text-muted small">Detalhamento</span>
                        </div>
                        <table class="w-100 table-finan mb-3">
                            <tr>
                                <td class="tf-label">Valor Hora Técnica</td>
                                <td class="tf-value"><?php echo $orc['moeda'] . ' ' . number_format($orc['valor_hora'], 2, ',', '.'); ?></td>
                            </tr>
                            <tr>
                                <td class="tf-label">Estimativa Mensal</td>
                                <td class="tf-value"><?php echo $orc['horas_estimadas']; ?> horas</td>
                            </tr>
                        </table>

                        <div class="text-end">
                            <small class="text-uppercase fw-bold text-primary" style="font-size: 0.75rem;">Investimento Mensal</small>
                            <div class="total-value">
                                <?php echo $orc['moeda'] . ' ' . number_format($mensal, 2, ',', '.'); ?>
                            </div>
                            <div class="sub-total mt-1">
                                Total Global: <?php echo $orc['moeda'] . ' ' . number_format($orc['valor_total'], 2, ',', '.'); ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="text-uppercase fw-bold text-muted small mb-2">Investimento Total</div>
                        <div class="total-value">
                            <?php echo $orc['moeda'] . ' ' . number_format($orc['valor_total'], 2, ',', '.'); ?>
                        </div>
                    <?php endif; ?>

                    <div class="small text-muted mt-2 fst-italic border-top pt-2" style="font-size: 0.75rem;">
                        * Proposta válida por 15 dias.
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 2cm;" class="row no-break">
            <div class="col-6 px-4 text-center">
                <div style="border-top: 1px solid #ccc; padding-top: 10px;">
                    <strong class="d-block text-uppercase small text-dark"><?php echo htmlspecialchars($minha_empresa); ?></strong>
                    <span class="text-muted small">Contratada</span>
                </div>
            </div>
            <div class="col-6 px-4 text-center">
                <div style="border-top: 1px solid #ccc; padding-top: 10px;">
                    <strong class="d-block text-uppercase small text-dark"><?php echo htmlspecialchars($orc['nome_cliente']); ?></strong>
                    <span class="text-muted small">Aceite / Contratante</span>
                </div>
            </div>
        </div>

    </div>

    <div class="footer">
        <div><strong>RatControl System</strong> &bull; WeCare</div>
        <div>Página 1 de 1</div>
    </div>
</div>

</body>
</html>