<?php
require 'config/db.php';
require_once 'includes/auth.php';

$eh_admin = (isset($_SESSION['usuario_permissao']) && $_SESSION['usuario_permissao'] === 'admin');

// 1. GERAR TOKEN (Link do Portal)
if (isset($_GET['gerar_token']) && $eh_admin) {
    $id_cli = filter_input(INPUT_GET, 'gerar_token', FILTER_VALIDATE_INT);
    $token = bin2hex(random_bytes(16)); 
    $pdo->prepare("UPDATE clientes SET token_acesso = :t WHERE id = :id")->execute([':t' => $token, ':id' => $id_cli]);
    $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Link do portal gerado com sucesso!'];
    header("Location: clientes.php"); exit;
}

// 2. EXCLUIR CLIENTE
if (isset($_GET['excluir']) && $eh_admin) {
    $id_cli = filter_input(INPUT_GET, 'excluir', FILTER_VALIDATE_INT);
    try {
        $pdo->prepare("DELETE FROM clientes WHERE id = :id")->execute([':id' => $id_cli]);
        $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Cliente removido.'];
    } catch (PDOException $e) {
        $_SESSION['toast_msg'] = ['tipo' => 'danger', 'texto' => 'Não é possível excluir: Cliente possui projetos ou histórico.'];
    }
    header("Location: clientes.php"); exit;
}

// 3. CADASTRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome_cliente'])) {
    if (!$eh_admin) die("Acesso negado.");
    
    $nome     = filter_input(INPUT_POST, 'nome_cliente', FILTER_SANITIZE_SPECIAL_CHARS);
    $doc      = filter_input(INPUT_POST, 'documento', FILTER_SANITIZE_SPECIAL_CHARS);
    $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_SPECIAL_CHARS);
    $site     = filter_input(INPUT_POST, 'site', FILTER_SANITIZE_URL);
    $val_hora = filter_input(INPUT_POST, 'valor_hora', FILTER_VALIDATE_FLOAT);
    $moeda    = $_POST['moeda'];
    
    // Upload de Logo
    $logo_path = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            $_SESSION['toast_msg'] = ['tipo' => 'warning', 'texto' => 'A logo deve ter no máximo 2MB.'];
        } else {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $novo_nome = 'logo_' . uniqid() . '.' . $ext;
                $destino = 'assets/uploads/' . $novo_nome;
                if (!is_dir('assets/uploads')) mkdir('assets/uploads', 0755, true);
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $destino)) {
                    $logo_path = $destino;
                }
            }
        }
    }

    if ($nome) {
        $sql = "INSERT INTO clientes (nome, documento, email, telefone, site, valor_hora, moeda, logo, status) 
                VALUES (:nome, :doc, :email, :tel, :site, :val, :moeda, :logo, 'ativo')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nome' => $nome, 
            ':doc'  => $doc,
            ':email' => $email, 
            ':tel' => $telefone, 
            ':site' => $site, 
            ':val' => $val_hora ?: 0.00, 
            ':moeda' => $moeda, 
            ':logo' => $logo_path
        ]);
        $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Cliente cadastrado com sucesso!'];
        header("Location: clientes.php"); exit;
    }
}

$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome ASC")->fetchAll();

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$base_url = rtrim($base_url, '/');

require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0">Carteira de Clientes</h2>
        <p class="text-muted mb-0">Gerencie contatos, contratos e acessos.</p>
    </div>
</div>

<div class="row">
    <?php if ($eh_admin): ?>
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold py-3 text-primary">
                <i class="fas fa-user-plus me-2"></i> Novo Cliente
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <img id="preview-logo" src="https://placehold.co/150x100/f8f9fa/adb5bd?text=Logo+Aqui" 
                                 class="rounded border shadow-sm" 
                                 style="width: 150px; height: 100px; object-fit: contain; background: #fff;">
                            
                            <label for="input-logo" class="position-absolute bottom-0 end-0 translate-middle-x bg-primary text-white rounded-circle shadow hover-scale" 
                                   style="cursor: pointer; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; margin-bottom: -10px;">
                                <i class="fas fa-camera fa-xs"></i>
                            </label>
                            <input type="file" name="logo" id="input-logo" class="d-none" accept="image/*" onchange="previewImage(this)">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Nome da Empresa *</label>
                        <input type="text" name="nome_cliente" class="form-control" placeholder="Ex: Microsoft Corp" required>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">CPF ou CNPJ</label>
                        <input type="text" name="documento" class="form-control" placeholder="00.000.000/0000-00" onkeyup="mascaraDoc(this)" maxlength="18">
                        <div class="form-text" style="font-size: 0.75rem;">Essencial para gerar propostas.</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold text-muted">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="contato@empresa.com">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-muted">Telefone</label>
                            <input type="text" name="telefone" class="form-control" placeholder="(00) 00000-0000" onkeyup="handlePhone(event)" maxlength="15">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Website</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-globe text-muted small"></i></span>
                            <input type="text" name="site" class="form-control border-start-0 ps-0" placeholder="www.site.com">
                        </div>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-md-6">
                            <label class="small fw-bold text-muted">Valor Hora</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">$</span>
                                <input type="number" name="valor_hora" class="form-control" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-muted">Moeda</label>
                            <select name="moeda" class="form-select">
                                <option value="R$">R$ (BRL)</option>
                                <option value="USD">USD ($)</option>
                                <option value="EUR">EUR (€)</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="fas fa-check me-2"></i> Cadastrar
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="<?php echo $eh_admin ? 'col-lg-8' : 'col-12'; ?>">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-uppercase small text-muted">
                        <tr>
                            <th class="ps-4">Empresa</th>
                            <th>Contato</th>
                            <th>Contrato</th>
                            <th>Status</th>
                            <th>Portal</th>
                            <?php if ($eh_admin): ?><th class="text-end pe-4">Ações</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($clientes as $c): 
                            $link_portal = $c['token_acesso'] ? "$base_url/portal.php?t=" . $c['token_acesso'] : null;
                            $avatar = "https://ui-avatars.com/api/?name=".urlencode($c['nome'])."&background=random&color=fff&size=128&font-size=0.5";
                            $img_src = $c['logo'] ? $c['logo'] : $avatar;
                        ?>
                        <tr class="<?php echo ($c['status'] === 'inativo') ? 'opacity-50' : ''; ?>">
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo $img_src; ?>" class="rounded-3 shadow-sm me-3 border bg-white" 
                                         style="width: 50px; height: 50px; object-fit: contain; padding: 2px;">
                                    
                                    <div style="line-height: 1.2;">
                                        <div class="fw-bold text-dark mb-1"><?php echo $c['nome']; ?></div>
                                        <?php if($c['documento']): ?>
                                            <span class="badge bg-light text-secondary border fw-normal" style="font-size: 0.65rem;"><?php echo $c['documento']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($c['email']): ?><div class="small text-muted"><i class="far fa-envelope me-1 text-primary"></i> <?php echo $c['email']; ?></div><?php endif; ?>
                                <?php if($c['telefone']): ?><div class="small text-muted"><i class="fas fa-phone me-1 text-success"></i> <?php echo $c['telefone']; ?></div><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace">
                                    <?php echo $c['moeda'].' '.number_format($c['valor_hora'], 2, ',', '.'); ?>/h
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $c['status']=='ativo'?'bg-success-subtle text-success':'bg-secondary-subtle text-secondary'; ?> rounded-pill">
                                    <?php echo ucfirst($c['status']); ?>
                                </span>
                            </td>
                            <td style="width: 140px;">
                                <?php if($link_portal): ?>
                                    <div class="input-group input-group-sm">
                                        <button class="btn btn-light border" onclick="copiarLink('<?php echo $link_portal; ?>')" title="Copiar Link"><i class="far fa-copy"></i></button>
                                        <a href="<?php echo $link_portal; ?>" target="_blank" class="btn btn-light border text-primary" title="Abrir Portal"><i class="fas fa-external-link-alt"></i></a>
                                    </div>
                                <?php else: ?>
                                    <?php if($eh_admin): ?>
                                        <a href="?gerar_token=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-dark w-100 rounded-pill"><i class="fas fa-key me-1"></i> Gerar</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <?php if ($eh_admin): ?>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <a href="editar_cliente.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-light text-primary" title="Editar"><i class="fas fa-edit"></i></a>
                                    <a href="?excluir=<?php echo $c['id']; ?>" class="btn btn-sm btn-light text-danger" title="Excluir" onclick="return confirm('Tem certeza? Isso apagará o cliente se ele não tiver projetos.');"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($clientes)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">Nenhum cliente cadastrado.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Pré-visualização de imagem
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) { document.getElementById('preview-logo').src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
    }
}

function copiarLink(texto) {
    navigator.clipboard.writeText(texto).then(() => {
        alert("Link do portal copiado!");
    });
}

// MÁSCARA TELEFONE
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

// MÁSCARA CPF/CNPJ
function mascaraDoc(i) {
    var v = i.value;
    if(isNaN(v[v.length-1])){ 
       i.value = v.substring(0, v.length-1);
       return;
    }
    i.setAttribute("maxlength", "18");
    var v = i.value;
    v = v.replace(/\D/g,"");
    if (v.length <= 11) {
        v = v.replace(/(\d{3})(\d)/,"$1.$2");
        v = v.replace(/(\d{3})(\d)/,"$1.$2");
        v = v.replace(/(\d{3})(\d{1,2})$/,"$1-$2");
    } else {
        v = v.replace(/^(\d{2})(\d)/, "$1.$2");
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
        v = v.replace(/\.(\d{3})(\d)/, ".$1/$2");
        v = v.replace(/(\d{4})(\d)/, "$1-$2");
    }
    i.value = v;
}
</script>

<?php require 'includes/footer.php'; ?>