<?php
// portal_detalhes.php - Versão Corrigida: Notificação Admin Garantida
require 'config/db.php';

// 1. SEGURANÇA
$token = $_GET['t'] ?? '';
$pid   = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT);

if (!$token || !$pid) die("Acesso inválido.");

// Valida Cliente e Projeto
$stmt = $pdo->prepare("SELECT c.id as cid, c.nome as cliente_nome, c.logo, p.nome as projeto_nome, p.id as pid 
                       FROM clientes c 
                       JOIN projetos p ON p.cliente_id = c.id 
                       WHERE c.token_acesso = :t AND p.id = :pid AND c.status = 'ativo'");
$stmt->execute([':t' => $token, ':pid' => $pid]);
$dados = $stmt->fetch();

if (!$dados) die("Acesso negado.");

// --- AJAX CHAT (NOVAS) ---
if (isset($_GET['ajax_chat_last_id'])) {
    $lastId = filter_input(INPUT_GET, 'ajax_chat_last_id', FILTER_VALIDATE_INT);
    $chat = $pdo->prepare("SELECT * FROM projeto_comentarios WHERE projeto_id = :pid AND id > :lid ORDER BY id ASC");
    $chat->execute([':pid' => $pid, ':lid' => $lastId]);
    echo json_encode($chat->fetchAll(PDO::FETCH_ASSOC)); exit;
}
// --- AJAX CHAT (HISTÓRICO) ---
if (isset($_GET['ajax_chat_history'])) {
    $firstId = filter_input(INPUT_GET, 'first_id', FILTER_VALIDATE_INT);
    $sql = "SELECT * FROM (SELECT * FROM projeto_comentarios WHERE projeto_id = :pid AND id < :fid ORDER BY id DESC LIMIT 20) sub ORDER BY id ASC";
    $stmt = $pdo->prepare($sql); $stmt->execute([':pid' => $pid, ':fid' => $firstId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

// 2. ENVIAR MENSAGEM (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensagem'])) {
    $msg = filter_input(INPUT_POST, 'mensagem', FILTER_SANITIZE_SPECIAL_CHARS);
    if ($msg) {
        // A) Salva no Banco
        $pdo->prepare("INSERT INTO projeto_comentarios (projeto_id, autor_tipo, mensagem) VALUES (:pid, 'cliente', :msg)")
            ->execute([':pid' => $pid, ':msg' => $msg]);

        // B) Envia E-mail para o ADMIN (Lógica Simplificada e Robusta)
        if (file_exists('includes/mailer.php')) {
            require_once 'includes/mailer.php';
            
            // 1. Tenta pegar o primeiro usuário com permissão 'admin'
            $stmtAdmin = $pdo->prepare("SELECT email FROM usuarios WHERE permissao = 'admin' ORDER BY id ASC LIMIT 1");
            $stmtAdmin->execute();
            $emailAdmin = $stmtAdmin->fetchColumn();

            // 2. Se não achar por permissão, pega o ID 1 (Geralmente o criador)
            if (!$emailAdmin) {
                $emailAdmin = $pdo->query("SELECT email FROM usuarios WHERE id = 1")->fetchColumn();
            }

            // 3. Se achou um e-mail, dispara
            if ($emailAdmin) {
                $assunto = "Nova mensagem de " . $dados['cliente_nome'];
                $texto = "O cliente <strong>{$dados['cliente_nome']}</strong> enviou uma mensagem no projeto <strong>{$dados['projeto_nome']}</strong>:<br><br><i>\"$msg\"</i>";
                
                $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
                $linkAdmin = $protocolo . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/editar_projeto.php?id=" . $pid;
                
                // Função genérica que deve existir no mailer.php (ou use mail() nativo se preferir)
                if(function_exists('enviarNotificacao')) {
                    enviarNotificacao($emailAdmin, $assunto, "Nova Mensagem no Portal", $texto, $linkAdmin);
                }
            }
        }
        // ----------------------------------------------

        exit; // Encerra para o JS não carregar HTML duplicado
    }
}

// 3. CARREGAR DADOS
$checklist = $pdo->query("SELECT * FROM checklist_projetos WHERE projeto_id = $pid ORDER BY concluido ASC, id DESC")->fetchAll();
$sqlAtiv = "SELECT t.*, (SELECT SUM(TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW()))) FROM tempo_logs tl WHERE tl.tarefa_id = t.id) as segundos_totais FROM tarefas t WHERE t.projeto_id = $pid ORDER BY t.data_criacao DESC LIMIT 50";
$atividades = $pdo->query($sqlAtiv)->fetchAll();
$arquivos  = $pdo->query("SELECT * FROM projeto_arquivos WHERE projeto_id = $pid AND visivel_cliente = 1 ORDER BY id DESC")->fetchAll();
$chatInit = $pdo->query("SELECT * FROM (SELECT * FROM projeto_comentarios WHERE projeto_id = $pid ORDER BY id DESC LIMIT 20) sub ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

function fmtHoras($seg) { return number_format($seg / 3600, 1, ',', '.'); }
$favicon = !empty($dados['logo']) ? $dados['logo'] : 'https://ui-avatars.com/api/?name='.urlencode($dados['cliente_nome'])."&background=0d6efd&color=fff";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dados['projeto_nome']); ?></title>
    <link rel="icon" href="<?php echo $favicon; ?>" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --brand-gradient: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%); }
        body { background-color: #f3f5f9; font-family: 'Inter', sans-serif; color: #495057; }
        .header-bg { background: var(--brand-gradient); padding: 2rem 0; color: white; margin-bottom: -2rem; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .btn-voltar { color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.9rem; transition: 0.2s; }
        .btn-voltar:hover { color: white; }
        .chat-box { height: 500px; overflow-y: auto; background: #fff; border-bottom: 1px solid #eee; display: flex; flex-direction: column; }
        .msg-container { padding: 20px; display: flex; flex-direction: column; gap: 12px; flex-grow: 1; }
        .msg { max-width: 80%; padding: 10px 14px; border-radius: 12px; font-size: 0.95rem; position: relative; word-wrap: break-word; }
        .msg-admin { align-self: flex-start; background: #f8f9fa; color: #333; border-bottom-left-radius: 2px; border: 1px solid #e9ecef; }
        .msg-cliente { align-self: flex-end; background: #d1e7dd; color: #0f5132; border-bottom-right-radius: 2px; border: 1px solid #badbcc; }
        .msg-time { font-size: 0.65rem; opacity: 0.7; display: block; margin-top: 4px; text-align: right; }
        .btn-load-more { font-size: 0.8rem; color: #0d6efd; background: none; border: none; padding: 10px; width: 100%; text-align: center; cursor: pointer; }
        .btn-load-more:hover { text-decoration: underline; }
        a.atividade-link { text-decoration: none; color: inherit; display: block; transition: background 0.2s; }
        a.atividade-link:hover { background-color: #f8f9fa; }
    </style>
</head>
<body>

<div class="header-bg">
    <div class="container">
        <div class="mb-3"><a href="portal.php?t=<?php echo $token; ?>" class="btn-voltar"><i class="fas fa-arrow-left me-1"></i> Voltar ao Dashboard</a></div>
        <div class="d-flex align-items-center gap-3">
            <div class="bg-white rounded p-2" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                <?php if($dados['logo']): ?>
                    <img src="<?php echo $dados['logo']; ?>" style="max-width:100%; max-height:100%;">
                <?php else: ?>
                    <i class="fas fa-folder-open fa-lg text-primary"></i>
                <?php endif; ?>
            </div>
            <div>
                <span class="badge bg-white text-dark bg-opacity-75 mb-1">Projeto</span>
                <h2 class="fw-bold mb-0 text-white"><?php echo htmlspecialchars($dados['projeto_nome']); ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="container" style="margin-top: 4rem;">
    <div class="row g-4">
        <div class="col-lg-7">
            <?php if(!empty($checklist)): ?>
            <div class="card mb-4">
                <div class="card-header bg-white py-3 fw-bold text-uppercase small text-muted"><i class="fas fa-list-check me-2"></i> Metas & Checklist</div>
                <div class="list-group list-group-flush">
                    <?php foreach($checklist as $item): $ok = ($item['concluido'] || $item['status']=='done'); ?>
                    <div class="list-group-item d-flex align-items-center p-3">
                        <i class="fas <?php echo $ok ? 'fa-check-circle text-success' : 'fa-circle text-muted opacity-25'; ?> fa-lg me-3"></i>
                        <span class="<?php echo $ok ? 'text-decoration-line-through text-muted' : 'fw-bold text-dark'; ?>"><?php echo htmlspecialchars($item['descricao']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header bg-white py-3 fw-bold text-uppercase small text-muted"><i class="fas fa-history me-2"></i> Atividades Realizadas</div>
                <div class="list-group list-group-flush">
                    <?php foreach($atividades as $a): 
                        $linkAtiv = "portal_atividade.php?t=$token&id={$a['id']}";
                    ?>
                    <a href="<?php echo $linkAtiv; ?>" class="list-group-item p-3 atividade-link">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-light text-dark border"><?php echo date('d/m/Y', strtotime($a['data_criacao'])); ?></span>
                            <span class="badge bg-primary bg-opacity-10 text-primary font-monospace"><?php echo fmtHoras($a['segundos_totais']); ?> h</span>
                        </div>
                        <div class="fw-bold text-dark d-flex align-items-center justify-content-between">
                            <?php echo htmlspecialchars($a['descricao']); ?>
                            <i class="fas fa-chevron-right small text-muted"></i>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php if(empty($atividades)) echo '<div class="p-4 text-center text-muted small">Nenhuma atividade registrada ainda.</div>'; ?>
                </div>
            </div>

            <?php if(!empty($arquivos)): ?>
            <div class="card">
                <div class="card-header bg-white py-3 fw-bold text-uppercase small text-muted"><i class="fas fa-cloud-download-alt me-2"></i> Arquivos</div>
                <div class="list-group list-group-flush">
                    <?php foreach($arquivos as $a): ?>
                    <a href="<?php echo $a['caminho']; ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center p-3">
                        <div class="bg-light rounded p-2 me-3"><i class="fas fa-file fa-lg text-secondary"></i></div>
                        <div><div class="fw-bold text-dark"><?php echo $a['nome_original']; ?></div><small class="text-muted">Clique para baixar</small></div>
                        <div class="ms-auto"><i class="fas fa-download text-muted"></i></div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-5">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-comments me-2"></i> Chat do Projeto</span>
                    <span class="badge bg-white text-primary" style="font-size:0.7em">Online</span>
                </div>
                
                <div class="chat-box" id="scrollContainer">
                    <button id="btnLoadMore" class="btn-load-more" onclick="carregarHistorico()"><i class="fas fa-history me-1"></i> Carregar conversas antigas</button>
                    <div class="msg-container" id="chatContent"></div>
                </div>

                <div class="card-footer bg-white p-3">
                    <form onsubmit="enviarMensagem(event)">
                        <div class="input-group">
                            <input type="text" id="inputMsg" class="form-control" placeholder="Digite sua mensagem..." required autocomplete="off">
                            <button class="btn btn-primary px-3" type="submit"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const token = "<?php echo $token; ?>";
const pid = "<?php echo $pid; ?>";
let mensagens = <?php echo json_encode($chatInit); ?>;
let primeiroId = mensagens.length > 0 ? mensagens[0].id : 0;
let ultimoId = mensagens.length > 0 ? mensagens[mensagens.length-1].id : 0;

renderizar(mensagens, true);

function renderizar(listaMsgs, scrollBottom = false) {
    const container = document.getElementById('chatContent');
    if(listaMsgs.length === 0 && container.innerHTML === "") { 
        container.innerHTML = '<div class="text-center text-muted mt-5 small">Nenhuma mensagem ainda.<br>Tire suas dúvidas aqui.</div>'; 
        document.getElementById('btnLoadMore').style.display = 'none';
        return; 
    }
    const html = listaMsgs.map(m => {
        const isAdmin = (m.autor_tipo === 'admin');
        const nome = isAdmin ? 'Equipe' : 'Você';
        return `<div class="msg ${isAdmin?'msg-admin':'msg-cliente'}">
                    ${isAdmin ? '<strong class="d-block text-primary small mb-1">'+nome+'</strong>' : ''}
                    ${m.mensagem.replace(/\n/g, '<br>')}
                    <span class="msg-time">${new Date(m.data_criacao).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</span>
                </div>`;
    }).join('');

    if(scrollBottom) {
        container.innerHTML += html;
        document.getElementById("scrollContainer").scrollTop = document.getElementById("scrollContainer").scrollHeight;
    } else {
        // Insere no topo
        // Salva altura antes
        const oldHeight = container.scrollHeight;
        container.innerHTML = html + container.innerHTML;
        // Ajusta scroll para manter posição
        // (Isso é complexo, mas deixar no topo é aceitável para MVP)
    }
}

async function verificarNovas() {
    try {
        const res = await fetch(`portal_detalhes.php?t=${token}&p=${pid}&ajax_chat_last_id=${ultimoId}`);
        const novas = await res.json();
        if(novas.length > 0) {
            ultimoId = novas[novas.length-1].id;
            renderizar(novas, true);
        }
    } catch(e) {}
}

async function carregarHistorico() {
    const btn = document.getElementById('btnLoadMore');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Carregando...';
    try {
        const res = await fetch(`portal_detalhes.php?t=${token}&p=${pid}&ajax_chat_history=1&first_id=${primeiroId}`);
        const antigas = await res.json();
        if(antigas.length > 0) {
            primeiroId = antigas[0].id;
            renderizar(antigas, false);
            btn.innerHTML = '<i class="fas fa-history me-1"></i> Carregar mais antigas';
        } else {
            btn.innerHTML = 'Histórico completo.';
            setTimeout(() => btn.style.display = 'none', 2000);
        }
    } catch(e) {}
}

async function enviarMensagem(e) {
    e.preventDefault();
    const input = document.getElementById('inputMsg');
    const txt = input.value.trim();
    if(!txt) return;
    
    const fd = new FormData(); fd.append('mensagem', txt);
    input.value = '';
    
    // Envia e já dispara verificação
    await fetch(`portal_detalhes.php?t=${token}&p=${pid}`, { method: 'POST', body: fd });
    verificarNovas();
}

setInterval(verificarNovas, 3000);
</script>
</body>
</html>