<?php
// register.php - Versão Segura e Localizada
require 'config/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }

$erro = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];
    $confirmar = $_POST['confirmar_senha'];

    if ($nome && $email && $senha) {
        if (strlen($senha) < 6) {
            $erro = "A senha deve ter no mínimo 6 caracteres.";
        } elseif ($senha !== $confirmar) {
            $erro = "As senhas não coincidem.";
        } else {
            // Verifica duplicidade
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
            $stmt->execute([':email' => $email]);
            
            if ($stmt->rowCount() > 0) {
                $erro = "Este e-mail já está cadastrado.";
            } else {
                // Lógica de Primeiro Usuário = Admin
                $countUsers = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
                $permissao = ($countUsers == 0) ? 'admin' : 'user';

                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $sql = "INSERT INTO usuarios (nome, email, senha, permissao) VALUES (:nome, :email, :senha, :perm)";
                
                try {
                    $pdo->prepare($sql)->execute([
                        ':nome' => $nome, 
                        ':email' => $email, 
                        ':senha' => $hash,
                        ':perm' => $permissao
                    ]);
                    $sucesso = true;
                } catch (Exception $e) {
                    $erro = "Erro ao criar conta. Tente novamente.";
                }
            }
        }
    } else {
        $erro = "Preencha todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta | RatControl</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; height: 100vh; overflow: hidden; background-color: #fff; }
        .row-full { height: 100vh; }
        
        /* LADO ESQUERDO (FORM) */
        .register-section { background: white; display: flex; align-items: center; justify-content: center; position: relative; }
        .register-box { width: 100%; max-width: 450px; padding: 2rem; }
        
        .form-floating > .form-control:focus ~ label { color: #198754; }
        .form-control:focus { border-color: #198754; box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.15); }
        
        .btn-success-custom {
            background: #198754; border: none; padding: 12px; font-weight: 600; border-radius: 8px; color: white; width: 100%; transition: all 0.3s;
        }
        .btn-success-custom:hover { background: #146c43; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3); }

        /* LADO DIREITO (VISUAL) */
        .bg-register {
            background: linear-gradient(135deg, #198754 0%, #0f5132 100%), url('https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=2070&auto=format&fit=crop');
            background-blend-mode: multiply;
            background-size: cover;
            background-position: center;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
        }
        .brand-hero { font-size: 3rem; font-weight: 800; letter-spacing: -1px; margin-bottom: 1rem; }

        @media (max-width: 992px) {
            .bg-register { display: none; }
            .row-full { height: auto; min-height: 100vh; }
            .register-section { height: 100vh; }
        }
    </style>
</head>
<body>

<div class="container-fluid p-0">
    <div class="row g-0 row-full">
        
        <div class="col-lg-5 register-section order-2 order-lg-1">
            <div class="register-box">
                
                <div class="d-block d-lg-none text-center mb-4">
                    <i class="fas fa-mouse fa-2x text-success"></i>
                </div>

                <h2 class="fw-bold text-dark mb-1">Comece Agora</h2>
                <p class="text-muted mb-4">Crie sua conta e organize sua agência.</p>

                <?php if($erro): ?>
                    <div class="alert alert-danger d-flex align-items-center small py-2">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $erro; ?>
                    </div>
                <?php endif; ?>

                <?php if($sucesso): ?>
                    <div class="text-center py-5">
                        <div class="mb-3"><i class="fas fa-check-circle fa-4x text-success"></i></div>
                        <h4 class="fw-bold">Conta Criada!</h4>
                        <p class="text-muted">Sua conta foi configurada com sucesso.</p>
                        <a href="login.php" class="btn btn-success-custom mt-3">Ir para Login</a>
                    </div>
                <?php else: ?>

                <form method="POST">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" name="nome" placeholder="Seu Nome" required value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>">
                        <label>Nome Completo</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" name="email" placeholder="E-mail" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <label>Endereço de E-mail</label>
                    </div>
                    
                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="password" class="form-control" name="senha" placeholder="Senha" required minlength="6">
                                <label>Senha (min 6)</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="password" class="form-control" name="confirmar_senha" placeholder="Confirmar" required>
                                <label>Confirmar</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success-custom mb-4 shadow-sm">Cadastrar Conta</button>

                    <div class="text-center">
                        <span class="text-muted small">Já tem conta?</span>
                        <a href="login.php" class="small fw-bold text-decoration-none ms-1 text-success">Fazer Login</a>
                    </div>
                </form>
                <?php endif; ?>
                
                <div class="text-center mt-5 text-muted d-lg-none">
                    <small>&copy; <?php echo date('Y'); ?> RatControl</small>
                </div>
            </div>
        </div>

        <div class="col-lg-7 d-none d-lg-flex bg-register order-1 order-lg-2">
            <h1 class="brand-hero">Junte-se a nós.</h1>
            <p class="text-white opacity-75 fs-5">"O RatControl transformou a forma como gerimos os tempos da equipe. Simples, rápido e eficaz."</p>
            <div class="mt-4 text-warning">
                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
            </div>
        </div>

    </div>
</div>

</body>
</html>