<?php
require 'config/db.php';
require_once 'includes/auth.php';

$eh_admin = (isset($_SESSION['usuario_permissao']) && $_SESSION['usuario_permissao'] === 'admin');

// 1. CADASTRAR PROJETO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    if (!$eh_admin) die("Acesso negado.");
    
    $nome       = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
    $horas      = filter_input(INPUT_POST, 'horas', FILTER_VALIDATE_INT);
    $inicio     = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : NULL;
    $fim        = !empty($_POST['data_fim']) ? $_POST['data_fim'] : NULL;
    $desc       = filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_SPECIAL_CHARS);

    if ($nome && $cliente_id) {
        $sql = "INSERT INTO projetos (nome, cliente_id, horas_estimadas, data_inicio, data_fim, descricao, status) 
                VALUES (:nome, :cid, :horas, :ini, :fim, :desc, 'ativo')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nome' => $nome, 
            ':cid' => $cliente_id, 
            ':horas' => $horas ?: 0,
            ':ini' => $inicio, 
            ':fim' => $fim, 
            ':desc' => $desc
        ]);
        
        $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Projeto criado com sucesso!'];
        header("Location: projetos.php"); exit;
    }
}

// 2. BUSCA PROJETOS
$status_filter = $_GET['status'] ?? 'ativo';
$where_status = ($status_filter === 'todos') ? "1=1" : "p.status = 'ativo'";

// Query otimizada para buscar progresso
$sql = "SELECT p.*, c.nome as nome_cliente,
        (SELECT SUM(TIMESTAMPDIFF(SECOND, tl.inicio, IFNULL(tl.fim, NOW()))) 
         FROM tempo_logs tl JOIN tarefas t ON tl.tarefa_id = t.id 
         WHERE t.projeto_id = p.id) as segundos_usados
        FROM projetos p 
        JOIN clientes c ON p.cliente_id = c.id
        WHERE $where_status
        ORDER BY p.status ASC, p.data_fim ASC"; // Prioriza ativos e com prazo próximo

$projetos = $pdo->query($sql)->fetchAll();
$clientes = $pdo->query("SELECT * FROM clientes WHERE status='ativo' ORDER BY nome")->fetchAll();

require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0">Projetos</h2>
        <p class="text-muted mb-0">Gerencie o escopo e acompanhe o progresso.</p>
    </div>
    
    <div class="d-flex gap-2">
        <div class="btn-group shadow-sm">
            <a href="?status=ativo" class="btn btn-white border <?php echo $status_filter=='ativo'?'active bg-light fw-bold text-primary':''; ?>">Ativos</a>
            <a href="?status=todos" class="btn btn-white border <?php echo $status_filter=='todos'?'active bg-light fw-bold text-primary':''; ?>">Todos</a>
        </div>
        
        <?php if($eh_admin): ?>
        <button class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalNovoProjeto">
            <i class="fas fa-plus me-1"></i> Novo Projeto
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <?php foreach($projetos as $p): 
        // Cálculos de Tempo
        $usado_seg = $p['segundos_usados'] ?? 0;
        $horas_usadas = $usado_seg / 3600;
        $meta = $p['horas_estimadas'];
        
        // Barra de Progresso
        $perc = 0; 
        $corBarra = 'bg-primary';
        
        if ($meta > 0) {
            $perc = ($horas_usadas / $meta) * 100;
            if ($perc >= 100) { $perc = 100; $corBarra = 'bg-danger'; }
            elseif ($perc > 80) { $corBarra = 'bg-warning'; }
            else { $corBarra = 'bg-success'; }
        }

        // Datas
        $prazo = $p['data_fim'] ? date('d/m/Y', strtotime($p['data_fim'])) : 'Sem prazo';
        $atrasado = ($p['data_fim'] && strtotime($p['data_fim']) < time() && $p['status']=='ativo');
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 shadow-sm border-0 position-relative hover-lift">
            
            <?php if($p['status'] === 'arquivado'): ?>
                <div class="position-absolute top-0 end-0 m-3 badge bg-secondary">Arquivado</div>
            <?php elseif($atrasado): ?>
                <div class="position-absolute top-0 end-0 m-3 badge bg-danger animate-pulse" title="Prazo vencido">Atrasado</div>
            <?php endif; ?>

            <div class="card-body d-flex flex-column p-4">
                
                <div class="mb-3">
                    <small class="text-muted text-uppercase fw-bold ls-1" style="font-size: 0.7rem;">
                        <i class="fas fa-building me-1"></i> <?php echo $p['nome_cliente']; ?>
                    </small>
                    <h5 class="fw-bold text-dark mt-1 mb-1 text-truncate" title="<?php echo $p['nome']; ?>">
                        <?php echo $p['nome']; ?>
                    </h5>
                </div>

                <p class="text-muted small flex-grow-1" style="min-height: 40px; line-height: 1.5;">
                    <?php echo $p['descricao'] ? mb_strimwidth($p['descricao'], 0, 90, "...") : '<span class="fst-italic opacity-50">Sem descrição definida.</span>'; ?>
                </p>

                <div class="d-flex justify-content-between align-items-center mb-3 small bg-light p-2 rounded border border-light">
                    <div class="<?php echo $atrasado ? 'text-danger fw-bold' : 'text-muted'; ?>">
                        <i class="far fa-calendar-alt me-1"></i> <?php echo $prazo; ?>
                    </div>
                    
                    <?php if($meta > 0): ?>
                        <div class="<?php echo ($horas_usadas > $meta) ? 'text-danger fw-bold' : 'text-dark fw-bold'; ?>">
                            <?php echo number_format($horas_usadas, 1); ?> / <?php echo $meta; ?>h
                        </div>
                    <?php else: ?>
                        <div class="text-primary fw-bold">
                            <?php echo number_format($horas_usadas, 1); ?>h <small class="text-muted fw-normal">acumuladas</small>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if($meta > 0): ?>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar <?php echo $corBarra; ?>" style="width: <?php echo $perc; ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted" style="font-size: 0.7rem;"><?php echo number_format($perc, 0); ?>% Concluído</small>
                        <small class="text-muted" style="font-size: 0.7rem;">Restam: <?php echo max(0, number_format($meta - $horas_usadas, 1)); ?>h</small>
                    </div>
                <?php else: ?>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-info progress-bar-striped" style="width: 100%"></div>
                    </div>
                    <div class="text-end mt-1">
                        <small class="text-muted" style="font-size: 0.7rem;">Projeto Contínuo</small>
                    </div>
                <?php endif; ?>

                <div class="mt-4 pt-3 border-top text-end">
                    <?php if($eh_admin): ?>
                        <a href="editar_projeto.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary fw-bold w-100">
                            Gerenciar Detalhes <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    <?php else: ?>
                        <span class="btn btn-sm btn-light w-100 disabled text-muted">Acesso Restrito</span>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if(empty($projetos)): ?>
        <div class="col-12 text-center py-5">
            <div class="text-muted mb-3"><i class="fas fa-folder-open fa-3x opacity-25"></i></div>
            <h5 class="text-muted fw-bold">Nenhum projeto encontrado.</h5>
            <p class="text-muted small">Crie um novo projeto para começar a rastrear o tempo.</p>
            <?php if($eh_admin): ?>
                <button class="btn btn-primary mt-2 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalNovoProjeto">
                    <i class="fas fa-plus me-2"></i> Criar Primeiro Projeto
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalNovoProjeto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold"><i class="fas fa-briefcase me-2"></i>Novo Projeto</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="acao" value="criar">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nome do Projeto *</label>
                        <input type="text" name="nome" class="form-control" required placeholder="Ex: E-commerce V2">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Cliente *</label>
                        <select name="cliente_id" id="select_cliente_modal" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach($clientes as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo $c['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Prazo Final</label>
                            <input type="date" name="data_fim" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Orçamento de Horas</label>
                        <div class="input-group">
                            <input type="number" name="horas" class="form-control" placeholder="0 = Ilimitado">
                            <span class="input-group-text text-muted">horas</span>
                        </div>
                        <div class="form-text small">Deixe 0 se for um projeto de recorrência mensal.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Descrição / Escopo</label>
                        <textarea name="descricao" class="form-control" rows="3" placeholder="Resumo do que será feito..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Criar Projeto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .hover-lift { transition: transform 0.2s, box-shadow 0.2s; }
    .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important; }
    
    .ls-1 { letter-spacing: 1px; }
    
    .animate-pulse { animation: pulse 2s infinite; }
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }
</style>

<script>
// Inicializa Select2 no Modal (se carregado)
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#select_cliente_modal').select2({
            dropdownParent: $('#modalNovoProjeto'),
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Selecione...'
        });
    }
});
</script>

<?php require 'includes/footer.php'; ?>