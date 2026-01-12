<?php
require 'config/db.php';
require_once 'includes/auth.php';

$tarefa_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$mensagem = '';

if (!$tarefa_id) { header('Location: relatorios.php'); exit; }

// --- PROCESSAR ATUALIZAÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Atualizar Cabeçalho
    if (isset($_POST['descricao'])) {
        $desc = $_POST['descricao'];
        $cli_id = $_POST['cliente_id'];
        $proj_id = !empty($_POST['projeto_id']) ? $_POST['projeto_id'] : NULL; // Recebe Projeto
        $status_pag = $_POST['status_pagamento'];
        
        $stmtUpdate = $pdo->prepare("UPDATE tarefas SET descricao = :desc, cliente_id = :cid, projeto_id = :pid, status_pagamento = :sp WHERE id = :tid");
        $stmtUpdate->execute([':desc' => $desc, ':cid' => $cli_id, ':pid' => $proj_id, ':sp' => $status_pag, ':tid' => $tarefa_id]);
    }

    // B. Logs de Tempo (Igual ao anterior)
    if (isset($_POST['log_id'])) {
        $ids = $_POST['log_id'];
        $inicios = $_POST['inicio'];
        $fins = $_POST['fim'];
        $sqlLog = "UPDATE tempo_logs SET inicio = :ini, fim = :fim WHERE id = :lid AND tarefa_id = :tid";
        $stmtLog = $pdo->prepare($sqlLog);
        foreach ($ids as $index => $log_id) {
            $stmtLog->execute([':ini' => $inicios[$index], ':fim' => $fins[$index] ?: NULL, ':lid' => $log_id, ':tid' => $tarefa_id]);
        }
        $mensagem = '<div class="alert alert-success">Tarefa atualizada com sucesso!</div>';
    }

    // C. Excluir Log
    if (isset($_POST['excluir_log_id'])) {
        $stmtDel = $pdo->prepare("DELETE FROM tempo_logs WHERE id = :lid AND tarefa_id = :tid");
        $stmtDel->execute([':lid' => $_POST['excluir_log_id'], ':tid' => $tarefa_id]);
        $mensagem = '<div class="alert alert-warning">Sessão removida.</div>';
    }
}

// --- BUSCAR DADOS ---
$tarefa = $pdo->prepare("SELECT * FROM tarefas WHERE id = :id");
$tarefa->execute([':id' => $tarefa_id]);
$tarefa = $tarefa->fetch();

$logs = $pdo->prepare("SELECT * FROM tempo_logs WHERE tarefa_id = :id ORDER BY inicio ASC");
$logs->execute([':id' => $tarefa_id]);
$logs = $logs->fetchAll();

$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome")->fetchAll();
$todos_projetos = $pdo->query("SELECT * FROM projetos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>

<div class="row">
    <div class="col-md-12 mb-3">
        <a href="relatorios.php" class="btn btn-outline-secondary btn-sm">&larr; Voltar aos Relatórios</a>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">Editar Detalhes</div>
            <div class="card-body">
                <?php echo $mensagem; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label>Cliente</label>
                        <select name="cliente_id" id="cliente_id" class="form-select" onchange="filtrarProjetos()">
                            <?php foreach($clientes as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $tarefa['cliente_id']) ? 'selected' : ''; ?>>
                                    <?php echo $c['nome']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Projeto</label>
                        <select name="projeto_id" id="projeto_id" class="form-select">
                            <option value="">Carregando...</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="fw-bold text-success">Status Financeiro</label>
                        <select name="status_pagamento" class="form-select border-success">
                            <option value="pendente" <?php echo ($tarefa['status_pagamento'] == 'pendente') ? 'selected' : ''; ?>>🕒 Pendente</option>
                            <option value="faturado" <?php echo ($tarefa['status_pagamento'] == 'faturado') ? 'selected' : ''; ?>>📄 Faturado</option>
                            <option value="pago" <?php echo ($tarefa['status_pagamento'] == 'pago') ? 'selected' : ''; ?>>✅ Pago</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Descrição</label>
                        <textarea name="descricao" class="form-control" rows="4"><?php echo htmlspecialchars($tarefa['descricao']); ?></textarea>
                    </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">Sessões de Tempo</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Início</th><th>Fim</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach($logs as $log): 
                                $valInicio = date('Y-m-d\TH:i:s', strtotime($log['inicio']));
                                $valFim = $log['fim'] ? date('Y-m-d\TH:i:s', strtotime($log['fim'])) : '';
                            ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="log_id[]" value="<?php echo $log['id']; ?>">
                                    <input type="datetime-local" name="inicio[]" class="form-control form-control-sm" step="1" value="<?php echo $valInicio; ?>">
                                </td>
                                <td>
                                    <input type="datetime-local" name="fim[]" class="form-control form-control-sm" step="1" value="<?php echo $valFim; ?>">
                                </td>
                                <td>
                                    <button type="submit" name="excluir_log_id" value="<?php echo $log['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Apagar?');"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save"></i> Salvar Tudo</button>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const projetosDb = <?php echo json_encode($todos_projetos); ?>;
// Pega o ID do projeto atual desta tarefa (pode ser nulo)
const projetoAtualId = "<?php echo $tarefa['projeto_id']; ?>"; 

function filtrarProjetos(selecionarId = null) {
    const cliId = document.getElementById('cliente_id').value;
    const selectProj = document.getElementById('projeto_id');
    
    selectProj.innerHTML = '<option value="">Sem Projeto Específico</option>';
    
    if(!cliId) {
        selectProj.disabled = true;
        return;
    }

    const projsDoCliente = projetosDb.filter(p => p.cliente_id == cliId);
    
    projsDoCliente.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.innerText = p.nome;
        
        // Se foi passado um ID para selecionar OU se é o projeto atual salvo
        if (p.id == selecionarId) {
            opt.selected = true;
        }
        
        selectProj.appendChild(opt);
    });
    selectProj.disabled = false;
}

// Ao carregar a página, executa o filtro e seleciona o projeto correto
window.onload = function() {
    filtrarProjetos(projetoAtualId);
};
</script>

<?php require 'includes/footer.php'; ?>