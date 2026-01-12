<?php
require 'config/db.php';
session_start();
// Verifica login
if (!isset($_SESSION['usuario_id'])) die("Acesso negado.");

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$uid = $_SESSION['usuario_id'];

$sql = "SELECT o.*, c.nome as nome_cliente, c.email, c.logo as logo_cliente, c.moeda, c.telefone 
        FROM orcamentos o JOIN clientes c ON o.cliente_id = c.id 
        WHERE o.id = :id AND o.usuario_id = :uid";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id, ':uid' => $uid]);
$orc = $stmt->fetch();

if (!$orc) die("Proposta não encontrada.");

// Dados do Emissor (Você) - Puxando da Sessão ou fixo
$meu_nome = $_SESSION['usuario_nome'];
$meu_email = $_SESSION['usuario_email'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Proposta #<?php echo str_pad($orc['id'], 4, '0', STR_PAD_LEFT); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #525659; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .page {
            background: white;
            width: 21cm;
            min-height: 29.7cm;
            display: block;
            margin: 0 auto;
            margin-bottom: 0.5cm;
            box-shadow: 0 0 0.5cm rgba(0,0,0,0.5);
            padding: 2cm;
            position: relative;
        }
        @media print {
            body { background: none; margin: 0; }
            .page { width: 100%; margin: 0; box-shadow: none; padding: 0; }
            .no-print { display: none; }
        }
        .header-line { border-bottom: 2px solid #333; margin-bottom: 2rem; padding-bottom: 1rem; }
        .footer { position: absolute; bottom: 1.5cm; left: 2cm; right: 2cm; border-top: 1px solid #ccc; padding-top: 1rem; font-size: 0.8rem; color: #777; }
        .client-logo { max-height: 80px; max-width: 150px; object-fit: contain; }
        .valor-box { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 1.5rem; text-align: right; }
    </style>
</head>
<body>

<div class="text-center no-print py-3">
    <button onclick="window.print()" class="btn btn-light fw-bold shadow-sm">🖨️ Imprimir / Salvar PDF</button>
</div>

<div class="page">
    
    <div class="d-flex justify-content-between align-items-center header-line">
        <div>
            <h1 class="fw-bold mb-0">PROPOSTA</h1>
            <div class="text-muted">#<?php echo str_pad($orc['id'], 4, '0', STR_PAD_LEFT); ?></div>
        </div>
        <div class="text-end">
            <h4 class="fw-bold text-dark">RatControl Agency</h4>
            <div class="small text-muted">
                <?php echo $meu_email; ?><br>
                <?php echo date('d/m/Y', strtotime($orc['data_criacao'])); ?>
            </div>
        </div>
    </div>

    <div class="row mb-5">
        <div class="col-6">
            <small class="text-uppercase text-muted fw-bold">Preparado para:</small>
            <h4 class="fw-bold mt-1"><?php echo $orc['nome_cliente']; ?></h4>
            <div class="text-muted small">
                <?php echo $orc['email']; ?><br>
                <?php echo $orc['telefone']; ?>
            </div>
        </div>
        <div class="col-6 text-end">
            <?php if($orc['logo_cliente']): ?>
                <img src="<?php echo $orc['logo_cliente']; ?>" class="client-logo">
            <?php endif; ?>
        </div>
    </div>

    <div class="mb-5">
        <h3 class="fw-bold text-primary mb-4"><?php echo $orc['titulo']; ?></h3>
        
        <div class="p-3">
            <h6 class="fw-bold text-uppercase text-secondary mb-3">Escopo do Serviço</h6>
            <div style="white-space: pre-wrap; line-height: 1.6; color: #444;"><?php echo $orc['descricao']; ?></div>
        </div>
    </div>

    <div class="row justify-content-end">
        <div class="col-md-6">
            <div class="valor-box">
                <small class="text-muted text-uppercase fw-bold">Investimento Total</small>
                <div class="display-5 fw-bold text-dark mt-1">
                    <?php echo $orc['moeda'] . ' ' . number_format($orc['valor_total'], 2, ',', '.'); ?>
                </div>
                <div class="small text-muted mt-2">Validade da proposta: 15 dias</div>
            </div>
        </div>
    </div>

    <div style="margin-top: 4cm;" class="row">
        <div class="col-6 text-center">
            <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 10px;">
                <strong>RatControl Agency</strong><br>
                <small>Contratada</small>
            </div>
        </div>
        <div class="col-6 text-center">
            <div style="border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 10px;">
                <strong><?php echo $orc['nome_cliente']; ?></strong><br>
                <small>Contratante</small>
            </div>
        </div>
    </div>

    <div class="footer text-center">
        <p>Documento gerado eletronicamente via <strong>RatControl System</strong>.</p>
    </div>

</div>

</body>
</html>