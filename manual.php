<?php
require 'config/db.php';
require_once 'includes/auth.php';

$mensagem = '';

// 1. PROCESSAR FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $projeto_id = filter_input(INPUT_POST, 'projeto_id', FILTER_VALIDATE_INT) ?: NULL; // Se vazio, vira NULL
    $descricao = filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_SPECIAL_CHARS);
    $data = $_POST['data'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fim = $_POST['hora_fim'];

    if ($cliente_id && $descricao && $hora_inicio && $hora_fim) {
        $inicio_completo = $data . ' ' . $hora_inicio . ':00';
        $fim_completo = $data . ' ' . $hora_fim . ':00';

        if (strtotime($fim_completo) > strtotime($inicio_completo)) {
            try {
                // Inserir Tarefa (Agora com projeto_id)
                $sql = "INSERT INTO tarefas (usuario_id, cliente_id, projeto_id, descricao, status, data_criacao, data_finalizacao, status_pagamento) 
                        VALUES (:uid, :cid, :pid, :desc, 'finalizado', :inicio, :fim, 'pendente')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':uid' => $_SESSION['usuario_id'],
                    ':cid' => $cliente_id,
                    ':pid' => $projeto_id,
                    ':desc' => $descricao,
                    ':inicio' => $inicio_completo,
                    ':fim' => $fim_completo
                ]);
                $tarefa_id = $pdo->lastInsertId();

                // Inserir Log de Tempo
                $sqlLog = "INSERT INTO tempo_logs (tarefa_id, inicio, fim) VALUES (:tid, :inicio, :fim)";
                $stmtLog = $pdo->prepare($sqlLog);
                $stmtLog->execute([':tid' => $tarefa_id, ':inicio' => $inicio_completo, ':fim' => $fim_completo]);

                $mensagem = '<div class="alert alert-success">Atividade lançada com sucesso!</div>';
            } catch (PDOException $e) {
                $mensagem = '<div class="alert alert-danger">Erro no banco de dados: ' . $e->getMessage() . '</div>';
            }
        } else {
            $mensagem = '<div class="alert alert-warning">A hora fim deve ser maior que a hora de início.</div>';
        }
    }
}

// 2. BUSCAR DADOS
$clientes = $pdo->query("SELECT * FROM clientes WHERE status = 'ativo' ORDER BY nome")->fetchAll();
$todos_projetos = $pdo->query("SELECT * FROM projetos WHERE status = 'ativo' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

// Autocomplete
$stmtSug = $pdo->prepare("SELECT DISTINCT descricao FROM tarefas WHERE usuario_id = :uid ORDER BY id DESC LIMIT 20");
$stmtSug->execute([':uid' => $_SESSION['usuario_id']]);
$sugestoes = $stmtSug->fetchAll(PDO::FETCH_COLUMN);

require 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <i class="fas fa-plus-circle"></i> Lançamento Manual
            </div>
            <div class="card-body">
                <p class="text-muted small">Registe trabalhos feitos fora do sistema.</p>
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
                            <option value="">Selecione um cliente primeiro</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Descrição</label>
                        <input type="text" name="descricao" class="form-control" list="lista-descricoes" required>
                        <datalist id="lista-descricoes">
                            <?php foreach($sugestoes as $sug): echo "<option value='".htmlspecialchars($sug)."'>"; endforeach; ?>
                        </datalist>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Data</label>
                            <input type="date" name="data" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Início</label>
                            <input type="time" name="hora_inicio" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Fim</label>
                            <input type="time" name="hora_fim" class="form-control" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100">Registrar Horas</button>
                </form>
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
    
    if(!cliId) {
        selectProj.disabled = true;
        return;
    }

    const projsDoCliente = projetosDb.filter(p => p.cliente_id == cliId);
    
    projsDoCliente.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.innerText = p.nome;
        selectProj.appendChild(opt);
    });
    
    selectProj.disabled = false;
}
</script>

<?php require 'includes/footer.php'; ?>