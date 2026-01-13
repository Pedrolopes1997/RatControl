<?php
require 'config/db.php';
require 'includes/header.php';

// Filtro de Projeto
$projeto_id = filter_input(INPUT_GET, 'projeto', FILTER_VALIDATE_INT);

// Busca projetos para o Dropdown
$projetos_lista = $pdo->query("SELECT id, nome FROM projetos WHERE status='ativo' ORDER BY nome")->fetchAll();

// Organiza as tarefas nas colunas
$tarefas = ['todo' => [], 'doing' => [], 'done' => []];

if ($projeto_id) {
    // Busca itens da tabela checklist_projetos (que estamos usando como tarefas do kanban)
    $sql = "SELECT * FROM checklist_projetos WHERE projeto_id = :pid ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':pid' => $projeto_id]);
    $items = $stmt->fetchAll();
    
    foreach ($items as $item) {
        // Normaliza status (se vier vazio ou estranho, joga para todo)
        $st = $item['status'];
        if (!in_array($st, ['todo', 'doing', 'done'])) $st = 'todo';
        $tarefas[$st][] = $item;
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
    /* Layout do Quadro */
    .kanban-board {
        display: flex;
        gap: 1.5rem;
        overflow-x: auto;
        padding-bottom: 20px;
        align-items: flex-start;
        height: calc(100vh - 200px); /* Ocupa a altura da tela */
    }
    
    .kanban-col {
        background-color: #f1f2f4;
        border-radius: 12px;
        width: 350px;
        min-width: 300px;
        display: flex;
        flex-direction: column;
        max-height: 100%;
        border: 1px solid #e0e0e0;
    }
    
    .kanban-header {
        padding: 1rem;
        font-weight: 700;
        color: #172b4d;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .kanban-items {
        padding: 0 0.8rem 0.8rem 0.8rem;
        flex-grow: 1;
        overflow-y: auto;
        min-height: 150px; /* Garante área de drop mesmo vazia */
    }
    
    /* Cartões */
    .kanban-card {
        background: white;
        padding: 10px 12px;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(9, 30, 66, 0.25);
        margin-bottom: 8px;
        cursor: grab;
        transition: transform 0.2s, box-shadow 0.2s;
        border-left: 4px solid transparent;
        font-size: 0.9rem;
        position: relative;
        group: relative;
    }
    .kanban-card:hover {
        background-color: #fafbfc;
        box-shadow: 0 4px 8px rgba(9, 30, 66, 0.25);
    }
    .kanban-card:active { cursor: grabbing; }
    
    /* Botão de Excluir (aparece no hover) */
    .btn-del-card {
        position: absolute;
        top: 5px;
        right: 5px;
        opacity: 0;
        transition: opacity 0.2s;
        border: none;
        background: none;
        color: #dc3545;
    }
    .kanban-card:hover .btn-del-card { opacity: 1; }

    /* Cores das Bordas */
    .card-todo { border-left-color: #6c757d; }
    .card-doing { border-left-color: #0d6efd; }
    .card-done { border-left-color: #198754; opacity: 0.8; text-decoration: line-through; background-color: #f8f9fa; }
    
    /* Input Rápido */
    .quick-add { padding: 0 0.8rem 0.8rem 0.8rem; }
    
    /* Scrollbar Bonita */
    .kanban-items::-webkit-scrollbar { width: 6px; }
    .kanban-items::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0">Quadro Kanban</h2>
        <p class="text-muted mb-0">Gerenciamento visual de tarefas.</p>
    </div>
    
    <form class="d-flex align-items-center bg-white p-2 rounded shadow-sm border">
        <select name="projeto" id="filtro_projeto" class="form-select form-select-sm border-0 fw-bold" style="width: 250px;" onchange="this.form.submit()">
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
        <div class="mb-3 opacity-25 text-primary"><i class="fas fa-columns fa-5x"></i></div>
        <h4 class="text-muted fw-bold">Selecione um projeto acima para começar.</h4>
    </div>
<?php else: ?>

    <div class="kanban-board">
        
        <div class="kanban-col shadow-sm">
            <div class="kanban-header">
                <span class="small text-uppercase">A Fazer</span>
                <span id="count-todo" class="badge bg-secondary rounded-pill"><?php echo count($tarefas['todo']); ?></span>
            </div>
            
            <div class="kanban-items" id="col-todo" data-status="todo">
                <?php foreach($tarefas['todo'] as $t): ?>
                    <div class="kanban-card card-todo" data-id="<?php echo $t['id']; ?>">
                        <?php echo htmlspecialchars($t['descricao']); ?>
                        <button class="btn-del-card" onclick="excluirCartao(<?php echo $t['id']; ?>, this)"><i class="fas fa-times"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="quick-add">
                <div class="input-group">
                    <input type="text" id="input-nova-tarefa" class="form-control form-control-sm" placeholder="+ Adicionar item" onkeypress="if(event.key === 'Enter') novaTarefa()">
                    <button class="btn btn-sm btn-primary" onclick="novaTarefa()"><i class="fas fa-plus"></i></button>
                </div>
            </div>
        </div>

        <div class="kanban-col shadow-sm">
            <div class="kanban-header">
                <span class="small text-uppercase text-primary">Em Execução</span>
                <span id="count-doing" class="badge bg-primary rounded-pill"><?php echo count($tarefas['doing']); ?></span>
            </div>
            <div class="kanban-items" id="col-doing" data-status="doing">
                <?php foreach($tarefas['doing'] as $t): ?>
                    <div class="kanban-card card-doing" data-id="<?php echo $t['id']; ?>">
                        <?php echo htmlspecialchars($t['descricao']); ?>
                        <button class="btn-del-card" onclick="excluirCartao(<?php echo $t['id']; ?>, this)"><i class="fas fa-times"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="kanban-col shadow-sm">
            <div class="kanban-header">
                <span class="small text-uppercase text-success">Concluído</span>
                <span id="count-done" class="badge bg-success rounded-pill"><?php echo count($tarefas['done']); ?></span>
            </div>
            <div class="kanban-items" id="col-done" data-status="done">
                <?php foreach($tarefas['done'] as $t): ?>
                    <div class="kanban-card card-done" data-id="<?php echo $t['id']; ?>">
                        <?php echo htmlspecialchars($t['descricao']); ?>
                        <button class="btn-del-card" onclick="excluirCartao(<?php echo $t['id']; ?>, this)"><i class="fas fa-times"></i></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

<?php endif; ?>

<script>
    const projId = <?php echo $projeto_id ?: 0; ?>;

    // Inicializa Drag & Drop
    const colunas = document.querySelectorAll('.kanban-items');
    colunas.forEach(col => {
        new Sortable(col, {
            group: 'shared', // Permite mover entre colunas
            animation: 150,
            ghostClass: 'bg-info', // Classe visual enquanto arrasta
            delay: 100, delayOnTouchOnly: true, // Melhor toque no mobile
            
            onEnd: function (evt) {
                const itemEl = evt.item;
                const novaColuna = evt.to;
                const novoStatus = novaColuna.getAttribute('data-status');
                const idTarefa = itemEl.getAttribute('data-id');

                // 1. Atualiza Cor da Borda
                itemEl.classList.remove('card-todo', 'card-doing', 'card-done');
                itemEl.classList.add('card-' + novoStatus);

                // 2. Chama API para salvar
                fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ acao: 'mover_kanban', id: idTarefa, status: novoStatus })
                });

                // 3. Atualiza Contadores
                atualizarContadores();
            }
        });
    });

    function atualizarContadores() {
        document.getElementById('count-todo').innerText  = document.getElementById('col-todo').children.length;
        document.getElementById('count-doing').innerText = document.getElementById('col-doing').children.length;
        document.getElementById('count-done').innerText  = document.getElementById('col-done').children.length;
    }

    function novaTarefa() {
        const input = document.getElementById('input-nova-tarefa');
        const desc = input.value.trim();
        if(!desc) return;

        // Limpa input
        input.value = '';

        // Salva na API
        fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ acao: 'add_checklist', projeto_id: projId, descricao: desc })
        }).then(() => location.reload()); 
        // Dica: Para ficar ainda mais "Pro", poderíamos criar o elemento HTML aqui 
        // sem reload, mas o reload garante que o ID venha correto do banco.
    }

    function excluirCartao(id, btn) {
        if(!confirm("Remover este item?")) return;
        
        // Efeito visual imediato
        const card = btn.closest('.kanban-card');
        card.style.opacity = '0.3';

        fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ acao: 'delete_checklist', id: id })
        }).then(r => r.json()).then(d => {
            if(d.sucesso) {
                card.remove();
                atualizarContadores();
            } else {
                alert('Erro ao excluir');
                card.style.opacity = '1';
            }
        });
    }

    // Inicializa Select2 no Filtro (se o footer carregou a lib)
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof $ !== 'undefined' && $.fn.select2) {
            $('#filtro_projeto').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Pesquisar projeto...'
            });
        }
    });
</script>

<?php require 'includes/footer.php'; ?>