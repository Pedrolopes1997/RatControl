<?php
// login.php - Versão Segura e Localizada
require 'config/db.php';

// Configuração segura de sessão (se ainda não iniciou)
if (session_status() === PHP_SESSION_NONE) {
    // Define tempo de vida do cookie da sessão (ex: 1 dia)
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.gc_maxlifetime', 86400);
    session_start();
}

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];

    if ($email && $senha) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
            // SEGURANÇA CRÍTICA: Regenerar ID da sessão para evitar sequestro
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario_nome'] = $user['nome'];
            $_SESSION['usuario_email'] = $user['email'];
            $_SESSION['usuario_permissao'] = $user['permissao'];
            
            header("Location: index.php");
            exit;
        } else {
            $erro = "E-mail ou senha incorretos.";
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
    <title>Login | RatControl</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; height: 100vh; overflow: hidden; background-color: #fff; }
        .row-full { height: 100vh; }
        
        /* LADO ESQUERDO (VISUAL) */
        .bg-login {
            background: linear-gradient(135deg, #0e2a47 0%, #0043a8 100%), url('https://images.unsplash.com/photo-1497215728101-856f4ea42174?q=80&w=2070&auto=format&fit=crop');
            background-blend-mode: multiply;
            background-size: cover;
            background-position: center;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            position: relative;
        }
        .brand-hero { font-size: 3.5rem; font-weight: 800; letter-spacing: -1px; margin-bottom: 1rem; }
        .text-hero { font-size: 1.2rem; opacity: 0.85; max-width: 500px; font-weight: 300; line-height: 1.6; }

        /* LADO DIREITO (FORM) */
        .login-section { background: white; display: flex; align-items: center; justify-content: center; position: relative; }
        .login-box { width: 100%; max-width: 400px; padding: 2rem; }
        .logo-mobile { display: none; margin-bottom: 2rem; }
        
        .form-floating > .form-control:focus ~ label { color: #0e2a47; }
        .form-control:focus { border-color: #0e2a47; box-shadow: 0 0 0 0.25rem rgba(14, 42, 71, 0.15); }
        
        .btn-primary {
            background: #0e2a47; border: none; padding: 14px; font-weight: 600; border-radius: 8px; transition: all 0.3s;
        }
        .btn-primary:hover { background: #0043a8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 67, 168, 0.3); }

        @media (max-width: 992px) {
            .bg-login { display: none; }
            .row-full { height: auto; min-height: 100vh; }
            .login-section { height: 100vh; align-items: center; }
            .logo-mobile { display: block; text-align: center; }
            .logo-mobile i { font-size: 3rem; color: #0e2a47; }
        }
    </style>
</head>
<body>

<div class="container-fluid p-0">
    <div class="row g-0 row-full">
        
        <div class="col-lg-7 d-none d-lg-flex bg-login">
            <div class="mb-4"><i class="fas fa-mouse fa-3x text-white-50"></i></div>
            <h1 class="brand-hero">RatControl.</h1>
            <p class="text-hero">A plataforma definitiva para gerir sua consultoria, controlar tempos e profissionalizar a entrega para seus clientes.</p>
            
            <div class="mt-5 pt-3 border-top border-white border-opacity-10">
                <div class="d-flex align-items-center gap-4 text-white-50">
                    <small><i class="fas fa-check-circle me-2 text-success"></i> Gestão de Tempo</small>
                    <small><i class="fas fa-check-circle me-2 text-success"></i> Faturas PDF</small>
                    <small><i class="fas fa-check-circle me-2 text-success"></i> Portal do Cliente</small>
                </div>
            </div>
        </div>

        <div class="col-lg-5 login-section">
            <div class="login-box">
                
                <div class="logo-mobile">
                    <i class="fas fa-mouse"></i>
                    <h3 class="fw-bold mt-2 text-dark">RatControl</h3>
                </div>

                <div class="mb-4">
                    <h3 class="fw-bold text-dark mb-1">Bem-vindo de volta</h3>
                    <p class="text-muted">Insira suas credenciais para acessar.</p>
                </div>

                <?php if($erro): ?>
                    <div class="alert alert-danger d-flex align-items-center small py-2" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $erro; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control <?php echo $erro ? 'is-invalid' : ''; ?>" id="email" name="email" placeholder="nome@exemplo.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <label for="email">E-mail</label>
                    </div>
                    
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control <?php echo $erro ? 'is-invalid' : ''; ?>" id="senha" name="senha" placeholder="Senha" required>
                        <label for="senha">Senha</label>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="lembrar">
                            <label class="form-check-label small text-muted" for="lembrar">Lembrar-me</label>
                        </div>
                        <a href="#" class="small text-decoration-none fw-bold text-muted">Esqueceu a senha?</a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mb-4 shadow-sm">
                        Entrar na Plataforma <i class="fas fa-arrow-right ms-2"></i>
                    </button>

                </form>
            </div>
            
            <div class="position-absolute bottom-0 w-100 text-center pb-3 d-none d-lg-block">
                <small class="text-muted" style="font-size: 0.75rem;">&copy; <?php echo date('Y'); ?> WeCare Consultoria.</small>
            </div>
        </div>

    </div>
</div>

</body>
</html>