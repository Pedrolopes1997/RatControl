<?php
require 'config/db.php';
require_once 'includes/auth.php';

$usuario_id = $_SESSION['usuario_id'];

// 1. CRIAR NOVO ORÇAMENTO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $titulo = filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_SPECIAL_CHARS);
    $valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
    $desc = filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_SPECIAL_CHARS);

    if ($cliente_id && $titulo) {
        $sql = "INSERT INTO orcamentos (usuario_id, cliente_id, titulo, descricao, valor_total) 
                VALUES (:uid, :cid, :tit, :desc, :val)";
        $pdo->prepare($sql)->execute([
            ':uid' => $usuario_id, ':cid' => $cliente_id, ':tit' => $titulo, ':desc' => $desc, ':val' => $valor
        ]);
        $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Orçamento criado!'];
        header("Location: orcamentos.php"); exit;
    }
}

// 2. CONVERTER EM PROJETO (APROVAR)
if (isset($_GET['aprovar'])) {
    $id_orc = filter_input(INPUT_GET, 'aprovar', FILTER_VALIDATE_INT);
    
    // Busca dados do orçamento
    $orc = $pdo->query("SELECT * FROM orcamentos WHERE id = $id_orc AND usuario_id = $usuario_id")->fetch();
    
    if ($orc && $orc['status'] !== 'aprovado') {
        // 1. Cria o Projeto
        // Tenta calcular horas estimadas baseadas no valor (se o cliente tiver valor/hora)
        $cli = $pdo->query("SELECT valor_hora FROM clientes WHERE id = " . $orc['cliente_id'])->fetch();
        $horas_estimadas = ($cli['valor_hora'] > 0) ? floor($orc['valor_total'] / $cli['valor_hora']) : 0;

        $sqlProj = "INSERT INTO projetos (cliente_id, nome, descricao, horas_estimadas, status, data_inicio) 
                    VALUES (:cid, :nome, :desc, :horas, 'ativo', CURDATE())";
        $pdo->prepare($sqlProj)->execute([
            ':cid' => $orc['cliente_id'],
            ':nome' => $orc['titulo'],
            ':desc' => $orc['descricao'],
            ':horas' => $horas_estimadas
        ]);

        // 2. Atualiza Orçamento para Aprovado
        $pdo->query("UPDATE orcamentos SET status = 'aprovado' WHERE id = $id_orc");

        $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Parabéns! Orçamento virou Projeto.'];
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
                           FROM orcamentos o JOIN clientes c ON o.cliente_id = c.id 
                           WHERE o.usuario_id = $usuario_id ORDER BY o.id DESC")->fetchAll();
$clientes = $pdo->query("SELECT * FROM clientes WHERE status='ativo' ORDER BY nome")->fetchAll();

require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0">Orçamentos</h2>
        <p class="text-muted mb-0">Crie propostas comerciais e converta em projetos.</p>
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
                            <th>Cliente</th>
                            <th>Título da Proposta</th>
                            <th>Valor</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($orcamentos as $o): 
                            $st = $o['status'];
                            $badge = match($st) { 'aprovado'=>'bg-success', 'rejeitado'=>'bg-danger', default=>'bg-warning text-dark' };
                        ?>
                        <tr>
                            <td class="ps-4 text-muted"><?php echo date('d/m/Y', strtotime($o['data_criacao'])); ?></td>
                            <td class="fw-bold"><?php echo $o['nome_cliente']; ?></td>
                            <td><?php echo $o['titulo']; ?></td>
                            <td class="font-monospace fw-bold text-dark">
                                <?php echo $o['moeda'] . ' ' . number_format($o['valor_total'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $badge; ?> rounded-pill"><?php echo ucfirst($st); ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <a href="ver_proposta.php?id=<?php echo $o['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver Documento">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    
                                    <?php if($st === 'pendente'): ?>
                                        <a href="?aprovar=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-success" title="Cliente Aprovou (Criar Projeto)" onclick="return confirm('O cliente aprovou? Isso criará um novo Projeto.');">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <a href="?excluir=<?php echo $o['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Excluir proposta?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light text-muted" disabled><i class="fas fa-lock"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($orcamentos)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">Nenhum orçamento criado.</td></tr>
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
                <h5 class="modal-title fw-bold">Nova Proposta Comercial</h5>
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
                                <option value="<?php echo $c['id']; ?>"><?php echo $c['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Título do Serviço</label>
                        <input type="text" name="titulo" class="form-control" required placeholder="Ex: Desenvolvimento E-commerce">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Valor Total da Proposta</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="valor" class="form-control" step="0.01" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Escopo / Detalhes</label>
                        <textarea name="descricao" class="form-control" rows="5" placeholder="Descreva o que será entregue..."></textarea>
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

<?php require 'includes/footer.php'; ?>