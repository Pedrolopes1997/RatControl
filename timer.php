<?php
require 'config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Lógica de Toast
if (isset($_GET['msg'])) {
    $msgs = [
        'iniciado' => '⏱️ Tarefa iniciada com sucesso!',
        'pausado'  => '⏸️ Cronômetro pausado.',
        'retomado' => '▶️ Cronômetro retomado.',
        'finalizado'=> '✅ Tarefa finalizada e salva!'
    ];
    $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => $msgs[$_GET['msg']] ?? 'Sucesso'];
    header("Location: timer.php"); exit;
}

require 'includes/header.php';

$uid = $_SESSION['usuario_id'];

// Buscas iniciais otimizadas
$clientes = $pdo->query("SELECT id, nome FROM clientes WHERE status = 'ativo' ORDER BY nome ASC")->fetchAll();
$todos_projetos = $pdo->query("SELECT id, nome, cliente_id FROM projetos WHERE status = 'ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$todas_tags = $pdo->query("SELECT id, nome FROM tags WHERE usuario_id = $uid ORDER BY nome")->fetchAll();
$sugestoes = $pdo->query("SELECT DISTINCT descricao FROM tarefas WHERE usuario_id = $uid ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold fs-5"><i class="fas fa-stopwatch me-2"></i> Cronômetro</span>
                <span class="badge bg-white text-dark fw-bold px-3 py-2 rounded-pill" id="status-badge">Aguardando Início</span>
            </div>
            <div class="card-body p-4">
                
                <div id="area-selecao">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Cliente</label>
                            <select id="cliente_id" class="form-select" onchange="filtrarProjetos()">
                                <option value="">Selecione...</option>
                                <?php foreach($clientes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo $c['nome']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Projeto</label>
                            <select id="projeto_id" class="form-select" disabled onchange="carregarChecklist()">
                                <option value="">Selecione um cliente...</option>
                            </select>
                        </div>
                    </div>

                    <div id="box-checklist" class="mb-4 p-3 bg-light rounded border border-dashed" style="display:none;">
                        <label class="form-label small fw-bold text-primary mb-2">
                            <i class="fas fa-tasks me-1"></i> Tarefas Pendentes deste Projeto:
                        </label>
                        <div class="d-flex flex-wrap gap-2" id="lista-checklist">
                            </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Tags (Opcional)</label>
                        <select id="tags_ids" class="form-select" multiple>
                            <?php foreach($todas_tags as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo $t['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">O que você vai fazer?</label>
                        <input type="text" id="descricao" class="form-control form-control-lg fw-bold" placeholder="Digite ou selecione acima..." list="lista-descricoes" autocomplete="off">
                        <datalist id="lista-descricoes">
                            <?php foreach($sugestoes as $sug): echo "<option value='".htmlspecialchars($sug)."'>"; endforeach; ?>
                        </datalist>
                    </div>
                    
                    <button onclick="iniciarTarefa()" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm py-3 hover-scale">
                        <i class="fas fa-play me-2"></i> INICIAR CONTAGEM
                    </button>
                </div>

                <div id="area-timer" style="display: none; text-align: center;">
                    <h5 id="timer-cliente" class="text-muted fw-bold mb-1 text-uppercase ls-1" style="font-size: 0.8rem;">CLIENTE</h5>
                    <span id="timer-projeto" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 mb-3 px-3">Nome do Projeto</span>
                    
                    <h3 id="timer-desc" class="fw-bold mb-4 text-dark text-break">Descrição da Tarefa...</h3>
                    
                    <div class="display-1 fw-bold font-monospace mb-5 text-dark position-relative d-inline-block" id="display-tempo" style="letter-spacing: -3px;">
                        00:00:00
                    </div>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <button id="btn-pausar" onclick="acaoSimples('pausar')" class="btn btn-warning btn-lg px-4 fw-bold shadow-sm rounded-pill">
                            <i class="fas fa-pause me-2"></i> Pausar
                        </button>
                        <button id="btn-retomar" onclick="acaoSimples('retomar')" class="btn btn-success btn-lg px-4 fw-bold shadow-sm rounded-pill" style="display:none;">
                            <i class="fas fa-play me-2"></i> Retomar
                        </button>
                        <button onclick="if(confirm('Finalizar e salvar esta tarefa?')) acaoSimples('finalizar')" class="btn btn-danger btn-lg px-4 fw-bold shadow-sm rounded-pill">
                            <i class="fas fa-check me-2"></i> Finalizar
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Inicialização
$(document).ready(function() {
    $('#tags_ids').select2({ theme: 'bootstrap-5', placeholder: "Tags...", width: '100%' });
    $('#cliente_id, #projeto_id').select2({ theme: 'bootstrap-5', width: '100%', placeholder: 'Selecione...' });
});

// Dados PHP -> JS
const projetosDb = <?php echo json_encode($todos_projetos); ?>;

// --- FILTRO DE PROJETOS ---
function filtrarProjetos() {
    const cliId = $('#cliente_id').val(); // Select2 usa jQuery
    const $selectProj = $('#projeto_id');
    
    $selectProj.empty().append('<option value="">Sem Projeto Específico</option>');
    document.getElementById('box-checklist').style.display = 'none';
    
    if(!cliId) { 
        $selectProj.prop('disabled', true); 
    } else {
        const projs = projetosDb.filter(p => p.cliente_id == cliId);
        projs.forEach(p => {
            $selectProj.append(new Option(p.nome, p.id));
        });
        $selectProj.prop('disabled', false);
    }
    $selectProj.trigger('change'); // Avisa Select2
}

// --- CHECKLIST ---
function carregarChecklist() {
    const projId = $('#projeto_id').val();
    const box = document.getElementById('box-checklist');
    const lista = document.getElementById('lista-checklist');
    
    if(!projId) { box.style.display = 'none'; return; }

    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ acao: 'get_checklist', projeto_id: projId })
    })
    .then(r => r.json())
    .then(data => {
        if(data.sucesso && data.itens.length > 0) {
            lista.innerHTML = '';
            data.itens.forEach(item => {
                const chip = document.createElement('button');
                chip.className = 'btn btn-outline-secondary btn-sm rounded-pill bg-white border shadow-sm';
                chip.innerHTML = `<i class="far fa-square me-1"></i> ${item.descricao}`;
                chip.onclick = function() {
                    document.getElementById('descricao').value = item.descricao;
                    // Visual selecionado
                    lista.querySelectorAll('button').forEach(b => {
                        b.classList.remove('btn-primary', 'text-white');
                        b.classList.add('btn-outline-secondary', 'bg-white');
                        b.querySelector('i').className = 'far fa-square me-1';
                    });
                    this.classList.remove('btn-outline-secondary', 'bg-white');
                    this.classList.add('btn-primary', 'text-white');
                    this.querySelector('i').className = 'fas fa-check-square me-1';
                };
                lista.appendChild(chip);
            });
            box.style.display = 'block';
        } else {
            box.style.display = 'none';
        }
    });
}

// --- LÓGICA DO TIMER (ANTI-DRIFT) ---
let timerInterval;
let horaInicioLocal; // Timestamp de quando começamos a contar localmente

window.onload = function() {
    // Verifica status ao carregar
    fetch('api.php', { method: 'POST', body: JSON.stringify({ acao: 'verificar_status' }) })
    .then(r => r.json())
    .then(data => {
        if (data.ativo) {
            configurarInterface(data.tarefa, data.segundos_totais);
            if (data.tarefa.status === 'em_andamento') iniciarContadorPreciso(data.segundos_totais);
        }
    });
};

function iniciarContadorPreciso(segundosIniciais) {
    clearInterval(timerInterval);
    // Define o "marco zero" local com base nos segundos que já passaram
    horaInicioLocal = Date.now() - (segundosIniciais * 1000);
    
    timerInterval = setInterval(() => {
        const diff = Math.floor((Date.now() - horaInicioLocal) / 1000);
        
        const h = Math.floor(diff / 3600);
        const m = Math.floor((diff % 3600) / 60);
        const s = Math.floor(diff % 60);
        
        const hh = h.toString().padStart(2, '0');
        const mm = m.toString().padStart(2, '0');
        const ss = s.toString().padStart(2, '0');
        
        document.getElementById('display-tempo').innerText = `${hh}:${mm}:${ss}`;
        
        // Atualiza título da aba também
        document.title = `(${hh}:${mm}) RatControl`;
    }, 1000);
}

function configurarInterface(tarefa, segundos) {
    document.getElementById('area-selecao').style.display = 'none';
    document.getElementById('area-timer').style.display = 'block';
    
    document.getElementById('timer-cliente').innerText = tarefa.nome_cliente;
    document.getElementById('timer-projeto').innerText = tarefa.nome_projeto || 'Sem Projeto';
    document.getElementById('timer-desc').innerText = tarefa.descricao;
    
    // Formata inicial
    const h=Math.floor(segundos/3600), m=Math.floor((segundos%3600)/60), s=Math.floor(segundos%60);
    document.getElementById('display-tempo').innerText = 
        `${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;

    const badge = document.getElementById('status-badge');
    
    if (tarefa.status === 'pausado') {
        document.getElementById('btn-pausar').style.display = 'none';
        document.getElementById('btn-retomar').style.display = 'inline-block';
        badge.innerText = 'PAUSADO';
        badge.className = 'badge bg-warning text-dark fw-bold px-3 py-2 rounded-pill';
        document.title = "⏸️ Pausado - RatControl";
    } else {
        document.getElementById('btn-pausar').style.display = 'inline-block';
        document.getElementById('btn-retomar').style.display = 'none';
        badge.innerText = 'EM ANDAMENTO';
        badge.className = 'badge bg-success animate-pulse fw-bold px-3 py-2 rounded-pill';
    }
}

function iniciarTarefa() {
    const cli = $('#cliente_id').val();
    const proj = $('#projeto_id').val();
    const desc = document.getElementById('descricao').value;
    let tags = $('#tags_ids').val() || [];

    if(!cli) { alert('Selecione um cliente!'); return; }
    if(!desc) { alert('Digite uma descrição!'); return; }

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
    // Pega o ID da tarefa atual da sessão (o backend já sabe qual é)
    fetch('api.php', { method: 'POST', body: JSON.stringify({ acao: acao }) })
    .then(r => r.json())
    .then(data => { if(data.sucesso) window.location.href = 'timer.php?msg=' + (acao=='finalizar'?'finalizado':(acao=='pausar'?'pausado':'retomado')); });
}
</script>

<style>
    .hover-scale:hover { transform: scale(1.02); transition: transform 0.2s; }
    .ls-1 { letter-spacing: 1px; }
    .animate-pulse{animation:pulse 2s infinite}@keyframes pulse{0%{opacity:1}50%{opacity:0.5}100%{opacity:1}}
</style>

<?php require 'includes/footer.php'; ?>