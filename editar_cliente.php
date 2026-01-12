<?php
require 'config/db.php';
require_once 'includes/auth.php';

if (!isset($_SESSION['usuario_permissao']) || $_SESSION['usuario_permissao'] !== 'admin') die("Acesso negado.");

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { header("Location: clientes.php"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_SPECIAL_CHARS);
    $site = filter_input(INPUT_POST, 'site', FILTER_SANITIZE_URL);
    $valor_hora = filter_input(INPUT_POST, 'valor_hora', FILTER_VALIDATE_FLOAT);
    $moeda = $_POST['moeda'];
    $status = $_POST['status'];

    $params = [':nome' => $nome, ':email' => $email, ':tel' => $telefone, ':site' => $site, ':val' => $valor_hora, ':moeda' => $moeda, ':st' => $status, ':id' => $id];
    $logo_sql = "";

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $destino = 'assets/uploads/logo_' . uniqid() . '.' . $ext;
            if (!is_dir('assets/uploads')) mkdir('assets/uploads', 0755, true);
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $destino)) {
                $logo_sql = ", logo = :logo";
                $params[':logo'] = $destino;
            }
        }
    }

    $pdo->prepare("UPDATE clientes SET nome=:nome, email=:email, telefone=:tel, site=:site, valor_hora=:val, moeda=:moeda, status=:st $logo_sql WHERE id=:id")->execute($params);
    $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Cliente atualizado!'];
    header("Location: clientes.php"); exit;
}

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
$stmt->execute([':id' => $id]);
$cliente = $stmt->fetch();
if (!$cliente) die("Cliente não encontrado.");

require 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="d-flex align-items-center mb-3">
            <a href="clientes.php" class="btn btn-outline-secondary me-3"><i class="fas fa-arrow-left"></i> Voltar</a>
            <h4 class="mb-0 fw-bold">Editar Cliente</h4>
        </div>

        <div class="card shadow border-0">
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <?php 
                                // Se tiver logo, usa. Se não, usa o Placeholder.
                                $imgShow = $cliente['logo'] ? $cliente['logo'] : "https://placehold.co/150x100/f0f0f0/cccccc?text=Sem+Logo"; 
                            ?>
                            <img id="preview-logo" src="<?php echo $imgShow; ?>" 
                                 class="rounded border shadow-sm" 
                                 style="width: 150px; height: 100px; object-fit: contain; background: #fff; padding: 5px;">
                            
                            <label for="input-logo" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2 shadow" style="cursor: pointer;"><i class="fas fa-camera"></i></label>
                            <input type="file" name="logo" id="input-logo" class="d-none" accept="image/*" onchange="previewImage(this)">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Nome da Empresa</label>
                        <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($cliente['nome']); ?>" placeholder="Ex: Microsoft Corp" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold text-muted">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>" placeholder="contato@empresa.com">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-muted">WhatsApp/Fone</label>
                            <input type="text" name="telefone" class="form-control" value="<?php echo htmlspecialchars($cliente['telefone'] ?? ''); ?>" placeholder="(00) 00000-0000" maxlength="15" onkeyup="handlePhone(event)">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Website</label>
                        <input type="text" name="site" class="form-control" value="<?php echo htmlspecialchars($cliente['site'] ?? ''); ?>" placeholder="https://www.site.com">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="small fw-bold text-muted">Valor</label>
                            <input type="number" name="valor_hora" class="form-control" step="0.01" value="<?php echo $cliente['valor_hora']; ?>" placeholder="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold text-muted">Moeda</label>
                            <select name="moeda" class="form-select">
                                <option value="R$" <?php echo ($cliente['moeda']=='R$')?'selected':''; ?>>R$</option>
                                <option value="USD" <?php echo ($cliente['moeda']=='USD')?'selected':''; ?>>USD</option>
                                <option value="EUR" <?php echo ($cliente['moeda']=='EUR')?'selected':''; ?>>EUR</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold text-muted">Status</label>
                            <select name="status" class="form-select">
                                <option value="ativo" <?php echo ($cliente['status']=='ativo')?'selected':''; ?>>Ativo</option>
                                <option value="inativo" <?php echo ($cliente['status']=='inativo')?'selected':''; ?>>Inativo</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-grid mt-4"><button type="submit" class="btn btn-primary fw-bold">Salvar Alterações</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) { document.getElementById('preview-logo').src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
    }
}

// MÁSCARA
const handlePhone = (event) => {
  let input = event.target
  input.value = phoneMask(input.value)
}

const phoneMask = (value) => {
  if (!value) return ""
  value = value.replace(/\D/g,'')
  value = value.replace(/(\d{2})(\d)/,"($1) $2")
  value = value.replace(/(\d)(\d{4})$/,"$1-$2")
  return value
}
</script>
<?php require 'includes/footer.php'; ?>