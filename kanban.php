<?php
require 'config/db.php';
require 'includes/header.php';

// Filtro de Projeto (Obrigatório selecionar um para ver o quadro)
$projeto_id = $_GET['projeto'] ?? '';

// Busca projetos para o Dropdown
$projetos_lista = $pdo->query("SELECT id, nome FROM projetos WHERE status='ativo' ORDER BY nome")->fetchAll();

// Se selecionou um projeto, busca as tarefas dele
$tarefas = ['todo' => [], 'doing' => [], 'done' => []];
if ($projeto_id) {
    $sql = "SELECT * FROM checklist_projetos WHERE projeto_id = :pid ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pid' => $projeto_id]);
    $items = $stmt->fetchAll();
    
    foreach ($items as $item) {
        // Fallback: se o status estiver vazio, assume 'todo'
        $st = $item['status'] ?: 'todo';
        if(isset($tarefas[$st])) {
            $tarefas[$st][] = $item;
        }
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
    /* Design do Quadro Kanban */
    .kanban-board {
        display: flex;
        gap: 1.5rem;
        overflow-x: auto;
        padding-bottom: 20px;
        align-items: flex-start;
    }
    
    .kanban-col {
        background-color: #f4f5f7;
        border-radius: 12px;
        width: 350px;
        min-width: 300px;
        display: flex;
        flex-direction: column;
        max-height: 80vh;
    }
    
    .kanban-header {
        padding: 1rem;
        font-weight: 700;
        color: #44546f;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid rgba(0,0,0,0.03);
    }
    
    .kanban-items {
        padding: 0.8rem;
        flex-grow: 1;
        overflow-y: auto;
        min-height: 100px;
    }
    
    /* Cartão da Tarefa */
    .kanban-card {
        background: white;
        padding: 12px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        margin-bottom: 10px;
        cursor: grab;
        transition: transform 0.2s, box-shadow 0.2s;
        border-left: 4px solid transparent;
        font-size: 0.95rem;
    }
    .kanban-card:hover {
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    .kanban-card:active { cursor: grabbing; }
    
    /* Cores das Bordas por Status */
    .card-todo { border-left-color: #6c757d; }
    .card-doing { border-left-color: #0d6efd; }
    .card-done { border-left-color: #198754; opacity: 0.7; text-decoration: line-through; background-color: #f8f9fa; }
    
    /* Efeito ao arrastar */
    .sortable-ghost { opacity: 0.4; background: #c8ebfb; border: 2px dashed #0d6efd; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0">Quadro Kanban</h2>
        <p class="text-muted mb-0">Gerencie o fluxo de trabalho visualmente.</p>
    </div>
    
    <form class="d-flex align-items-center bg-white p-2 rounded shadow-sm border">
        <label class="me-2 small fw-bold text-muted">Projeto:</label>
        <select name="projeto" class="form-select form-select-sm border-0 bg-light fw-bold" style="width: 250px;" onchange="this.form.submit()">
            <option value="">Selecione um projeto...</option>
            <?php foreach($projetos_lista as $p): ?>
                <option value="<?php echo $p['id']; ?>" <?php echo ($projeto_id == $p['id']) ? 'selected' : ''; ?>>
                    <?php echo $p['nome']; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (!$projeto_id): ?>
    <div class="text-center py-5">
        <div class="mb-3 opacity-25"><i class="fas fa-columns fa-4x"></i></div>
        <h4 class="text-muted">Selecione um projeto acima para ver o quadro.</h4>
    </div>
<?php else: ?>

    <div class="kanban-board">
        
        <div class="kanban-col shadow-sm">
            <div class="kanban-header">
                <span><i class="fas fa-list-ul me-2 text-secondary"></i> A Fazer</span>
                <span id="count-todo" class="badge bg-secondary rounded-pill"><?php echo count($tarefas['todo']); ?></span>
            </div>
            <div class="kanban-items" id="col-todo" data-status="todo">
                <?php foreach($tarefas['todo'] as $t): ?>
                    <div class="kanban-card card-todo" data-id="<?php echo $t['id']; ?>">
                        <?php echo $t['descricao']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="p-2">
                <button class="btn btn-sm btn-white w-100 text-muted border fw-bold shadow-sm" onclick="modalAdd()"><i class="fas fa-plus"></i> Adicionar cartão</button>
            </div>
        </div>

        <div class="kanban-col shadow-sm">
            <div class="kanban-header">
                <span><i class="fas fa-spinner fa-spin me-2 text-primary"></i> Em Execução</span>
                <span id="count-doing" class="badge bg-primary rounded-pill"><?php echo count($tarefas['doing']); ?></span>
            </div>
            <div class="kanban-items" id="col-doing" data-status="doing">
                <?php foreach($tarefas['doing'] as $t): ?>
                    <div class="kanban-card card-doing" data-id="<?php echo $t['id']; ?>">
                        <?php echo $t['descricao']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="kanban-col shadow-sm">
            <div class="kanban-header">
                <span><i class="fas fa-check-circle me-2 text-success"></i> Concluído</span>
                <span id="count-done" class="badge bg-success rounded-pill"><?php echo count($tarefas['done']); ?></span>
            </div>
            <div class="kanban-items" id="col-done" data-status="done">
                <?php foreach($tarefas['done'] as $t): ?>
                    <div class="kanban-card card-done" data-id="<?php echo $t['id']; ?>">
                        <?php echo $t['descricao']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

<?php endif; ?>

<script>
    // Inicializa o Sortable para todas as colunas
    const colunas = document.querySelectorAll('.kanban-items');
    
    colunas.forEach(col => {
        new Sortable(col, {
            group: 'shared', // Permite mover entre colunas
            animation: 150,
            ghostClass: 'sortable-ghost',
            delay: 100, // Previne clique acidental em mobile
            delayOnTouchOnly: true,
            
            onEnd: function (evt) {
                const itemEl = evt.item;
                const novaColuna = evt.to;
                const novoStatus = novaColuna.getAttribute('data-status');
                const idTarefa = itemEl.getAttribute('data-id');

                // 1. Atualiza Estilo Visual
                itemEl.classList.remove('card-todo', 'card-doing', 'card-done');
                itemEl.classList.add('card-' + novoStatus);

                // 2. Salva no Banco
                fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ acao: 'mover_kanban', id: idTarefa, status: novoStatus })
                });

                // 3. ATUALIZA OS NÚMEROS (CONTADORES)
                atualizarContadores();
            }
        });
    });

    // Função que conta os filhos de cada coluna e atualiza o badge
    function atualizarContadores() {
        document.getElementById('count-todo').innerText  = document.getElementById('col-todo').children.length;
        document.getElementById('count-doing').innerText = document.getElementById('col-doing').children.length;
        document.getElementById('count-done').innerText  = document.getElementById('col-done').children.length;
    }

    function modalAdd() {
        let desc = prompt("Nova tarefa para 'A Fazer':");
        if(desc) {
            fetch('api.php', {
                method: 'POST',
                body: JSON.stringify({ acao: 'add_checklist', projeto_id: <?php echo $projeto_id ?: 0; ?>, descricao: desc })
            }).then(() => location.reload()); // Recarrega para mostrar a nova tarefa
        }
    }
</script>

<?php require 'includes/footer.php'; ?>