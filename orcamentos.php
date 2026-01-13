<?php
require 'config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/auth.php';

$usuario_id = $_SESSION['usuario_id'];

// 1. CRIAR NOVO ORÇAMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $titulo     = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_SPECIAL_CHARS);
    $prazo      = filter_input(INPUT_POST, 'prazo_entrega', FILTER_SANITIZE_SPECIAL_CHARS); // NOVO
    $desc       = filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_SPECIAL_CHARS);
    
    $tipo       = filter_input(INPUT_POST, 'tipo_cobranca', FILTER_SANITIZE_SPECIAL_CHARS);
    $valor_total = 0;
    $valor_hora  = 0;
    $horas_est   = 0;

    if ($tipo === 'fixo') {
        $valor_total = filter_input(INPUT_POST, 'valor_fixo', FILTER_VALIDATE_FLOAT);
    } else {
        $valor_hora = filter_input(INPUT_POST, 'valor_hora', FILTER_VALIDATE_FLOAT);
        // Aqui pegamos as horas mensais inseridas
        $horas_mensais = filter_input(INPUT_POST, 'horas_estimadas', FILTER_VALIDATE_INT);
        // Pegamos a quantidade de meses para calcular o total (apenas para cálculo do valor total do contrato)
        $qtd_meses = filter_input(INPUT_POST, 'qtd_meses_calc', FILTER_VALIDATE_INT) ?: 1;
        
        $horas_est   = $horas_mensais; // Salvamos a referência mensal/base
        $valor_total = $valor_hora * $horas_mensais * $qtd_meses; // Total Global do Contrato
    }

    if ($cliente_id && $titulo) {
        $sql = "INSERT INTO orcamentos (usuario_id, cliente_id, titulo, prazo_entrega, descricao, tipo_cobranca, valor_total, valor_hora, horas_estimadas, data_criacao) 
                VALUES (:uid, :cid, :tit, :prazo, :desc, :tipo, :vtotal, :vhora, :horas, NOW())";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':uid' => $usuario_id, 
                ':cid' => $cliente_id, 
                ':tit' => $titulo, 
                ':prazo'=> $prazo,
                ':desc' => $desc,
                ':tipo' => $tipo,
                ':vtotal' => $valor_total,
                ':vhora' => $valor_hora,
                ':horas' => $horas_est
            ]);
            
            $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Proposta criada com sucesso!'];
            header("Location: orcamentos.php"); exit;
        } catch (PDOException $e) {
            $_SESSION['toast_msg'] = ['tipo' => 'danger', 'texto' => 'Erro: ' . $e->getMessage()];
        }
    }
}

// 2. APROVAR
if (isset($_GET['aprovar'])) {
    $id_orc = filter_input(INPUT_GET, 'aprovar', FILTER_VALIDATE_INT);
    $orc = $pdo->query("SELECT * FROM orcamentos WHERE id = $id_orc AND usuario_id = $usuario_id")->fetch();
    
    if ($orc && $orc['status'] !== 'aprovado') {
        $horas_proj = ($orc['tipo_cobranca'] === 'hora') ? $orc['horas_estimadas'] : 0;
        
        // Adiciona o prazo na descrição do projeto para não perder a info
        $desc_final = $orc['descricao'] . "\n\n[Prazo Acordado: " . $orc['prazo_entrega'] . "]";

        $sqlProj = "INSERT INTO projetos (cliente_id, nome, descricao, horas_estimadas, status, data_inicio) 
                    VALUES (:cid, :nome, :desc, :horas, 'ativo', CURDATE())";
        $pdo->prepare($sqlProj)->execute([
            ':cid' => $orc['cliente_id'],
            ':nome' => $orc['titulo'],
            ':desc' => $desc_final,
            ':horas' => $horas_proj
        ]);

        $pdo->query("UPDATE orcamentos SET status = 'aprovado' WHERE id = $id_orc");
        $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Projeto criado!'];
        header("Location: projetos.php"); exit;
    }
}

// 3. EXCLUIR
if (isset($_GET['excluir'])) {
    $id = filter_input(INPUT_GET, 'excluir', FILTER_VALIDATE_INT);
    $pdo->query("DELETE FROM orcamentos WHERE id = $id AND usuario_id = $usuario_id");
    header("Location: orcamentos.php"); exit;
}

// BUSCAS
$orcamentos = $pdo->query("SELECT o.*, c.nome as nome_cliente, c.moeda 
                           FROM orcamentos o 
                           JOIN clientes c ON o.cliente_id = c.id 
                           WHERE o.usuario_id = $usuario_id 
                           ORDER BY o.id DESC")->fetchAll(PDO::FETCH_ASSOC);

$clientes = $pdo->query("SELECT * FROM clientes WHERE status='ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0">Propostas Comerciais</h2>
        <p class="text-muted mb-0">Gerencie seus orçamentos e contratos.</p>
    </div>
    <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalNovoOrcamento">
        <i class="fas fa-plus me-1"></i> Nova Proposta
    </button>
</div>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Data</th>
                            <th>Cliente / Título</th>
                            <th>Prazo</th>
                            <th>Valor / Detalhes</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($orcamentos as $o): 
                            $st = $o['status'];
                            $badge = match($st) { 'aprovado'=>'bg-success', 'rejeitado'=>'bg-danger', default=>'bg-warning text-dark' };
                            $tipoIcon = ($o['tipo_cobranca'] === 'hora') ? '<i class="fas fa-history text-info me-1"></i>' : '<i class="fas fa-box text-success me-1"></i>';
                        ?>
                        <tr>
                            <td class="ps-4 text-muted small"><?php echo date('d/m/y', strtotime($o['data_criacao'])); ?></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($o['nome_cliente']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($o['titulo']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="far fa-calendar-alt me-1"></i> <?php echo htmlspecialchars($o['prazo_entrega'] ?? '-'); ?>
                                </span>
                            </td>
                            
                            <td class="text-dark">
                                <?php if($o['tipo_cobranca'] === 'hora'): ?>
                                    <div class="small">
                                        <?php echo $tipoIcon; ?> <strong><?php echo $o['horas_estimadas']; ?>h</strong> x <?php echo number_format($o['valor_hora'], 2, ',', '.'); ?>
                                    </div>
                                    <div class="fw-bold text-primary" style="font-size: 0.9rem;">
                                        Total: <?php echo $o['moeda'] . ' ' . number_format($o['valor_total'], 2, ',', '.'); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="fw-bold"><?php echo $tipoIcon . $o['moeda'] . ' ' . number_format($o['valor_total'], 2, ',', '.'); ?></div>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <span class="badge <?php echo $badge; ?> rounded-pill"><?php echo ucfirst($st); ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <a href="ver_proposta.php?id=<?php echo $o['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Imprimir">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <?php if($st === 'pendente'): ?>
                                        <a href="?aprovar=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Aprovar?');">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="?excluir=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($orcamentos)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">Nenhuma proposta.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNovoOrcamento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Nova Proposta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="criar">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Cliente</label>
                        <select name="cliente_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach($clientes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-8 mb-3">
                            <label class="form-label small fw-bold text-muted">Título</label>
                            <input type="text" name="titulo" class="form-control" required placeholder="Ex: Contrato Semestral">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label small fw-bold text-muted">Duração/Prazo</label>
                            <input type="text" name="prazo_entrega" class="form-control" required placeholder="Ex: 6 Meses">
                        </div>
                    </div>

                    <hr class="my-3">

                    <label class="form-label small fw-bold text-muted mb-2">Modelo de Contrato</label>
                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="tipo_cobranca" id="tipoFixo" value="fixo" checked onchange="toggleTipo('fixo')">
                        <label class="btn btn-outline-primary" for="tipoFixo">Fixo (Entrega Única)</label>

                        <input type="radio" class="btn-check" name="tipo_cobranca" id="tipoHora" value="hora" onchange="toggleTipo('hora')">
                        <label class="btn btn-outline-primary" for="tipoHora">Por Hora / Recorrente</label>
                    </div>

                    <div id="box-fixo">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Valor Total da Proposta</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="number" name="valor_fixo" class="form-control" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div id="box-hora" style="display: none;">
                        <div class="row g-2">
                            <div class="col-4 mb-2">
                                <label class="form-label small fw-bold text-muted">Valor Hora</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" name="valor_hora" id="v_hora" class="form-control" step="0.01" oninput="calcTotal()">
                                </div>
                            </div>
                            <div class="col-4 mb-2">
                                <label class="form-label small fw-bold text-muted">Horas/Mês</label>
                                <input type="number" name="horas_estimadas" id="v_horas" class="form-control form-control-sm" placeholder="Ex: 40" oninput="calcTotal()">
                            </div>
                            <div class="col-4 mb-2">
                                <label class="form-label small fw-bold text-muted">Qtd. Meses</label>
                                <input type="number" name="qtd_meses_calc" id="v_meses" class="form-control form-control-sm" value="1" min="1" oninput="calcTotal()">
                            </div>
                        </div>
                        <div class="alert alert-info border-0 py-2 mt-2 text-center">
                            <small class="d-block text-muted">Valor Global do Contrato</small>
                            <strong class="fs-5" id="preview-total">R$ 0,00</strong>
                        </div>
                    </div>

                    <div class="mb-3 mt-3">
                        <label class="form-label small fw-bold text-muted">Escopo / Detalhes</label>
                        <textarea name="descricao" class="form-control" rows="4" placeholder="Descreva o escopo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold">Criar Proposta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleTipo(tipo) {
    if(tipo === 'fixo') {
        document.getElementById('box-fixo').style.display = 'block';
        document.getElementById('box-hora').style.display = 'none';
        document.querySelector('[name="valor_fixo"]').required = true;
        document.querySelector('[name="valor_hora"]').required = false;
        document.querySelector('[name="horas_estimadas"]').required = false;
    } else {
        document.getElementById('box-fixo').style.display = 'none';
        document.getElementById('box-hora').style.display = 'block';
        document.querySelector('[name="valor_fixo"]').required = false;
        document.querySelector('[name="valor_hora"]').required = true;
        document.querySelector('[name="horas_estimadas"]').required = true;
    }
}

function calcTotal() {
    const vh = parseFloat(document.getElementById('v_hora').value) || 0;
    const qtd = parseFloat(document.getElementById('v_horas').value) || 0;
    const meses = parseFloat(document.getElementById('v_meses').value) || 1;
    
    // Cálculo: Valor Hora * Horas Mensais * Meses
    const total = vh * qtd * meses;
    
    document.getElementById('preview-total').innerText = total.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
}
</script>

<?php require 'includes/footer.php'; ?>