<?php
require 'config/db.php';
require_once 'includes/auth.php';

$eh_admin = (isset($_SESSION['usuario_permissao']) && $_SESSION['usuario_permissao'] === 'admin');
$mensagem = '';

// 1. GERAR TOKEN
if (isset($_GET['gerar_token']) && $eh_admin) {
    $id_cli = filter_input(INPUT_GET, 'gerar_token', FILTER_VALIDATE_INT);
    $token = bin2hex(random_bytes(16)); 
    $pdo->prepare("UPDATE clientes SET token_acesso = :t WHERE id = :id")->execute([':t' => $token, ':id' => $id_cli]);
    $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Link do portal gerado!'];
    header("Location: clientes.php"); exit;
}

// 2. CADASTRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nome_cliente'])) {
    if (!$eh_admin) die("Acesso negado.");
    
    $nome = filter_input(INPUT_POST, 'nome_cliente', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_SPECIAL_CHARS);
    $site = filter_input(INPUT_POST, 'site', FILTER_SANITIZE_URL);
    $valor_hora = filter_input(INPUT_POST, 'valor_hora', FILTER_VALIDATE_FLOAT);
    $moeda = $_POST['moeda'];
    
    $logo_path = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
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

    if ($nome) {
        $sql = "INSERT INTO clientes (nome, email, telefone, site, valor_hora, moeda, logo) 
                VALUES (:nome, :email, :tel, :site, :val, :moeda, :logo)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nome' => $nome, ':email' => $email, ':tel' => $telefone, ':site' => $site, ':val' => $valor_hora ?: 0.00, ':moeda' => $moeda, ':logo' => $logo_path]);
        $_SESSION['toast_msg'] = ['tipo' => 'success', 'texto' => 'Cliente cadastrado!'];
        header("Location: clientes.php"); exit;
    }
}

$clientes = $pdo->query("SELECT * FROM clientes ORDER BY id DESC")->fetchAll();
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$base_url = rtrim($base_url, '/');

require 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0">Clientes</h2>
        <p class="text-muted mb-0">Gerencie sua carteira e contatos.</p>
    </div>
</div>

<div class="row">
    <?php if ($eh_admin): ?>
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-bold py-3"><i class="fas fa-plus-circle text-primary me-2"></i> Novo Cliente</div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <img id="preview-logo" src="https://placehold.co/150x100/f0f0f0/cccccc?text=Sem+Logo" 
                                 class="rounded border shadow-sm" 
                                 style="width: 150px; height: 100px; object-fit: contain; background: #fff; padding: 5px;">
                            
                            <label for="input-logo" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2 shadow" 
                                   style="cursor: pointer; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-camera small"></i>
                            </label>
                            <input type="file" name="logo" id="input-logo" class="d-none" accept="image/*" onchange="previewImage(this)">
                        </div>
                        <div class="small text-muted mt-2">Clique para enviar logo</div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Nome da Empresa *</label>
                        <input type="text" name="nome_cliente" class="form-control" placeholder="Ex: Microsoft Corp" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold text-muted">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="contato@empresa.com">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold text-muted">WhatsApp/Fone</label>
                            <input type="text" name="telefone" class="form-control" placeholder="(00) 00000-0000" maxlength="15" onkeyup="handlePhone(event)">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold text-muted">Website</label>
                        <input type="text" name="site" class="form-control" placeholder="https://www.site.com">
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-md-6">
                            <label class="small fw-bold text-muted">Valor Hora</label>
                            <input type="number" name="valor_hora" class="form-control" step="0.01" placeholder="0.00">
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

                    <button type="submit" class="btn btn-primary w-100 fw-bold">Cadastrar Cliente</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="<?php echo $eh_admin ? 'col-lg-8' : 'col-12'; ?>">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr><th class="ps-4">Empresa</th><th>Contato</th><th>Valor/Hora</th><th>Status</th><th>Portal</th><?php if ($eh_admin): ?><th class="text-end pe-4">Ações</th><?php endif; ?></tr>
                    </thead>
                    <tbody>
                        <?php foreach($clientes as $c): 
                            $link_portal = $c['token_acesso'] ? "$base_url/portal.php?t=" . $c['token_acesso'] : null;
                            // Se não tiver logo, usa um gerador de iniciais bonito
                            $logo = $c['logo'] ? $c['logo'] : "https://ui-avatars.com/api/?name=".urlencode($c['nome'])."&background=random&color=fff&size=128";
                        ?>
                        <tr class="<?php echo ($c['status'] === 'inativo') ? 'table-light opacity-50' : ''; ?>">
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo $logo; ?>" class="rounded shadow-sm me-3 bg-white" 
                                         style="width: 50px; height: 50px; object-fit: contain; padding: 2px; border: 1px solid #dee2e6;"
                                         onerror="this.src='https://placehold.co/50?text=IMG'">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $c['nome']; ?></div>
                                        <?php if($c['site']): ?><a href="<?php echo strpos($c['site'], 'http')===0?$c['site']:'http://'.$c['site']; ?>" target="_blank" class="small text-primary text-decoration-none"><i class="fas fa-link me-1"></i>Site</a><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if($c['email']): ?><div class="small text-muted"><i class="far fa-envelope me-1"></i> <?php echo $c['email']; ?></div><?php endif; ?>
                                <?php if($c['telefone']): ?><div class="small text-muted"><i class="fas fa-phone me-1"></i> <?php echo $c['telefone']; ?></div><?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo $c['moeda'].' '.number_format($c['valor_hora'], 2, ',', '.'); ?></span></td>
                            <td><span class="badge <?php echo $c['status']=='ativo'?'bg-success bg-opacity-10 text-success':'bg-secondary'; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                            <td style="width: 160px;">
                                <?php if($link_portal): ?>
                                    <div class="btn-group btn-group-sm w-100">
                                        <button class="btn btn-outline-secondary" onclick="copiarLink('link-<?php echo $c['id']; ?>')"><i class="far fa-copy"></i></button>
                                        <a href="<?php echo $link_portal; ?>" target="_blank" class="btn btn-outline-primary"><i class="fas fa-external-link-alt"></i></a>
                                        <input type="hidden" value="<?php echo $link_portal; ?>" id="link-<?php echo $c['id']; ?>">
                                    </div>
                                <?php else: ?>
                                    <?php if($eh_admin): ?><a href="?gerar_token=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-dark w-100">Gerar Link</a><?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <?php if ($eh_admin): ?><td class="text-end pe-4"><a href="editar_cliente.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-light text-primary"><i class="fas fa-edit"></i></a></td><?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Pré-visualização
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) { document.getElementById('preview-logo').src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
    }
}
function copiarLink(id) { navigator.clipboard.writeText(document.getElementById(id).value).then(()=>alert("Link copiado!")); }

// MÁSCARA DE TELEFONE (JS Puro)
const handlePhone = (event) => {
  let input = event.target
  input.value = phoneMask(input.value)
}

const phoneMask = (value) => {
  if (!value) return ""
  value = value.replace(/\D/g,'') // Remove tudo que não é número
  value = value.replace(/(\d{2})(\d)/,"($1) $2") // Coloca parênteses
  value = value.replace(/(\d)(\d{4})$/,"$1-$2") // Coloca hífen
  return value
}
</script>
<?php require 'includes/footer.php'; ?>