<?php
require 'config/db.php';
require_once 'includes/auth.php';

$tarefa_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$mensagem = '';

if (!$tarefa_id) { header('Location: relatorios.php'); exit; }

// --- PROCESSAR ATUALIZAÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Atualizar Detalhes da Tarefa
    if (isset($_POST['descricao'])) {
        $desc = $_POST['descricao'];
        $cli_id = $_POST['cliente_id'];
        $proj_id = !empty($_POST['projeto_id']) ? $_POST['projeto_id'] : NULL;
        $status_pag = $_POST['status_pagamento'];
        
        $stmtUpdate = $pdo->prepare("UPDATE tarefas SET descricao = :desc, cliente_id = :cid, projeto_id = :pid, status_pagamento = :sp WHERE id = :tid");
        $stmtUpdate->execute([':desc' => $desc, ':cid' => $cli_id, ':pid' => $proj_id, ':sp' => $status_pag, ':tid' => $tarefa_id]);
    }

    // B. Atualizar Logs de Tempo
    if (isset($_POST['log_id'])) {
        $ids = $_POST['log_id'];
        $inicios = $_POST['inicio'];
        $fins = $_POST['fim'];
        
        $sqlLog = "UPDATE tempo_logs SET inicio = :ini, fim = :fim WHERE id = :lid AND tarefa_id = :tid";
        $stmtLog = $pdo->prepare($sqlLog);
        
        foreach ($ids as $index => $log_id) {
            $valFim = !empty($fins[$index]) ? $fins[$index] : NULL;
            $stmtLog->execute([':ini' => $inicios[$index], ':fim' => $valFim, ':lid' => $log_id, ':tid' => $tarefa_id]);
        }
        $mensagem = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i> Tarefa atualizada com sucesso!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }

    // C. Excluir Log Específico
    if (isset($_POST['excluir_log_id'])) {
        $stmtDel = $pdo->prepare("DELETE FROM tempo_logs WHERE id = :lid AND tarefa_id = :tid");
        $stmtDel->execute([':lid' => $_POST['excluir_log_id'], ':tid' => $tarefa_id]);
        $mensagem = '<div class="alert alert-warning alert-dismissible fade show"><i class="fas fa-trash me-2"></i> Sessão de tempo removida.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// --- BUSCAR DADOS ---
// Busca Tarefa com Nome do Cliente
$stmt = $pdo->prepare("SELECT t.*, c.nome as nome_cliente FROM tarefas t JOIN clientes c ON t.cliente_id = c.id WHERE t.id = :id");
$stmt->execute([':id' => $tarefa_id]);
$tarefa = $stmt->fetch();

if (!$tarefa) die("Tarefa não encontrada.");

// Busca Logs
$logs = $pdo->prepare("SELECT * FROM tempo_logs WHERE tarefa_id = :id ORDER BY inicio ASC");
$logs->execute([':id' => $tarefa_id]);
$logs = $logs->fetchAll();

// Listas para Dropdowns
$clientes = $pdo->query("SELECT * FROM clientes WHERE status='ativo' ORDER BY nome")->fetchAll();
$todos_projetos = $pdo->query("SELECT * FROM projetos WHERE status='ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';

// Helper para calcular duração visual
function calcularDuracao($inicio, $fim) {
    $dtIni = new DateTime($inicio);
    $dtFim = $fim ? new DateTime($fim) : new DateTime();
    $diff = $dtIni->diff($dtFim);
    return $diff->format('%H:%I:%S');
}

// Calcula total geral
$totalSegundos = 0;
foreach($logs as $l) {
    $i = strtotime($l['inicio']);
    $f = $l['fim'] ? strtotime($l['fim']) : time();
    $totalSegundos += ($f - $i);
}
$horasTotal = floor($totalSegundos / 3600);
$minutosTotal = floor(($totalSegundos % 3600) / 60);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="relatorios.php" class="btn btn-outline-secondary me-3 shadow-sm"><i class="fas fa-arrow-left"></i></a>
            <div>
                <small class="text-muted text-uppercase fw-bold">ID: #<?php echo str_pad($tarefa['id'], 4, '0', STR_PAD_LEFT); ?></small>
                <h4 class="mb-0 fw-bold text-dark">Editar Tarefa</h4>
            </div>
        </div>
        <div class="text-end">
             <span class="badge bg-light text-dark border p-2">
                <i class="fas fa-clock text-primary me-1"></i> Total: <strong><?php echo sprintf("%02d:%02d", $horasTotal, $minutosTotal); ?> h</strong>
            </span>
        </div>
    </div>

    <?php echo $mensagem; ?>

    <form method="POST">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold py-3 text-primary">
                        <i class="fas fa-info-circle me-2"></i> Dados Principais
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="small fw-bold text-muted">Cliente</label>
                            <select name="cliente_id" id="cliente_id" class="form-select" onchange="filtrarProjetos()">
                                <?php foreach($clientes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $tarefa['cliente_id']) ? 'selected' : ''; ?>>
                                        <?php echo $c['nome']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted">Projeto</label>
                            <select name="projeto_id" id="projeto_id" class="form-select" disabled>
                                <option value="">Selecione um cliente...</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="small fw-bold text-muted">Status Financeiro</label>
                            <select name="status_pagamento" class="form-select">
                                <option value="pendente" <?php echo ($tarefa['status_pagamento'] == 'pendente') ? 'selected' : ''; ?>>🕒 Pendente</option>
                                <option value="faturado" <?php echo ($tarefa['status_pagamento'] == 'faturado') ? 'selected' : ''; ?>>📄 Faturado</option>
                                <option value="pago" <?php echo ($tarefa['status_pagamento'] == 'pago') ? 'selected' : ''; ?>>✅ Pago</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="5"><?php echo htmlspecialchars($tarefa['descricao']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-history text-warning me-2"></i> Sessões de Tempo</span>
                        <span class="badge bg-secondary"><?php echo count($logs); ?> registros</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light small text-muted">
                                    <tr>
                                        <th class="ps-3">Início</th>
                                        <th>Fim</th>
                                        <th>Duração</th>
                                        <th class="text-end pe-3">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($logs as $log): 
                                        $valInicio = date('Y-m-d\TH:i:s', strtotime($log['inicio']));
                                        $valFim = $log['fim'] ? date('Y-m-d\TH:i:s', strtotime($log['fim'])) : '';
                                        $duracao = calcularDuracao($log['inicio'], $log['fim']);
                                    ?>
                                    <tr>
                                        <td class="ps-3">
                                            <input type="hidden" name="log_id[]" value="<?php echo $log['id']; ?>">
                                            <input type="datetime-local" name="inicio[]" class="form-control form-control-sm" step="1" value="<?php echo $valInicio; ?>" style="width: 200px;">
                                        </td>
                                        <td>
                                            <input type="datetime-local" name="fim[]" class="form-control form-control-sm" step="1" value="<?php echo $valFim; ?>" style="width: 200px;">
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace">
                                                <?php echo $duracao; ?>
                                                <?php if(!$log['fim']): ?> <i class="fas fa-spinner fa-spin text-primary ms-1"></i><?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <button type="submit" name="excluir_log_id" value="<?php echo $log['id']; ?>" class="btn btn-sm text-danger opacity-50 hover-opacity-100" onclick="return confirm('Tem certeza que deseja apagar este registro de tempo? Isso afetará o cálculo total.');" title="Excluir Sessão">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white p-3">
                        <button type="submit" class="btn btn-success fw-bold w-100 shadow-sm">
                            <i class="fas fa-save me-2"></i> Salvar Todas as Alterações
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Dados do PHP para o JS
const projetosDb = <?php echo json_encode($todos_projetos); ?>;
const projetoAtualId = "<?php echo $tarefa['projeto_id']; ?>"; 

document.addEventListener('DOMContentLoaded', function() {
    
    // Função global para filtrar (Compatível com Select2)
    window.filtrarProjetos = function() {
        // Usa jQuery para conversar com Select2
        const cliId = $('#cliente_id').val();
        const $selectProj = $('#projeto_id');
        
        $selectProj.empty().append('<option value="">Sem Projeto Específico</option>');
        
        if(!cliId) {
            $selectProj.prop('disabled', true);
        } else {
            const projs = projetosDb.filter(p => p.cliente_id == cliId);
            
            projs.forEach(p => {
                // new Option(text, value, defaultSelected, selected)
                // Se for o projeto atual salvo no banco, marca como selecionado
                const isSelected = (p.id == projetoAtualId);
                const option = new Option(p.nome, p.id, isSelected, isSelected);
                $selectProj.append(option);
            });
            $selectProj.prop('disabled', false);
        }
        
        // Dispara evento para o Select2 atualizar visualmente
        $selectProj.trigger('change');
    };

    // Inicializa Select2
    $('#cliente_id, #projeto_id').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Selecione...'
    });

    // Chama o filtro na carga inicial para preencher o projeto correto
    filtrarProjetos();
});
</script>

<?php require 'includes/footer.php'; ?>