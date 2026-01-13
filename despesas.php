<?php
require 'config/db.php';
require 'includes/header.php';

$mensagem = '';
$uid = $_SESSION['usuario_id'];

// 1. SALVAR DESPESA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valor'])) {
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $projeto_id = filter_input(INPUT_POST, 'projeto_id', FILTER_VALIDATE_INT) ?: NULL;
    $descricao  = filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_SPECIAL_CHARS);
    $valor      = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
    $data       = $_POST['data'];

    // Upload do Comprovante (Opcional)
    $comprovante = null;
    if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            $novo_nome = 'recibo_' . uniqid() . '.' . $ext;
            $destino = 'assets/uploads/recibos/';
            if (!is_dir($destino)) mkdir($destino, 0755, true);
            
            if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $destino . $novo_nome)) {
                $comprovante = $destino . $novo_nome;
            }
        }
    }

    if ($cliente_id && $valor && $descricao) {
        try {
            // Nota: Se sua tabela despesas não tiver a coluna 'comprovante', rode o SQL abaixo no banco:
            // ALTER TABLE despesas ADD COLUMN comprovante VARCHAR(255) NULL;
            
            // Para garantir que não quebre se a coluna não existir, vamos verificar antes ou assumir que você vai criar.
            // Vou usar um try/catch específico para colunas que podem faltar.
            
            $sql = "INSERT INTO despesas (usuario_id, cliente_id, projeto_id, descricao, valor, data_despesa, comprovante) 
                    VALUES (:uid, :cid, :pid, :desc, :val, :data, :comp)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':uid' => $uid,
                ':cid' => $cliente_id,
                ':pid' => $projeto_id,
                ':desc' => $descricao,
                ':val' => $valor,
                ':data' => $data,
                ':comp' => $comprovante
            ]);
            $mensagem = '<div class="alert alert-success alert-dismissible fade show">Despesa registrada com sucesso!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (PDOException $e) {
            // Se der erro de coluna faltando, avisa
            if (strpos($e->getMessage(), "Unknown column 'comprovante'") !== false) {
                 $mensagem = '<div class="alert alert-warning">Erro: Coluna "comprovante" não existe no banco. <br>Execute: <code>ALTER TABLE despesas ADD COLUMN comprovante VARCHAR(255) NULL;</code></div>';
            } else {
                 $mensagem = '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

// 2. EXCLUIR DESPESA
if (isset($_GET['excluir'])) {
    $id = filter_input(INPUT_GET, 'excluir', FILTER_VALIDATE_INT);
    // Remove o arquivo físico se existir
    $stmt = $pdo->prepare("SELECT comprovante FROM despesas WHERE id = :id AND usuario_id = :uid");
    $stmt->execute([':id' => $id, ':uid' => $uid]);
    $arq = $stmt->fetchColumn();
    if ($arq && file_exists($arq)) unlink($arq);
    
    $pdo->prepare("DELETE FROM despesas WHERE id = :id AND usuario_id = :uid")->execute([':id' => $id, ':uid' => $uid]);
    header("Location: despesas.php"); exit;
}

// 3. BUSCAR DADOS
$clientes = $pdo->query("SELECT * FROM clientes WHERE status='ativo' ORDER BY nome")->fetchAll();
$todos_projetos = $pdo->query("SELECT * FROM projetos WHERE status='ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Lista das últimas 20 despesas
$sqlLista = "SELECT d.*, c.nome as nome_cliente, p.nome as nome_projeto 
             FROM despesas d 
             JOIN clientes c ON d.cliente_id = c.id 
             LEFT JOIN projetos p ON d.projeto_id = p.id
             WHERE d.usuario_id = :uid 
             ORDER BY d.data_despesa DESC LIMIT 20";
$stmtL = $pdo->prepare($sqlLista);
$stmtL->execute([':uid' => $uid]);
$lista_despesas = $stmtL->fetchAll();
?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-white fw-bold py-3 text-danger border-bottom">
                <i class="fas fa-receipt me-2"></i> Nova Despesa
            </div>
            <div class="card-body">
                <?php echo $mensagem; ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Cliente *</label>
                        <select name="cliente_id" id="cliente_id" class="form-select" required onchange="filtrarProjetos()">
                            <option value="">Selecione...</option>
                            <?php foreach($clientes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo $c['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Projeto Relacionado</label>
                        <select name="projeto_id" id="projeto_id" class="form-select" disabled>
                            <option value="">Selecione um cliente...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Descrição do Custo *</label>
                        <input type="text" name="descricao" class="form-control" placeholder="Ex: Hospedagem AWS, Uber..." required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small fw-bold text-muted">Valor *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0">$</span>
                                <input type="number" name="valor" class="form-control border-start-0 ps-0" step="0.01" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small fw-bold text-muted">Data</label>
                            <input type="date" name="data" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="small fw-bold text-muted">Comprovante (Imagem/PDF)</label>
                        <input type="file" name="comprovante" class="form-control form-control-sm" accept="image/*,.pdf">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger fw-bold">
                            <i class="fas fa-plus-circle me-2"></i> Registrar Saída
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark">Últimas Despesas Lançadas</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Data</th>
                            <th>Cliente / Projeto</th>
                            <th>Descrição</th>
                            <th class="text-end">Valor</th>
                            <th class="text-center">Recibo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($lista_despesas as $d): ?>
                        <tr>
                            <td class="ps-4 text-muted" style="font-size: 0.9rem;">
                                <?php echo date('d/m/Y', strtotime($d['data_despesa'])); ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo $d['nome_cliente']; ?></div>
                                <div class="small text-muted"><?php echo $d['nome_projeto'] ?? '-'; ?></div>
                            </td>
                            <td><?php echo $d['descricao']; ?></td>
                            <td class="text-end text-danger fw-bold" style="font-family: monospace; font-size: 0.95rem;">
                                - <?php echo number_format($d['valor'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-center">
                                <?php if(!empty($d['comprovante'])): ?>
                                    <a href="<?php echo $d['comprovante']; ?>" target="_blank" class="btn btn-sm btn-light text-secondary" title="Ver Comprovante">
                                        <i class="fas fa-paperclip"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted opacity-25"><i class="fas fa-ban"></i></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <a href="?excluir=<?php echo $d['id']; ?>" class="btn btn-sm btn-link text-danger p-0" onclick="return confirm('Tem certeza que deseja apagar este registro?');">
                                    <i class="far fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($lista_despesas)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">Nenhuma despesa registrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Lógica de Filtro compatível com Select2
const projetosDb = <?php echo json_encode($todos_projetos); ?>;

// Espera a página (e o jQuery) carregar
document.addEventListener('DOMContentLoaded', function() {
    
    // Função global para o onchange funcionar
    window.filtrarProjetos = function() {
        // Usa jQuery para pegar o valor do Select2
        const cliId = $('#cliente_id').val();
        const $selectProj = $('#projeto_id');
        
        // Limpa opções
        $selectProj.empty().append('<option value="">Sem Projeto Específico</option>');
        
        if(!cliId) { 
            $selectProj.prop('disabled', true); 
        } else {
            const projs = projetosDb.filter(p => p.cliente_id == cliId);
            
            if (projs.length > 0) {
                projs.forEach(p => {
                    $selectProj.append(new Option(p.nome, p.id));
                });
                $selectProj.prop('disabled', false);
            } else {
                $selectProj.append(new Option("Nenhum projeto encontrado", ""));
                $selectProj.prop('disabled', true);
            }
        }
        
        // Avisa o Select2 que mudou
        $selectProj.trigger('change');
    };
    
    // Inicializa Select2 manualmente aqui para garantir
    $('#cliente_id, #projeto_id').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Selecione...'
    });
});
</script>

<?php require 'includes/footer.php'; ?>