<?php
require 'config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Lógica de Toast
if (isset($_GET['msg'])) {
    $msgs = ['iniciado'=>'⏱️ Tarefa iniciada!', 'pausado'=>'⏸️ Pausada.', 'retomado'=>'▶️ Retomada.', 'finalizado'=>'✅ Finalizada!'];
    $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => $msgs[$_GET['msg']] ?? 'Sucesso'];
    header("Location: timer.php"); exit;
}

require 'includes/header.php';

// Buscas iniciais
$clientes = $pdo->query("SELECT * FROM clientes WHERE status = 'ativo' ORDER BY nome ASC")->fetchAll();
$todos_projetos = $pdo->query("SELECT * FROM projetos WHERE status = 'ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$todas_tags = $pdo->query("SELECT * FROM tags WHERE usuario_id = {$_SESSION['usuario_id']} ORDER BY nome")->fetchAll();
$sugestoes = $pdo->query("SELECT DISTINCT descricao FROM tarefas WHERE usuario_id = {$_SESSION['usuario_id']} ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="fas fa-stopwatch me-2"></i> Cronómetro</span>
                <span class="badge bg-white text-dark" id="status-badge">Aguardando</span>
            </div>
            <div class="card-body p-4">
                
                <div id="area-selecao">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">Cliente</label>
                            <select id="cliente_id" class="form-select" onchange="filtrarProjetos()">
                                <option value="">Selecione...</option>
                                <?php foreach($clientes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo $c['nome']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted">Projeto</label>
                            <select id="projeto_id" class="form-select" disabled onchange="carregarChecklist()">
                                <option value="">Selecione cliente...</option>
                            </select>
                        </div>
                    </div>

                    <div id="box-checklist" class="mb-3" style="display:none;">
                        <label class="form-label small fw-bold text-primary"><i class="fas fa-tasks me-1"></i> Tarefas Pendentes do Projeto</label>
                        <div class="d-flex flex-wrap gap-2" id="lista-checklist">
                            </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Tags</label>
                        <select id="tags_ids" class="form-select" multiple>
                            <?php foreach($todas_tags as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo $t['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">O que você vai fazer?</label>
                        <input type="text" id="descricao" class="form-control form-control-lg" placeholder="Descreva a atividade..." list="lista-descricoes">
                        <datalist id="lista-descricoes">
                            <?php foreach($sugestoes as $sug): echo "<option value='".htmlspecialchars($sug)."'>"; endforeach; ?>
                        </datalist>
                    </div>
                    
                    <button onclick="iniciarTarefa()" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm">
                        <i class="fas fa-play me-2"></i> Iniciar Contagem
                    </button>
                </div>

                <div id="area-timer" style="display: none; text-align: center;">
                    <h5 id="timer-cliente" class="text-muted fw-bold mb-1">Cliente</h5>
                    <span id="timer-projeto" class="badge bg-light text-primary border mb-3">Projeto</span>
                    <h3 id="timer-desc" class="fw-bold mb-4">Descrição...</h3>
                    
                    <div class="display-1 fw-bold font-monospace mb-4 text-dark" id="display-tempo" style="letter-spacing: -2px;">00:00:00</div>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <button id="btn-pausar" onclick="acaoSimples('pausar')" class="btn btn-warning btn-lg px-4 fw-bold shadow-sm"><i class="fas fa-pause me-2"></i> Pausar</button>
                        <button id="btn-retomar" onclick="acaoSimples('retomar')" class="btn btn-success btn-lg px-4 fw-bold shadow-sm" style="display:none;"><i class="fas fa-play me-2"></i> Retomar</button>
                        <button onclick="if(confirm('Finalizar?')) acaoSimples('finalizar')" class="btn btn-danger btn-lg px-4 fw-bold shadow-sm"><i class="fas fa-check me-2"></i> Finalizar</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('#tags_ids').select2({ theme: 'bootstrap-5', placeholder: "Tags opcionais...", width: '100%' });
});

const projetosDb = <?php echo json_encode($todos_projetos); ?>;

function filtrarProjetos() {
    const cliId = document.getElementById('cliente_id').value;
    const selectProj = document.getElementById('projeto_id');
    
    // Reseta
    selectProj.innerHTML = '<option value="">Sem Projeto Específico</option>';
    document.getElementById('box-checklist').style.display = 'none';
    
    if(!cliId) { selectProj.disabled = true; return; }

    const projs = projetosDb.filter(p => p.cliente_id == cliId);
    projs.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.innerText = p.nome;
        selectProj.appendChild(opt);
    });
    selectProj.disabled = false;
}

// NOVA FUNÇÃO: Busca Checklist do Projeto
function carregarChecklist() {
    const projId = document.getElementById('projeto_id').value;
    const box = document.getElementById('box-checklist');
    const lista = document.getElementById('lista-checklist');
    
    if(!projId) { box.style.display = 'none'; return; }

    fetch('api.php', {
        method: 'POST',
        body: JSON.stringify({ acao: 'get_checklist', projeto_id: projId })
    })
    .then(r => r.json())
    .then(data => {
        if(data.sucesso && data.itens.length > 0) {
            lista.innerHTML = '';
            data.itens.forEach(item => {
                // Cria uma "Chip" clicável para cada tarefa
                const chip = document.createElement('button');
                chip.className = 'btn btn-outline-secondary btn-sm rounded-pill bg-white';
                chip.innerHTML = `<i class="far fa-square me-1"></i> ${item.descricao}`;
                chip.onclick = function() {
                    document.getElementById('descricao').value = item.descricao;
                    // Highlight visual
                    document.querySelectorAll('#lista-checklist button').forEach(b => b.classList.remove('btn-primary', 'text-white'));
                    this.classList.add('btn-primary', 'text-white');
                    this.classList.remove('btn-outline-secondary', 'bg-white');
                };
                lista.appendChild(chip);
            });
            box.style.display = 'block';
        } else {
            box.style.display = 'none';
        }
    });
}

// --- LÓGICA DO TIMER (Igual à anterior) ---
let timerInterval, segundosTotais = 0, tarefaIdAtual = null;

window.onload = function() {
    fetch('api.php', { method: 'POST', body: JSON.stringify({ acao: 'verificar_status' }) })
    .then(r => r.json())
    .then(data => {
        if (data.ativo) {
            configurarInterface(data.tarefa, data.segundos_totais);
            if (data.tarefa.status === 'em_andamento') iniciarContadorVisual();
        }
    });
};

function iniciarContadorVisual() {
    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        segundosTotais++;
        document.getElementById('display-tempo').innerText = new Date(segundosTotais * 1000).toISOString().substr(11, 8);
    }, 1000);
}

function configurarInterface(tarefa, segundos) {
    tarefaIdAtual = tarefa.id;
    segundosTotais = segundos;
    
    document.getElementById('area-selecao').style.display = 'none';
    document.getElementById('area-timer').style.display = 'block';
    
    document.getElementById('timer-cliente').innerText = tarefa.nome_cliente;
    document.getElementById('timer-projeto').innerText = tarefa.nome_projeto || 'Sem Projeto';
    document.getElementById('timer-desc').innerText = tarefa.descricao;
    document.getElementById('display-tempo').innerText = new Date(segundos * 1000).toISOString().substr(11, 8);
    document.getElementById('status-badge').innerText = 'Em andamento';
    document.getElementById('status-badge').className = 'badge bg-success animate-pulse';

    if (tarefa.status === 'pausado') {
        document.getElementById('btn-pausar').style.display = 'none';
        document.getElementById('btn-retomar').style.display = 'inline-block';
        document.getElementById('status-badge').innerText = 'Pausado';
        document.getElementById('status-badge').className = 'badge bg-warning text-dark';
    } else {
        document.getElementById('btn-pausar').style.display = 'inline-block';
        document.getElementById('btn-retomar').style.display = 'none';
    }
}

function iniciarTarefa() {
    const cli = document.getElementById('cliente_id').value;
    const proj = document.getElementById('projeto_id').value;
    const desc = document.getElementById('descricao').value;
    let tags = $('#tags_ids').val() || [];

    if(!cli) { alert('Selecione um cliente!'); return; }
    if(!desc) { alert('Digite uma descrição ou selecione uma tarefa!'); return; }

    const btn = document.querySelector('button[onclick="iniciarTarefa()"]');
    const oldText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Iniciando...';
    btn.disabled = true;

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao: 'iniciar', cliente_id: cli, projeto_id: proj, descricao: desc, tags: tags })
    })
    .then(r => r.json())
    .then(data => { 
        if(data.sucesso) window.location.href = 'timer.php?msg=iniciado';
        else { alert('Erro: ' + data.msg); btn.innerHTML = oldText; btn.disabled = false; }
    });
}

function acaoSimples(acao) {
    fetch('api.php', { method: 'POST', body: JSON.stringify({ acao: acao, tarefa_id: tarefaIdAtual }) })
    .then(r => r.json())
    .then(data => { if(data.sucesso) window.location.href = 'timer.php?msg=' + (acao=='finalizar'?'finalizado':(acao=='pausar'?'pausado':'retomado')); });
}
</script>

<style>.animate-pulse{animation:pulse 2s infinite}@keyframes pulse{0%{opacity:1}50%{opacity:0.5}100%{opacity:1}}</style>

<?php require 'includes/footer.php'; ?>