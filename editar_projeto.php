<?php
require 'config/db.php';
require_once 'includes/auth.php';

// Segurança
if (!isset($_SESSION['usuario_permissao']) || $_SESSION['usuario_permissao'] !== 'admin') {
    die("<div class='alert alert-danger m-4'>Acesso negado.</div>");
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header("Location: projetos.php"); exit; }

// Helper para tamanho de arquivo
function formatBytes($bytes, $precision = 2) { 
    $units = ['B', 'KB', 'MB', 'GB']; 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow]; 
}

// 1. SALVAR ALTERAÇÕES (Formulário Principal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome'])) {
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $horas = filter_input(INPUT_POST, 'horas', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? 'ativo';
    $inicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : NULL;
    $fim    = !empty($_POST['data_fim']) ? $_POST['data_fim'] : NULL;
    $desc   = filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_SPECIAL_CHARS);

    if ($nome) {
        $sql = "UPDATE projetos SET nome = :nome, horas_estimadas = :horas, status = :st, 
                data_inicio = :ini, data_fim = :fim, descricao = :desc WHERE id = :id";
        try {
            $pdo->prepare($sql)->execute([':nome'=>$nome, ':horas'=>$horas, ':st'=>$status, ':ini'=>$inicio, ':fim'=>$fim, ':desc'=>$desc, ':id'=>$id]);
            $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Projeto atualizado!'];
            header("Location: projetos.php"); exit;
        } catch (PDOException $e) {
            $erro = "Erro ao salvar: " . $e->getMessage();
        }
    }
}

// 2. BUSCAR DADOS
try {
    $stmt = $pdo->prepare("SELECT * FROM projetos WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $projeto = $stmt->fetch();
    if (!$projeto) die("Projeto não encontrado.");

    $stmtCli = $pdo->prepare("SELECT nome FROM clientes WHERE id = :cid");
    $stmtCli->execute([':cid' => $projeto['cliente_id']]);
    $cliente_nome = $stmtCli->fetchColumn() ?: 'Desconhecido';

    // Checklist
    $checklist = [];
    try {
        $stmtCheck = $pdo->prepare("SELECT * FROM checklist_projetos WHERE projeto_id = :pid ORDER BY concluido ASC, id DESC");
        $stmtCheck->execute([':pid' => $id]);
        $checklist = $stmtCheck->fetchAll();
    } catch (PDOException $e) { $erro_checklist = "Tabela checklist ausente."; }

    // Arquivos (Novo!)
    $arquivos = [];
    try {
        $stmtArq = $pdo->prepare("SELECT * FROM projeto_arquivos WHERE projeto_id = :pid ORDER BY id DESC");
        $stmtArq->execute([':pid' => $id]);
        $arquivos = $stmtArq->fetchAll();
    } catch (PDOException $e) { $erro_arquivos = "Tabela arquivos ausente."; }

} catch (Exception $e) { die("Erro crítico: " . $e->getMessage()); }

require 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        
        <div class="col-lg-5 mb-4">
            <div class="d-flex align-items-center mb-3">
                <a href="projetos.php" class="btn btn-outline-secondary me-3"><i class="fas fa-arrow-left"></i> Voltar</a>
                <div>
                    <small class="text-muted text-uppercase">Cliente: <strong><?php echo htmlspecialchars($cliente_nome); ?></strong></small>
                    <h4 class="mb-0 fw-bold text-truncate" style="max-width: 300px;"><?php echo htmlspecialchars($projeto['nome']); ?></h4>
                </div>
            </div>

            <?php if(isset($erro)) echo "<div class='alert alert-danger'>$erro</div>"; ?>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3">Dados Gerais</div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Nome</label>
                            <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($projeto['nome']); ?>" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><label class="small fw-bold text-muted">Início</label><input type="date" name="data_inicio" class="form-control" value="<?php echo $projeto['data_inicio']; ?>"></div>
                            <div class="col-6"><label class="small fw-bold text-muted">Prazo</label><input type="date" name="data_fim" class="form-control" value="<?php echo $projeto['data_fim']; ?>"></div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><label class="small fw-bold text-muted">Orçamento (h)</label><input type="number" name="horas" class="form-control" value="<?php echo $projeto['horas_estimadas']; ?>"></div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted">Status</label>
                                <select name="status" class="form-select">
                                    <option value="ativo" <?php echo ($projeto['status']=='ativo')?'selected':''; ?>>Ativo</option>
                                    <option value="arquivado" <?php echo ($projeto['status']=='arquivado')?'selected':''; ?>>Arquivado</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Descrição</label>
                            <textarea name="descricao" class="form-control" rows="4"><?php echo htmlspecialchars($projeto['descricao']); ?></textarea>
                        </div>
                        <div class="d-grid"><button type="submit" class="btn btn-primary fw-bold">Salvar Dados</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-tasks text-primary me-2"></i> Checklist</span>
                    <span class="badge bg-light text-dark border"><?php echo count($checklist); ?></span>
                </div>
                <div class="card-body bg-light">
                    <div class="input-group mb-3 shadow-sm">
                        <input type="text" id="nova-tarefa" class="form-control border-0 p-3" placeholder="Adicionar tarefa..." onkeypress="if(event.key === 'Enter') addChecklist()">
                        <button class="btn btn-primary px-4" onclick="addChecklist()"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="list-group shadow-sm" id="lista-checklist">
                        <?php foreach($checklist as $item): 
                            $isDone = ($item['concluido'] == 1 || $item['status'] == 'done');
                        ?>
                        <div class="list-group-item d-flex align-items-center justify-content-between p-3 border-0 border-bottom <?php echo $isDone?'bg-light':'bg-white'; ?>" id="item-<?php echo $item['id']; ?>">
                            <div class="d-flex align-items-center flex-grow-1">
                                <input class="form-check-input me-3 fs-5" type="checkbox" onchange="toggleChecklist(<?php echo $item['id']; ?>, this)" <?php echo $isDone?'checked':''; ?>>
                                <span class="<?php echo $isDone?'text-decoration-line-through text-muted opacity-50 fw-bold':'fw-bold'; ?>" id="texto-<?php echo $item['id']; ?>"><?php echo $item['descricao']; ?></span>
                            </div>
                            <button class="btn btn-sm text-danger opacity-25 hover-opacity-100" onclick="deleteChecklist(<?php echo $item['id']; ?>)"><i class="fas fa-trash"></i></button>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($checklist)) echo '<div class="text-center py-4 text-muted small">Nenhuma tarefa.</div>'; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-folder-open text-warning me-2"></i> Arquivos & Anexos</span>
                    <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('input-arquivo').click()">
                        <i class="fas fa-cloud-upload-alt me-1"></i> Upload
                    </button>
                    <input type="file" id="input-arquivo" class="d-none" onchange="uploadArquivo()">
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light small">
                                <tr><th class="ps-4">Nome</th><th>Tamanho</th><th>Cliente Vê?</th><th class="text-end pe-4">Ação</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($arquivos as $arq): 
                                    $ext = pathinfo($arq['nome_original'], PATHINFO_EXTENSION);
                                    $icon = match($ext) { 'pdf'=>'fa-file-pdf text-danger', 'zip'=>'fa-file-archive text-warning', 'jpg'=>'fa-file-image text-success', 'png'=>'fa-file-image text-success', 'doc'=>'fa-file-word text-primary', 'docx'=>'fa-file-word text-primary', default=>'fa-file text-secondary' };
                                ?>
                                <tr id="arq-<?php echo $arq['id']; ?>">
                                    <td class="ps-4">
                                        <a href="<?php echo $arq['caminho']; ?>" target="_blank" class="text-decoration-none fw-bold text-dark">
                                            <i class="fas <?php echo $icon; ?> me-2"></i> <?php echo $arq['nome_original']; ?>
                                        </a>
                                    </td>
                                    <td class="small text-muted"><?php echo formatBytes($arq['tamanho']); ?></td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" onchange="toggleVisibilidade(<?php echo $arq['id']; ?>, this)" <?php echo $arq['visivel_cliente']?'checked':''; ?>>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm text-danger opacity-50 hover-opacity-100" onclick="deleteArquivo(<?php echo $arq['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($arquivos)): ?>
                                    <tr><td colspan="4" class="text-center py-5 text-muted"><i class="fas fa-cloud-upload-alt fa-2x mb-2 opacity-25"></i><br>Nenhum arquivo.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
const projId = <?php echo $id; ?>;

// --- CHECKLIST JS ---
function addChecklist() {
    const desc = document.getElementById('nova-tarefa').value.trim();
    if(!desc) return;
    fetch('api.php', { method: 'POST', body: JSON.stringify({ acao: 'add_checklist', projeto_id: projId, descricao: desc }) })
    .then(() => location.reload());
}
function toggleChecklist(id, cb) {
    document.getElementById('texto-'+id).classList.toggle('text-decoration-line-through', cb.checked);
    document.getElementById('texto-'+id).classList.toggle('opacity-50', cb.checked);
    document.getElementById('item-'+id).classList.toggle('bg-light', cb.checked);
    fetch('api.php', { method: 'POST', body: JSON.stringify({ acao: 'toggle_checklist', id: id, concluido: cb.checked }) });
}
function deleteChecklist(id) {
    if(confirm('Excluir tarefa?')) fetch('api.php', { method: 'POST', body: JSON.stringify({ acao: 'delete_checklist', id: id }) }).then(() => document.getElementById('item-'+id).remove());
}

// --- ARQUIVOS JS (NOVO!) ---
function uploadArquivo() {
    const input = document.getElementById('input-arquivo');
    if (input.files && input.files[0]) {
        const formData = new FormData();
        formData.append('acao', 'upload_arquivo'); // Para API saber
        formData.append('projeto_id', projId);
        formData.append('arquivo', input.files[0]);

        // Feedback visual
        const btn = document.querySelector('button[onclick*="input-arquivo"]');
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
        btn.disabled = true;

        fetch('api.php', {
            method: 'POST',
            body: formData // Não usar Content-Type header com FormData!
        })
        .then(r => r.json())
        .then(data => {
            if(data.sucesso) location.reload();
            else { alert('Erro: ' + data.msg); btn.innerHTML = oldHtml; btn.disabled = false; }
        })
        .catch(err => { alert('Erro de conexão'); btn.innerHTML = oldHtml; btn.disabled = false; });
    }
}

function deleteArquivo(id) {
    if(confirm('Excluir arquivo permanentemente?')) {
        fetch('api.php', { method: 'POST', body: JSON.stringify({ acao: 'delete_arquivo', id: id }) })
        .then(r => r.json())
        .then(data => { if(data.sucesso) document.getElementById('arq-'+id).remove(); });
    }
}

function toggleVisibilidade(id, cb) {
    fetch('api.php', { method: 'POST', body: JSON.stringify({ acao: 'toggle_visibilidade_arquivo', id: id, visivel: cb.checked }) });
}
</script>

<?php require 'includes/footer.php'; ?>