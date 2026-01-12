<?php
require 'config/db.php';
require 'includes/header.php';

$mensagem = '';

// 1. SALVAR DESPESA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valor'])) {
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $projeto_id = filter_input(INPUT_POST, 'projeto_id', FILTER_VALIDATE_INT) ?: NULL;
    $descricao  = filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_SPECIAL_CHARS);
    $valor      = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
    $data       = $_POST['data'];

    if ($cliente_id && $valor && $descricao) {
        try {
            $sql = "INSERT INTO despesas (usuario_id, cliente_id, projeto_id, descricao, valor, data_despesa) 
                    VALUES (:uid, :cid, :pid, :desc, :val, :data)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':uid' => $_SESSION['usuario_id'],
                ':cid' => $cliente_id,
                ':pid' => $projeto_id,
                ':desc' => $descricao,
                ':val' => $valor,
                ':data' => $data
            ]);
            $mensagem = '<div class="alert alert-success">Despesa registrada!</div>';
        } catch (PDOException $e) {
            $mensagem = '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
        }
    }
}

// 2. EXCLUIR DESPESA
if (isset($_GET['excluir'])) {
    $id = filter_input(INPUT_GET, 'excluir', FILTER_VALIDATE_INT);
    $pdo->prepare("DELETE FROM despesas WHERE id = :id AND usuario_id = :uid")->execute([':id' => $id, ':uid' => $_SESSION['usuario_id']]);
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
$stmtL->execute([':uid' => $_SESSION['usuario_id']]);
$lista_despesas = $stmtL->fetchAll();
?>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4 border-danger">
            <div class="card-header bg-danger text-white"><i class="fas fa-money-bill-wave"></i> Nova Despesa</div>
            <div class="card-body">
                <?php echo $mensagem; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label>Cliente</label>
                        <select name="cliente_id" id="cliente_id" class="form-select" required onchange="filtrarProjetos()">
                            <option value="">Selecione...</option>
                            <?php foreach($clientes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo $c['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label>Projeto <small class="text-muted">(Opcional)</small></label>
                        <select name="projeto_id" id="projeto_id" class="form-select" disabled>
                            <option value="">Selecione um cliente...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Descrição do Custo</label>
                        <input type="text" name="descricao" class="form-control" placeholder="Ex: Servidor AWS, Freelancer..." required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Valor</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="valor" class="form-control" step="0.01" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Data</label>
                            <input type="date" name="data" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger w-100">Registrar Saída</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header">Últimas Despesas Lançadas</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Cliente / Projeto</th>
                            <th>Descrição</th>
                            <th class="text-end">Valor</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($lista_despesas as $d): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($d['data_despesa'])); ?></td>
                            <td>
                                <strong><?php echo $d['nome_cliente']; ?></strong><br>
                                <small class="text-muted"><?php echo $d['nome_projeto'] ?? '-'; ?></small>
                            </td>
                            <td><?php echo $d['descricao']; ?></td>
                            <td class="text-end text-danger fw-bold">
                                - <?php echo number_format($d['valor'], 2, ',', '.'); ?>
                            </td>
                            <td class="text-end">
                                <a href="?excluir=<?php echo $d['id']; ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Apagar registro?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const projetosDb = <?php echo json_encode($todos_projetos); ?>;
function filtrarProjetos() {
    const cliId = document.getElementById('cliente_id').value;
    const selectProj = document.getElementById('projeto_id');
    selectProj.innerHTML = '<option value="">Sem Projeto Específico</option>';
    
    if(!cliId) { selectProj.disabled = true; return; }

    const projs = projetosDb.filter(p => p.cliente_id == cliId);
    projs.forEach(p => {
        let opt = document.createElement('option');
        opt.value = p.id;
        opt.innerText = p.nome;
        selectProj.appendChild(opt);
    });
    selectProj.disabled = false;
}
</script>

<?php require 'includes/footer.php'; ?>