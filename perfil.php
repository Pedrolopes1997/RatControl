<?php
require 'config/db.php';
require_once 'includes/auth.php';

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';

// 1. PROCESSAR FORMULÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';

    // Buscar dados atuais
    $stmt = $pdo->prepare("SELECT senha, avatar FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $usuario_id]);
    $dados_atuais = $stmt->fetch();

    $validado = true;

    // Validação de Senha (se preenchida)
    if (!empty($nova_senha)) {
        if (empty($senha_atual)) {
            $mensagem = '<div class="alert alert-warning">Para alterar a senha, digite sua senha atual.</div>';
            $validado = false;
        } elseif (!password_verify($senha_atual, $dados_atuais['senha'])) {
            $mensagem = '<div class="alert alert-danger">A senha atual está incorreta.</div>';
            $validado = false;
        } elseif ($nova_senha !== $confirma_senha) {
            $mensagem = '<div class="alert alert-warning">A nova senha e a confirmação não coincidem.</div>';
            $validado = false;
        } elseif (strlen($nova_senha) < 6) {
            $mensagem = '<div class="alert alert-warning">A senha deve ter no mínimo 6 caracteres.</div>';
            $validado = false;
        }
    }

    // Processamento de Upload de Avatar
    $avatar_path = $dados_atuais['avatar']; // Mantém o antigo por padrão
    if ($validado && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $permitidos = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $permitidos)) {
            if ($_FILES['avatar']['size'] <= 2 * 1024 * 1024) { // Max 2MB
                // Cria pasta se não existir
                $dir = 'assets/uploads/avatars/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                
                // Remove avatar antigo se existir (para não acumular lixo)
                if ($avatar_path && file_exists($avatar_path)) {
                    unlink($avatar_path);
                }

                $novo_nome = 'user_' . $usuario_id . '_' . uniqid() . '.' . $ext;
                $destino = $dir . $novo_nome;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destino)) {
                    $avatar_path = $destino;
                    // Atualiza sessão imediatamente para refletir no header
                    $_SESSION['usuario_avatar'] = $destino; 
                }
            } else {
                $mensagem = '<div class="alert alert-warning">A imagem deve ter no máximo 2MB.</div>';
                $validado = false;
            }
        } else {
            $mensagem = '<div class="alert alert-warning">Formato de imagem inválido. Use JPG ou PNG.</div>';
            $validado = false;
        }
    }

    if ($validado) {
        try {
            // Constrói Query Dinâmica
            $sql = "UPDATE usuarios SET nome = :nome, avatar = :avatar";
            $params = [':nome' => $nome, ':avatar' => $avatar_path, ':id' => $usuario_id];

            if (!empty($nova_senha)) {
                $sql .= ", senha = :senha";
                $params[':senha'] = password_hash($nova_senha, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id = :id";
            
            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute($params);

            // Atualiza sessão
            $_SESSION['usuario_nome'] = $nome;

            $mensagem = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i> Perfil atualizado com sucesso!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        } catch (PDOException $e) {
            $mensagem = '<div class="alert alert-danger">Erro ao atualizar: ' . $e->getMessage() . '</div>';
        }
    }
}

// 2. BUSCAR DADOS ATUALIZADOS
$stmt = $pdo->prepare("SELECT nome, email, permissao, criado_em, avatar FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $usuario_id]);
$user = $stmt->fetch();

require 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-id-card text-primary me-2"></i> Meu Perfil</h5>
            </div>
            <div class="card-body p-4">
                
                <?php echo $mensagem; ?>

                <form method="POST" enctype="multipart/form-data">
                    
                    <div class="row">
                        <div class="col-md-4 text-center mb-4 border-end">
                            <h6 class="text-muted text-uppercase small fw-bold mb-3">Foto de Perfil</h6>
                            
                            <div class="position-relative d-inline-block">
                                <?php 
                                    // Lógica visual: Se tem avatar, mostra. Se não, gera iniciais.
                                    $avatarUrl = $user['avatar'] ? $user['avatar'] : "https://ui-avatars.com/api/?name=".urlencode($user['nome'])."&background=0e2a47&color=fff&size=150";
                                ?>
                                <img id="preview-avatar" src="<?php echo $avatarUrl; ?>" 
                                     class="rounded-circle shadow-sm border p-1" 
                                     style="width: 150px; height: 150px; object-fit: cover;">
                                
                                <label for="input-avatar" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle shadow hover-scale" 
                                       style="cursor: pointer; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border: 3px solid white;">
                                    <i class="fas fa-camera"></i>
                                </label>
                                <input type="file" name="avatar" id="input-avatar" class="d-none" accept="image/*" onchange="previewImage(this)">
                            </div>
                            <div class="mt-3 small text-muted">
                                Clique na câmera para alterar.<br>Max 2MB (JPG/PNG).
                            </div>
                        </div>

                        <div class="col-md-8 ps-md-4">
                            <h6 class="text-uppercase text-muted fw-bold mb-3 small ls-1">Informações Pessoais</h6>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Nome Completo</label>
                                <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($user['nome']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold">E-mail (Login)</label>
                                <input type="text" class="form-control bg-light text-muted" value="<?php echo $user['email']; ?>" readonly>
                            </div>

                            <div class="d-flex align-items-center gap-3 bg-light p-3 rounded border mb-4">
                                <div>
                                    <span class="d-block small text-muted">Permissão</span>
                                    <span class="badge bg-primary"><?php echo strtoupper($user['permissao']); ?></span>
                                </div>
                                <div class="vr"></div>
                                <div>
                                    <span class="d-block small text-muted">Membro Desde</span>
                                    <span class="fw-bold text-dark"><?php echo date('d/m/Y', strtotime($user['criado_em'])); ?></span>
                                </div>
                            </div>

                            <hr class="my-4">

                            <h6 class="text-uppercase text-muted fw-bold mb-3 small ls-1">Alterar Senha <small class="text-muted fw-normal">(Opcional)</small></h6>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Senha Atual</label>
                                <div class="input-group">
                                    <input type="password" name="senha_atual" id="senha_atual" class="form-control" placeholder="Necessário apenas se for mudar a senha">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePass('senha_atual')"><i class="far fa-eye"></i></button>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold">Nova Senha</label>
                                    <div class="input-group">
                                        <input type="password" name="nova_senha" id="nova_senha" class="form-control" placeholder="Mínimo 6 dígitos">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePass('nova_senha')"><i class="far fa-eye"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold">Confirmar</label>
                                    <div class="input-group">
                                        <input type="password" name="confirma_senha" id="confirma_senha" class="form-control">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePass('confirma_senha')"><i class="far fa-eye"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-2">
                                <button type="submit" class="btn btn-success fw-bold px-4 py-2 shadow-sm">
                                    <i class="fas fa-save me-2"></i> Salvar Alterações
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Preview da Imagem
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) { document.getElementById('preview-avatar').src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
    }
}

// Mostrar/Ocultar Senha
function togglePass(id) {
    const input = document.getElementById(id);
    const icon = input.nextElementSibling.querySelector('i');
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash');
    } else {
        input.type = "password";
        icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye');
    }
}
</script>

<?php require 'includes/footer.php'; ?>