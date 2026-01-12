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

    // Buscar hash da senha atual no banco
    $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = :id");
    $stmt->execute([':id' => $usuario_id]);
    $hash_atual = $stmt->fetchColumn();

    $validado = true;

    // Se tentou mudar a senha
    if (!empty($nova_senha)) {
        // Verifica se digitou a senha atual corretamente
        if (!password_verify($senha_atual, $hash_atual)) {
            $mensagem = '<div class="alert alert-danger">A senha atual está incorreta.</div>';
            $validado = false;
        } elseif ($nova_senha !== $confirma_senha) {
            $mensagem = '<div class="alert alert-warning">A nova senha e a confirmação não coincidem.</div>';
            $validado = false;
        } elseif (strlen($nova_senha) < 6) {
            $mensagem = '<div class="alert alert-warning">A nova senha deve ter pelo menos 6 caracteres.</div>';
            $validado = false;
        }
    }

    if ($validado) {
        try {
            // Atualiza Nome
            $sql = "UPDATE usuarios SET nome = :nome";
            $params = [':nome' => $nome, ':id' => $usuario_id];

            // Atualiza Senha se fornecida
            if (!empty($nova_senha)) {
                $sql .= ", senha = :senha";
                $params[':senha'] = password_hash($nova_senha, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id = :id";
            
            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute($params);

            // Atualiza o nome na sessão também
            $_SESSION['usuario_nome'] = $nome;

            $mensagem = '<div class="alert alert-success">Perfil atualizado com sucesso!</div>';
        } catch (PDOException $e) {
            $mensagem = '<div class="alert alert-danger">Erro ao atualizar perfil.</div>';
        }
    }
}

// 2. BUSCAR DADOS PARA EXIBIR
$stmt = $pdo->prepare("SELECT nome, email, permissao, criado_em FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $usuario_id]);
$user = $stmt->fetch();

require 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-user-cog"></i> Meu Perfil
            </div>
            <div class="card-body">
                <?php echo $mensagem; ?>

                <form method="POST">
                    <h5 class="mb-3 border-bottom pb-2">Dados Pessoais</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome Completo</label>
                            <input type="text" name="nome" class="form-control" value="<?php echo htmlspecialchars($user['nome']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">E-mail (Login)</label>
                            <input type="text" class="form-control bg-light" value="<?php echo $user['email']; ?>" readonly>
                            <div class="form-text">O e-mail não pode ser alterado.</div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Nível de Acesso:</label>
                        <span class="badge bg-secondary"><?php echo strtoupper($user['permissao']); ?></span>
                        <span class="text-muted ms-2 small">Membro desde <?php echo date('d/m/Y', strtotime($user['criado_em'])); ?></span>
                    </div>

                    <h5 class="mb-3 border-bottom pb-2">Alterar Senha <small class="text-muted fs-6 fw-normal">(Opcional)</small></h5>

                    <div class="mb-3">
                        <label class="form-label">Senha Atual</label>
                        <input type="password" name="senha_atual" class="form-control" placeholder="Necessário apenas se for mudar a senha">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nova Senha</label>
                            <input type="password" name="nova_senha" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirmar Nova Senha</label>
                            <input type="password" name="confirma_senha" class="form-control">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>