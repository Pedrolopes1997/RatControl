<?php
require 'config/db.php';
require_once 'includes/auth.php';

$usuario_id = $_SESSION['usuario_id'];
$acao = 'criar';
$id_editar = '';
$nome_editar = '';
$cor_editar = '#0d6efd'; // Azul padrão

// 1. PROCESSAR FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['acao'];
    
    // EXCLUIR
    if ($tipo === 'excluir') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $pdo->prepare("DELETE FROM tags WHERE id = :id AND usuario_id = :uid")->execute([':id' => $id, ':uid' => $usuario_id]);
            $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Tag removida com sucesso.'];
        }
    }
    
    // SALVAR
    elseif (isset($_POST['nome'])) {
        $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
        // Validação básica de cor hex
        $cor = $_POST['cor'] ?? '#6c757d';
        if (!preg_match('/^#[a-f0-9]{6}$/i', $cor)) $cor = '#6c757d'; 

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($nome) {
            if ($tipo === 'editar' && $id) {
                $sql = "UPDATE tags SET nome = :nome, cor = :cor WHERE id = :id AND usuario_id = :uid";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nome' => $nome, ':cor' => $cor, ':id' => $id, ':uid' => $usuario_id]);
                $msg_texto = 'Tag atualizada!';
            } else {
                $sql = "INSERT INTO tags (usuario_id, nome, cor) VALUES (:uid, :nome, :cor)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':uid' => $usuario_id, ':nome' => $nome, ':cor' => $cor]);
                $msg_texto = 'Tag criada!';
            }
            $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => $msg_texto];
            header("Location: tags.php"); exit;
        }
    }
}

// 2. MODO EDIÇÃO
if (isset($_GET['editar'])) {
    $id_editar = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
    $stmt = $pdo->prepare("SELECT * FROM tags WHERE id = :id AND usuario_id = :uid");
    $stmt->execute([':id' => $id_editar, ':uid' => $usuario_id]);
    $tag_atual = $stmt->fetch();
    
    if ($tag_atual) {
        $acao = 'editar';
        $nome_editar = $tag_atual['nome'];
        $cor_editar = $tag_atual['cor'];
    }
}

// 3. LISTAR
$stmt = $pdo->prepare("SELECT * FROM tags WHERE usuario_id = :uid ORDER BY nome ASC");
$stmt->execute([':uid' => $usuario_id]);
$tags = $stmt->fetchAll();

require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0">Tags & Categorias</h2>
        <p class="text-muted mb-0">Organize suas tarefas com etiquetas coloridas.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 sticky-top" style="top: 100px; z-index: 1;">
            <div class="card-header bg-white fw-bold py-3 border-bottom-0">
                <i class="fas fa-tag me-2 text-primary"></i> 
                <?php echo ($acao === 'editar') ? 'Editar Tag' : 'Nova Tag'; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="acao" value="<?php echo $acao; ?>">
                    <?php if($id_editar): ?>
                        <input type="hidden" name="id" value="<?php echo $id_editar; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Nome da Tag</label>
                        <input type="text" name="nome" id="input-nome" class="form-control" 
                               value="<?php echo htmlspecialchars($nome_editar); ?>" 
                               placeholder="Ex: Bug, Reunião, Design" required
                               oninput="atualizarPreview()">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">Cor da Etiqueta</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" name="cor" id="input-cor" 
                                   class="form-control form-control-color w-100" 
                                   value="<?php echo $cor_editar; ?>" 
                                   title="Escolha uma cor"
                                   oninput="atualizarPreview()">
                        </div>
                    </div>

                    <div class="mb-4 text-center p-4 bg-light rounded border border-dashed">
                        <small class="d-block text-muted mb-2 text-uppercase fw-bold" style="font-size: 0.7rem;">Pré-visualização</small>
                        <span id="badge-preview" class="badge rounded-pill px-3 py-2 fs-6 shadow-sm" 
                              style="background-color: <?php echo $cor_editar; ?>; transition: all 0.3s;">
                            <?php echo $nome_editar ?: 'Nome da Tag'; ?>
                        </span>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary fw-bold">
                            <?php echo ($acao === 'editar') ? 'Salvar Alterações' : 'Criar Tag'; ?>
                        </button>
                        
                        <?php if($acao === 'editar'): ?>
                            <a href="tags.php" class="btn btn-outline-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-uppercase text-muted">
                            <tr>
                                <th class="ps-4">Visualização</th>
                                <th>Nome da Tag</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tags as $t): ?>
                            <tr>
                                <td class="ps-4" style="width: 180px;">
                                    <span class="badge rounded-pill px-3 py-2 shadow-sm" 
                                          style="background-color: <?php echo $t['cor']; ?>; font-weight: 500;">
                                        <?php echo $t['nome']; ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-dark">
                                    <?php echo $t['nome']; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Excluir esta tag?');">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        
                                        <a href="?editar=<?php echo $t['id']; ?>" class="btn btn-sm btn-light text-primary me-1" title="Editar">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>
                                        <button type="submit" class="btn btn-sm btn-light text-danger" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($tags)): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted">
                                        <i class="fas fa-tags fa-3x mb-3 opacity-25"></i><br>
                                        Nenhuma tag criada ainda.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Função para calcular contraste (Texto preto ou branco)
function getContrastYIQ(hexcolor){
    hexcolor = hexcolor.replace("#", "");
    var r = parseInt(hexcolor.substr(0,2),16);
    var g = parseInt(hexcolor.substr(2,2),16);
    var b = parseInt(hexcolor.substr(4,2),16);
    var yiq = ((r*299)+(g*587)+(b*114))/1000;
    return (yiq >= 128) ? 'black' : 'white';
}

function atualizarPreview() {
    const nome = document.getElementById('input-nome').value;
    const cor = document.getElementById('input-cor').value;
    const badge = document.getElementById('badge-preview');
    
    badge.style.backgroundColor = cor;
    badge.style.color = getContrastYIQ(cor); // Ajusta cor do texto automaticamente
    badge.innerText = nome ? nome : 'Nome da Tag';
}

// Roda uma vez ao carregar para ajustar contraste inicial
document.addEventListener('DOMContentLoaded', atualizarPreview);
</script>

<?php require 'includes/footer.php'; ?>