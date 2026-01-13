<?php
require 'config/db.php';
require_once 'includes/auth.php';

$mensagem = '';
$uid = $_SESSION['usuario_id'];

// 1. PROCESSAR FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $projeto_id = filter_input(INPUT_POST, 'projeto_id', FILTER_VALIDATE_INT) ?: NULL;
    $descricao  = filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_SPECIAL_CHARS);
    $data       = $_POST['data'];
    $hora_inicio= $_POST['hora_inicio'];
    $hora_fim   = $_POST['hora_fim'];

    if ($cliente_id && $descricao && $hora_inicio && $hora_fim) {
        $inicio_completo = $data . ' ' . $hora_inicio . ':00';
        $fim_completo    = $data . ' ' . $hora_fim . ':00';

        if (strtotime($fim_completo) > strtotime($inicio_completo)) {
            try {
                // INÍCIO DA TRANSAÇÃO (Tudo ou Nada)
                $pdo->beginTransaction();

                // 1. Cria a Tarefa
                $sql = "INSERT INTO tarefas (usuario_id, cliente_id, projeto_id, descricao, status, data_criacao, data_finalizacao, status_pagamento) 
                        VALUES (:uid, :cid, :pid, :desc, 'finalizado', :inicio, :fim, 'pendente')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':uid' => $uid,
                    ':cid' => $cliente_id,
                    ':pid' => $projeto_id,
                    ':desc' => $descricao,
                    ':inicio' => $inicio_completo,
                    ':fim' => $fim_completo
                ]);
                $tarefa_id = $pdo->lastInsertId();

                // 2. Cria o Log de Tempo
                $sqlLog = "INSERT INTO tempo_logs (tarefa_id, inicio, fim) VALUES (:tid, :inicio, :fim)";
                $stmtLog = $pdo->prepare($sqlLog);
                $stmtLog->execute([':tid' => $tarefa_id, ':inicio' => $inicio_completo, ':fim' => $fim_completo]);

                $pdo->commit();
                // FIM DA TRANSAÇÃO

                $mensagem = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i> Horas lançadas com sucesso!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            } catch (Exception $e) {
                $pdo->rollBack();
                $mensagem = '<div class="alert alert-danger">Erro ao salvar: ' . $e->getMessage() . '</div>';
            }
        } else {
            $mensagem = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i> A hora final deve ser maior que a inicial.</div>';
        }
    }
}

// 2. BUSCAR DADOS
$clientes = $pdo->query("SELECT * FROM clientes WHERE status = 'ativo' ORDER BY nome")->fetchAll();
$todos_projetos = $pdo->query("SELECT * FROM projetos WHERE status = 'ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Autocomplete Inteligente (Últimas 20 descrições)
$stmtSug = $pdo->prepare("SELECT DISTINCT descricao FROM tarefas WHERE usuario_id = :uid ORDER BY id DESC LIMIT 20");
$stmtSug->execute([':uid' => $uid]);
$sugestoes = $stmtSug->fetchAll(PDO::FETCH_COLUMN);

require 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 fw-bold border-bottom">
                <i class="fas fa-pen-fancy text-success me-2"></i> Lançamento Manual
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">Utilize este formulário para registrar atividades realizadas fora do computador ou esquecidas.</p>
                
                <?php echo $mensagem; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Cliente</label>
                        <select name="cliente_id" id="cliente_id" class="form-select" required onchange="filtrarProjetos()">
                            <option value="">Selecione...</option>
                            <?php foreach($clientes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo $c['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Projeto</label>
                        <select name="projeto_id" id="projeto_id" class="form-select" disabled>
                            <option value="">Selecione um cliente...</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="small fw-bold text-muted">O que foi feito?</label>
                        <input type="text" name="descricao" class="form-control" list="lista-descricoes" placeholder="Ex: Reunião presencial..." required>
                        <datalist id="lista-descricoes">
                            <?php foreach($sugestoes as $sug): echo "<option value='".htmlspecialchars($sug)."'>"; endforeach; ?>
                        </datalist>
                    </div>

                    <div class="card bg-light border-0 p-3 mb-4">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="small fw-bold text-muted">Data</label>
                                <input type="date" name="data" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-muted">Início</label>
                                <input type="time" name="hora_inicio" id="hora_inicio" class="form-control" onchange="calcularTotal()" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-muted">Fim</label>
                                <input type="time" name="hora_fim" id="hora_fim" class="form-control" onchange="calcularTotal()" required>
                            </div>
                        </div>
                        <div class="text-end mt-2">
                            <small class="text-muted fw-bold">Total Calculado: <span id="total-horas" class="text-success">00:00</span></small>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success fw-bold py-2">
                            <i class="fas fa-check me-2"></i> Registrar Atividade
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Dados para o filtro
const projetosDb = <?php echo json_encode($todos_projetos); ?>;

document.addEventListener('DOMContentLoaded', function() {
    
    // Filtro Compatível com Select2
    window.filtrarProjetos = function() {
        const cliId = $('#cliente_id').val();
        const $selectProj = $('#projeto_id');
        
        $selectProj.empty().append('<option value="">Sem Projeto Específico</option>');
        
        if(!cliId) {
            $selectProj.prop('disabled', true);
        } else {
            const projs = projetosDb.filter(p => p.cliente_id == cliId);
            projs.forEach(p => {
                $selectProj.append(new Option(p.nome, p.id));
            });
            $selectProj.prop('disabled', false);
        }
        $selectProj.trigger('change');
    };

    // Inicializa Select2
    $('#cliente_id, #projeto_id').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Selecione...'
    });
});

// Cálculo visual de horas
function calcularTotal() {
    const ini = document.getElementById('hora_inicio').value;
    const fim = document.getElementById('hora_fim').value;
    const span = document.getElementById('total-horas');

    if(ini && fim) {
        const d1 = new Date("2000-01-01 " + ini);
        const d2 = new Date("2000-01-01 " + fim);
        
        let diff = d2 - d1;
        if(diff < 0) {
            span.innerText = "Inválido";
            span.className = "text-danger fw-bold";
            return;
        }
        
        let msec = diff;
        const hh = Math.floor(msec / 1000 / 60 / 60);
        msec -= hh * 1000 * 60 * 60;
        const mm = Math.floor(msec / 1000 / 60);
        
        span.innerText = String(hh).padStart(2, '0') + ':' + String(mm).padStart(2, '0');
        span.className = "text-success fw-bold";
    }
}
</script>

<?php require 'includes/footer.php'; ?>