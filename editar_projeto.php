<?php
require 'config/db.php';
require_once 'includes/auth.php';

// Segurança: Apenas Admin
if (!isset($_SESSION['usuario_permissao']) || $_SESSION['usuario_permissao'] !== 'admin') {
    die("<div class='alert alert-danger m-4'>Acesso negado.</div>");
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header("Location: projetos.php"); exit; }

// --- AJAX PARA CHAT ADMIN (Retorna JSON limpo) ---
if (isset($_GET['ajax_chat_admin'])) {
    // Busca mensagens
    $stmt = $pdo->prepare("SELECT * FROM projeto_comentarios WHERE projeto_id = :id ORDER BY id ASC");
    $stmt->execute([':id' => $id]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Limpa buffer e retorna JSON
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json'); 
    echo json_encode($msgs); 
    exit;
}

// Helper para tamanho de arquivo
function formatBytes($bytes, $precision = 2) { 
    $units = ['B', 'KB', 'MB', 'GB']; $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow]; 
}

// 1. SALVAR ALTERAÇÕES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome'])) {
    $nome   = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $horas  = filter_input(INPUT_POST, 'horas', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? 'ativo';
    $inicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : NULL;
    $fim    = !empty($_POST['data_fim']) ? $_POST['data_fim'] : NULL;
    $desc   = filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_SPECIAL_CHARS);

    if ($nome) {
        $pdo->prepare("UPDATE projetos SET nome=:n, horas_estimadas=:h, status=:st, data_inicio=:ini, data_fim=:fim, descricao=:desc WHERE id=:id")
            ->execute([':n'=>$nome, ':h'=>$horas, ':st'=>$status, ':ini'=>$inicio, ':fim'=>$fim, ':desc'=>$desc, ':id'=>$id]);
        $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Projeto atualizado com sucesso!'];
        header("Location: projetos.php"); exit;
    }
}

// 2. BUSCAR DADOS
try {
    // Dados do Projeto + Cliente
    $stmt = $pdo->prepare("SELECT p.*, c.nome as cliente_nome, c.token_acesso, c.id as cliente_id FROM projetos p JOIN clientes c ON p.cliente_id = c.id WHERE p.id = :id");
    $stmt->execute([':id' => $id]);
    $projeto = $stmt->fetch();
    
    if (!$projeto) die("Projeto não encontrado.");

    // Se cliente não tiver token, gera agora (Segurança Melhorada)
    if (empty($projeto['token_acesso'])) {
        $newToken = bin2hex(random_bytes(16)); // Token Forte
        $pdo->prepare("UPDATE clientes SET token_acesso = :t WHERE id = :cid")->execute([':t' => $newToken, ':cid' => $projeto['cliente_id']]);
        $projeto['token_acesso'] = $newToken;
    }

    // Buscas auxiliares (Seguras com Prepared Statements)
    $stmtCheck = $pdo->prepare("SELECT * FROM checklist_projetos WHERE projeto_id = :id ORDER BY concluido ASC, id DESC");
    $stmtCheck->execute([':id' => $id]);
    $checklist = $stmtCheck->fetchAll();

    $stmtArq = $pdo->prepare("SELECT * FROM projeto_arquivos WHERE projeto_id = :id ORDER BY id DESC");
    $stmtArq->execute([':id' => $id]);
    $arquivos = $stmtArq->fetchAll();

} catch (Exception $e) { die("Erro crítico: " . $e->getMessage()); }

require 'includes/header.php';

// Monta Link do Portal Corretamente
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$base_url = rtrim($base_url, '/'); // Remove barra extra se houver
$link_portal = $base_url . "/portal.php?t=" . $projeto['token_acesso'];
?>

<style>
    /* Estilos Chat Moderno */
    .chat-container { height: 350px; overflow-y: auto; background: #f8f9fa; border-radius: 8px; padding: 15px; display: flex; flex-direction: column; gap: 10px; border: 1px solid #e9ecef; }
    .msg { max-width: 80%; padding: 10px 14px; border-radius: 12px; font-size: 0.9rem; position: relative; line-height: 1.4; }
    .msg-admin { align-self: flex-end; background: #cfe2ff; color: #084298; border-bottom-right-radius: 2px; }
    .msg-client { align-self: flex-start; background: #fff; border: 1px solid #dee2e6; color: #495057; border-bottom-left-radius: 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .msg-time { font-size: 0.65rem; opacity: 0.7; margin-top: 4px; display: block; text-align: right; }
    
    /* Scrollbar Bonita */
    .chat-container::-webkit-scrollbar { width: 6px; }
    .chat-container::-webkit-scrollbar-track { background: #f1f1f1; }
    .chat-container::-webkit-scrollbar-thumb { background: #ccc; border-radius: 10px; }
    .chat-container::-webkit-scrollbar-thumb:hover { background: #aaa; }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center">
            <a href="projetos.php" class="btn btn-outline-secondary me-3 shadow-sm"><i class="fas fa-arrow-left"></i></a>
            <div>
                <small class="text-muted text-uppercase fw-bold" style="font-size: 0.75rem;">Cliente: <?php echo htmlspecialchars($projeto['cliente_nome']); ?></small>
                <h4 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($projeto['nome']); ?></h4>
            </div>
        </div>

        <div class="input-group shadow-sm" style="max-width: 450px;">
            <span class="input-group-text bg-white small fw-bold text-muted border-end-0"><i class="fas fa-link me-1"></i> Portal</span>
            <input type="text" class="form-control bg-white text-muted border-start-0 ps-0" value="<?php echo $link_portal; ?>" readonly id="linkPortalInput" style="font-size: 0.85rem;">
            <button class="btn btn-light border" onclick="copiarLink()" title="Copiar Link" data-bs-toggle="tooltip"><i class="far fa-copy text-primary"></i></button>
            <a href="<?php echo $link_portal; ?>" target="_blank" class="btn btn-primary fw-bold"><i class="fas fa-external-link-alt"></i> Abrir</a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-5 mb-4">
            
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3">Dados Gerais</div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Nome do Projeto</label>
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
                        <div class="d-grid"><button type="submit" class="btn btn-primary fw-bold">Salvar Alterações</button></div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-comments text-info me-2"></i> Chat com Cliente</span>
                    <span class="badge bg-info text-dark bg-opacity-10"><i class="fas fa-bolt"></i> Ao Vivo</span>
                </div>
                <div class="card-body p-0">
                    <div class="chat-container" id="adminChat">
                        <div class="text-center mt-5 text-muted small"><i class="fas fa-spinner fa-spin"></i> Carregando...</div>
                    </div>
                    <div class="p-3 bg-white border-top">
                        <div class="input-group">
                            <input type="text" id="msgAdmin" class="form-control" placeholder="Digite uma mensagem..." onkeypress="if(event.key==='Enter') enviarMsg()">
                            <button class="btn btn-primary px-4" onclick="enviarMsg()"><i class="fas fa-paper-plane"></i></button>
                        </div>
                        <div class="form-text mt-1 text-end" style="font-size: 0.7rem;">O cliente receberá uma notificação por e-mail.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-tasks text-success me-2"></i> Checklist</span>
                    <span class="badge bg-light text-dark border" id="count-checklist"><?php echo count($checklist); ?></span>
                </div>
                <div class="card-body bg-light">
                    <div class="input-group mb-3 shadow-sm">
                        <input type="text" id="nova-tarefa" class="form-control border-0 p-3" placeholder="Adicionar nova tarefa..." onkeypress="if(event.key === 'Enter') addChecklist()">
                        <button class="btn btn-success px-4" onclick="addChecklist()"><i class="fas fa-plus"></i></button>
                    </div>
                    <div class="list-group shadow-sm" id="lista-checklist">
                        <?php foreach($checklist as $item): $isDone = ($item['concluido'] == 1 || $item['status'] == 'done'); ?>
                        <div class="list-group-item d-flex align-items-center justify-content-between p-3 border-0 border-bottom <?php echo $isDone?'bg-light':'bg-white'; ?>" id="item-<?php echo $item['id']; ?>">
                            <div class="d-flex align-items-center flex-grow-1">
                                <input class="form-check-input me-3 fs-5" type="checkbox" onchange="toggleChecklist(<?php echo $item['id']; ?>, this)" <?php echo $isDone?'checked':''; ?> style="cursor: pointer;">
                                <span class="<?php echo $isDone?'text-decoration-line-through text-muted opacity-50 fw-bold':'fw-bold text-dark'; ?>" id="texto-<?php echo $item['id']; ?>"><?php echo $item['descricao']; ?></span>
                            </div>
                            <button class="btn btn-sm text-danger opacity-25 hover-opacity-100" onclick="deleteChecklist(<?php echo $item['id']; ?>)" title="Excluir"><i class="fas fa-trash"></i></button>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($checklist)): ?>
                            <div class="text-center p-4 text-muted small" id="empty-check">Nenhuma tarefa criada.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-folder-open text-warning me-2"></i> Arquivos & Anexos</span>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" onclick="document.getElementById('input-arquivo').click()"><i class="fas fa-cloud-upload-alt me-1"></i> Upload</button>
                        <input type="file" id="input-arquivo" class="d-none" onchange="uploadArquivo()">
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-muted text-uppercase"><tr><th class="ps-4">Nome</th><th>Tamanho</th><th>Visível Cliente?</th><th class="text-end pe-4">Ação</th></tr></thead>
                        <tbody>
                            <?php foreach($arquivos as $arq): 
                                $ext = strtolower(pathinfo($arq['nome_original'], PATHINFO_EXTENSION)); 
                                $icon = match($ext) { 'pdf'=>'fa-file-pdf text-danger', 'zip'=>'fa-file-archive text-warning', 'rar'=>'fa-file-archive text-warning', 'jpg'=>'fa-file-image text-success', 'png'=>'fa-file-image text-success', 'doc'=>'fa-file-word text-primary', 'docx'=>'fa-file-word text-primary', 'xls'=>'fa-file-excel text-success', 'xlsx'=>'fa-file-excel text-success', default=>'fa-file text-secondary' }; 
                            ?>
                            <tr id="arq-<?php echo $arq['id']; ?>">
                                <td class="ps-4">
                                    <a href="<?php echo $arq['caminho']; ?>" target="_blank" class="text-dark text-decoration-none fw-bold">
                                        <i class="fas <?php echo $icon; ?> me-2"></i><?php echo $arq['nome_original']; ?>
                                    </a>
                                </td>
                                <td class="small text-muted"><?php echo formatBytes($arq['tamanho']); ?></td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" onchange="toggleVisibilidade(<?php echo $arq['id']; ?>, this)" <?php echo $arq['visivel_cliente']?'checked':''; ?> style="cursor: pointer;">
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm text-danger opacity-50 hover-opacity-100" onclick="deleteArquivo(<?php echo $arq['id']; ?>)"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($arquivos)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted small">Nenhum arquivo anexado.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const projId = <?php echo $id; ?>;
let lastCount = 0;
let isFirstLoad = true;

function copiarLink() {
    var copyText = document.getElementById("linkPortalInput");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value).then(() => {
        // Feedback visual rápido
        const btn = document.querySelector('button[title="Copiar Link"]');
        const icon = btn.querySelector('i');
        icon.className = 'fas fa-check text-success';
        setTimeout(() => icon.className = 'far fa-copy text-primary', 2000);
    });
}

// --- CHAT ADMIN JS ---
async function carregarChat() {
    try {
        const res = await fetch(`editar_projeto.php?id=${projId}&ajax_chat_admin=1`);
        const msgs = await res.json();
        
        if(msgs.length !== lastCount) {
            renderChat(msgs);
            lastCount = msgs.length;
            
            // Scroll para o fim apenas se for msg nova ou primeira carga
            const div = document.getElementById("adminChat");
            div.scrollTop = div.scrollHeight;
        }
    } catch(e) { console.error("Erro chat", e); }
}

function renderChat(msgs) {
    const div = document.getElementById("adminChat");
    if(msgs.length === 0) { div.innerHTML = '<div class="text-center mt-5 text-muted small">Nenhuma mensagem ainda.<br>Envie algo para iniciar.</div>'; return; }
    
    div.innerHTML = msgs.map(m => {
        const isAdmin = (m.autor_tipo === 'admin');
        return `<div class="msg ${isAdmin?'msg-admin':'msg-client'}">
                    ${m.mensagem.replace(/\n/g, '<br>')}
                    <span class="msg-time">${new Date(m.data_criacao).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</span>
                </div>`;
    }).join('');
}

function enviarMsg() {
    const input = document.getElementById('msgAdmin');
    const txt = input.value.trim();
    if(!txt) return;
    
    input.value = '';
    // Headers explícitos para o PHP entender o JSON
    fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ acao: 'add_comentario_admin', projeto_id: projId, mensagem: txt })
    }).then(res => res.json()).then(data => {
        if(data.sucesso) carregarChat();
        else alert('Erro ao enviar mensagem');
    });
}

// Inicia loop do chat
setInterval(carregarChat, 3000); 
carregarChat();

// --- CHECKLIST & ARQUIVOS (API Conectada) ---
function addChecklist() {
    const desc = document.getElementById('nova-tarefa').value.trim();
    if(!desc) return;
    fetch('api.php', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ acao: 'add_checklist', projeto_id: projId, descricao: desc }) 
    }).then(r=>r.json()).then(d => { if(d.sucesso) location.reload(); });
}

function toggleChecklist(id, cb) {
    const texto = document.getElementById('texto-'+id);
    const item = document.getElementById('item-'+id);
    
    if(cb.checked) {
        texto.classList.add('text-decoration-line-through', 'text-muted', 'opacity-50');
        texto.classList.remove('text-dark');
        item.classList.add('bg-light');
        item.classList.remove('bg-white');
    } else {
        texto.classList.remove('text-decoration-line-through', 'text-muted', 'opacity-50');
        texto.classList.add('text-dark');
        item.classList.remove('bg-light');
        item.classList.add('bg-white');
    }
    
    fetch('api.php', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ acao: 'toggle_checklist', id: id, concluido: cb.checked }) 
    });
}

function deleteChecklist(id) {
    if(confirm('Excluir esta tarefa?')) {
        fetch('api.php', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ acao: 'delete_checklist', id: id }) 
        }).then(r=>r.json()).then(d => { if(d.sucesso) document.getElementById('item-'+id).remove(); });
    }
}

function uploadArquivo() {
    const input = document.getElementById('input-arquivo');
    if (input.files && input.files[0]) {
        const fd = new FormData();
        fd.append('acao', 'upload_arquivo'); 
        fd.append('projeto_id', projId); 
        fd.append('arquivo', input.files[0]);
        
        // FormData não precisa de header Content-Type (o browser define boundary)
        fetch('api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { 
            if(d.sucesso) location.reload(); 
            else alert('Erro: ' + d.msg); 
        });
    }
}

function deleteArquivo(id) {
    if(confirm('Excluir arquivo permanentemente?')) {
        fetch('api.php', { 
            method: 'POST', 
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ acao: 'delete_arquivo', id: id }) 
        }).then(r=>r.json()).then(d => { if(d.sucesso) document.getElementById('arq-'+id).remove(); });
    }
}

function toggleVisibilidade(id, cb) {
    fetch('api.php', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ acao: 'toggle_visibilidade_arquivo', id: id, visivel: cb.checked }) 
    });
}
</script>

<?php require 'includes/footer.php'; ?>