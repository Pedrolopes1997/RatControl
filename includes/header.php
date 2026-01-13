<?php
// includes/header.php

// 1. GARANTIA DE SESSÃO E CONEXÃO
if (session_status() === PHP_SESSION_NONE) {
    // Configurações de cookie seguro (opcional, mas recomendado)
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.gc_maxlifetime', 86400);
    session_start();
}

// Garante conexão com banco
if (!isset($pdo)) {
    // Tenta achar o db.php subindo níveis se necessário
    $paths = [
        __DIR__ . '/../config/db.php',
        dirname(__DIR__) . '/config/db.php',
        'config/db.php'
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

// Verifica Autenticação (exceto se for a página de login)
if (!isset($_SESSION['usuario_id']) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header("Location: login.php");
    exit;
}

// 2. SISTEMA DE NOTIFICAÇÃO (TOAST)
$toast_html = "";
if (isset($_SESSION['toast_msg'])) {
    $tipo = $_SESSION['toast_msg']['tipo'] ?? 'info'; // success, danger, warning, info
    $texto = $_SESSION['toast_msg']['texto'] ?? '';
    
    // Mapeia cores do Bootstrap
    $bgClass = match($tipo) {
        'success' => 'text-bg-success',
        'danger'  => 'text-bg-danger',
        'warning' => 'text-bg-warning',
        default   => 'text-bg-primary'
    };

    $toast_html = "
    <div class='toast-container position-fixed top-0 end-0 p-3' style='z-index: 9999'>
        <div id='liveToast' class='toast align-items-center $bgClass border-0 show' role='alert' aria-live='assertive' aria-atomic='true'>
            <div class='d-flex'>
                <div class='toast-body fw-bold'>
                    <i class='fas fa-info-circle me-2'></i> $texto
                </div>
                <button type='button' class='btn-close btn-close-white me-2 m-auto' data-bs-dismiss='toast'></button>
            </div>
        </div>
    </div>";
    
    unset($_SESSION['toast_msg']);
}

// 3. LÓGICA TIMER GLOBAL (HEADER)
$uid_header = $_SESSION['usuario_id'] ?? 0;
$active_timer = null;
$h_clientes = []; 
$h_projetos = [];
$segundos_iniciais = 0;

if ($uid_header && isset($pdo)) {
    try {
        // Busca Timer Ativo
        $sqlH = "SELECT l.inicio, t.descricao, p.nome as projeto_nome, c.nome as cliente_nome
                 FROM tempo_logs l 
                 JOIN tarefas t ON l.tarefa_id = t.id 
                 LEFT JOIN projetos p ON t.projeto_id = p.id 
                 LEFT JOIN clientes c ON t.cliente_id = c.id
                 WHERE t.usuario_id = :uid AND l.fim IS NULL 
                 ORDER BY l.inicio DESC LIMIT 1";
        $stmtH = $pdo->prepare($sqlH);
        $stmtH->execute([':uid' => $uid_header]);
        $active_timer = $stmtH->fetch(PDO::FETCH_ASSOC);

        // Se tem timer rodando, calcula o offset
        if ($active_timer) {
            $segundos_iniciais = time() - strtotime($active_timer['inicio']);
        } else {
            // Se NÃO tem timer, carrega listas para o Modal de Quick Start
            $h_clientes = $pdo->query("SELECT id, nome FROM clientes WHERE status = 'ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
            $h_projetos = $pdo->query("SELECT id, nome, cliente_id FROM projetos WHERE status = 'ativo' ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (Exception $e) {
        // Silêncio é ouro no header, loga erro mas não quebra a página
        error_log("Erro Header: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RatControl | Gestão Inteligente</title>
    <link rel="icon" href="assets/favicon.ico" />
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <style>
        :root { 
            --font-main: 'Inter', sans-serif; 
            --primary-color: #0e2a47; /* Azul WeCare */
            --bg-body: #f4f6f8; 
        }
        body { font-family: var(--font-main); background-color: var(--bg-body); color: #344767; display: flex; flex-direction: column; min-height: 100vh; }
        
        /* Navbar */
        .navbar { background: rgba(255, 255, 255, 0.95) !important; backdrop-filter: blur(10px); border-bottom: 1px solid rgba(0,0,0,0.08) !important; padding: 0.8rem 0; }
        .nav-link { font-weight: 500; color: #67748e; transition: color 0.2s; }
        .nav-link:hover { color: var(--primary-color); }
        .nav-link.active { color: var(--primary-color) !important; font-weight: 600; }
        
        /* Dropdowns */
        .dropdown-item { font-size: 0.9rem; padding: 8px 16px; border-radius: 6px; margin: 2px 0; }
        .dropdown-item:hover { background-color: #f8f9fa; color: var(--primary-color); }
        .dropdown-menu { border-radius: 12px; padding: 10px; }

        /* Timer Widget (Topo) */
        .timer-widget { 
            display: flex; align-items: center; background: #fff; 
            border: 1px solid #e9ecef; border-radius: 50px; 
            padding: 4px 6px 4px 15px; height: 42px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }
        .timer-display { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--primary-color); font-size: 1.05rem; margin: 0 12px; min-width: 75px; text-align: center; }
        .pulse-dot { width: 10px; height: 10px; background: #dc3545; border-radius: 50%; display: inline-block; animation: pulse 1.5s infinite; box-shadow: 0 0 0 rgba(220, 53, 69, 0.4); }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); } 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); } }
        
        .btn-timer-stop { 
            border-radius: 50%; width: 32px; height: 32px; padding: 0; 
            display: flex; align-items: center; justify-content: center; 
            transition: transform 0.2s;
        }
        .btn-timer-stop:hover { transform: scale(1.1); }

        /* Dark Mode Override */
        [data-bs-theme="dark"] body { background-color: #121416; color: #e9ecef; }
        [data-bs-theme="dark"] .navbar { background: rgba(26, 29, 33, 0.95) !important; border-bottom-color: #343a40 !important; }
        [data-bs-theme="dark"] .dropdown-menu { background-color: #212529; border: 1px solid #343a40; }
        [data-bs-theme="dark"] .dropdown-item { color: #adb5bd; }
        [data-bs-theme="dark"] .dropdown-item:hover { background-color: #2c3035; color: #fff; }
        [data-bs-theme="dark"] .timer-widget { background: #212529; border-color: #343a40; }
        [data-bs-theme="dark"] .timer-display { color: #fff; }
    </style>
    
    <script>
        const storedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', storedTheme);
    </script>
</head>
<body>

<?php echo $toast_html; ?>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold text-primary" href="index.php">
        <i class="fas fa-mouse me-2"></i>RatControl
    </a>
    
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4 gap-lg-2">
        <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
        
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Trabalho</a>
          <ul class="dropdown-menu shadow">
            <li><a class="dropdown-item" href="timer.php"><i class="fas fa-stopwatch me-2 text-primary"></i> Timer</a></li>
            <li><a class="dropdown-item" href="kanban.php"><i class="fas fa-columns me-2 text-warning"></i> Kanban</a></li>
            <li><a class="dropdown-item" href="manual.php"><i class="fas fa-pen-square me-2 text-success"></i> Manual</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="calendario.php"><i class="fas fa-calendar-alt me-2 text-info"></i> Calendário</a></li>
          </ul>
        </li>
        
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Gestão</a>
          <ul class="dropdown-menu shadow">
            <li><a class="dropdown-item" href="orcamentos.php"><i class="fas fa-file-contract me-2"></i> Propostas</a></li>
            <li><a class="dropdown-item" href="clientes.php"><i class="fas fa-briefcase me-2"></i> Clientes</a></li>
            <li><a class="dropdown-item" href="projetos.php"><i class="fas fa-project-diagram me-2"></i> Projetos</a></li>
            <li><a class="dropdown-item" href="tags.php"><i class="fas fa-tags me-2"></i> Tags</a></li>
          </ul>
        </li>
        
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Financeiro</a>
          <ul class="dropdown-menu shadow">
            <li><a class="dropdown-item" href="relatorios.php"><i class="fas fa-chart-line me-2 text-success"></i> Relatórios</a></li>
            <li><a class="dropdown-item" href="despesas.php"><i class="fas fa-receipt me-2 text-danger"></i> Despesas</a></li>
          </ul>
        </li>
      </ul>
      
      <ul class="navbar-nav align-items-center gap-3">
        
        <li class="nav-item">
            <?php if ($active_timer): ?>
                <div class="timer-widget">
                    <div class="d-flex align-items-center">
                        <span class="pulse-dot" title="Gravando"></span>
                        <span id="gt-display" class="timer-display">00:00:00</span>
                    </div>
                    <div class="d-none d-xl-block border-start ps-2 ms-2 lh-1 text-truncate" style="max-width: 150px;">
                        <div class="small fw-bold text-dark text-truncate" style="font-size: 0.75rem;">
                            <?php echo htmlspecialchars($active_timer['cliente_nome']); ?>
                        </div>
                        <div class="text-muted text-truncate" style="font-size: 0.7rem;">
                            <?php echo htmlspecialchars($active_timer['projeto_nome'] ?? 'Geral'); ?>
                        </div>
                    </div>
                    <button class="btn btn-danger btn-timer-stop ms-2 shadow-sm" onclick="pararTimerHeader()" title="Parar e Salvar">
                        <i class="fas fa-stop fa-xs"></i>
                    </button>
                </div>
                
                <script>
                    const headerStart = <?php echo $segundos_iniciais; ?>;
                    const headerLocalTime = Date.now() - (headerStart * 1000);

                    function headerTimerLoop() {
                        const diff = Math.floor((Date.now() - headerLocalTime) / 1000);
                        const h = Math.floor(diff/3600);
                        const m = Math.floor((diff%3600)/60);
                        const s = Math.floor(diff%60);
                        
                        document.getElementById('gt-display').innerText = 
                            `${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
                    }
                    setInterval(headerTimerLoop, 1000);
                    headerTimerLoop();
                </script>
            <?php else: ?>
                <button class="btn btn-success btn-sm rounded-pill fw-bold px-3 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalHeaderTimer">
                    <i class="fas fa-play fa-xs"></i> <span>Timer</span>
                </button>
            <?php endif; ?>
        </li>

        <li class="nav-item border-start ps-3 d-none d-lg-block"></li>

        <li class="nav-item">
            <button class="btn btn-link nav-link p-0" id="btn-theme" title="Alternar Tema">
                <i class="fas fa-moon"></i>
            </button>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
            <?php 
                $avatarUser = $_SESSION['usuario_avatar'] ?? "https://ui-avatars.com/api/?name=".urlencode($_SESSION['usuario_nome'] ?? 'U')."&background=0e2a47&color=fff";
            ?>
            <img src="<?php echo $avatarUser; ?>" class="rounded-circle border" style="width: 34px; height: 34px; object-fit: cover;">
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
            <li><h6 class="dropdown-header">Olá, <?php echo htmlspecialchars(explode(' ', $_SESSION['usuario_nome'] ?? 'User')[0]); ?></h6></li>
            <li><a class="dropdown-item py-2" href="perfil.php"><i class="fas fa-user-circle me-2 text-secondary"></i> Meu Perfil</a></li>
            <li><a class="dropdown-item py-2" href="backup.php"><i class="fas fa-database me-2 text-secondary"></i> Backup</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Sair</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="modal fade" id="modalHeaderTimer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white border-0">
                <h6 class="modal-title fw-bold"><i class="fas fa-stopwatch me-2"></i>Início Rápido</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form onsubmit="iniciarTimerHeader(event)">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Cliente</label>
                        <select id="ht_cliente" class="form-select" required onchange="filtrarProjetosHeader()">
                            <option value="">Selecione...</option>
                            <?php foreach ($h_clientes as $hc): ?>
                                <option value="<?php echo $hc['id']; ?>"><?php echo htmlspecialchars($hc['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Projeto</label>
                        <select id="ht_projeto" class="form-select" disabled>
                            <option value="">Selecione um Cliente</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">O que você vai fazer?</label>
                        <input type="text" id="ht_desc" class="form-control" placeholder="Ex: Ajuste layout..." required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success fw-bold">
                            <i class="fas fa-play me-2"></i> Começar Agora
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="container flex-grow-1 py-4">

<script>
    // --- TEMA DARK/LIGHT ---
    const btnTheme = document.getElementById('btn-theme');
    const iconTheme = btnTheme.querySelector('i');
    
    // Ajusta ícone inicial
    if(document.documentElement.getAttribute('data-bs-theme') === 'dark') {
        iconTheme.classList.replace('fa-moon', 'fa-sun');
    }

    btnTheme.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-bs-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-bs-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        if(newTheme === 'dark') {
            iconTheme.classList.replace('fa-moon', 'fa-sun');
        } else {
            iconTheme.classList.replace('fa-sun', 'fa-moon');
        }
    });

    // --- API CALLS ---
    function iniciarTimerHeader(e) {
        e.preventDefault();
        const pid = document.getElementById('ht_projeto').value;
        const desc = document.getElementById('ht_desc').value;
        
        // Se projeto for vazio, mandamos null ou string vazia, a API trata
        fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ acao: 'quick_start', projeto_id: pid, descricao: desc })
        }).then(r => r.json()).then(d => {
            if(d.sucesso) location.reload();
            else alert(d.msg);
        });
    }

    function pararTimerHeader() {
        if(!confirm('Parar tarefa atual?')) return;
        fetch('api.php', { method: 'POST', body: JSON.stringify({ acao: 'parar_timer' }) })
        .then(() => location.reload());
    }

    // --- SELECT2 LOGIC (Espera jQuery carregar no footer) ---
    window.addEventListener('load', function() {
        if (typeof $ !== 'undefined' && $.fn.select2) {
            
            // Dados vindos do PHP
            const hProjetos = <?php echo json_encode($h_projetos); ?>;

            // Função global de filtro
            window.filtrarProjetosHeader = function() {
                const cliId = $('#ht_cliente').val();
                const $selProj = $('#ht_projeto');
                
                $selProj.empty().append('<option value="">Selecione o Projeto...</option>');
                
                if (!cliId) {
                    $selProj.prop('disabled', true);
                } else {
                    const filtrados = hProjetos.filter(p => p.cliente_id == cliId);
                    filtrados.forEach(p => {
                        $selProj.append(new Option(p.nome, p.id));
                    });
                    $selProj.prop('disabled', false);
                }
                $selProj.trigger('change'); // Avisa Select2
            };

            // Inicializa Select2 no Modal
            $('#ht_cliente, #ht_projeto').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#modalHeaderTimer'),
                width: '100%',
                placeholder: 'Selecione...'
            });
        }
    });
    
    // Auto-Hide Toast (Se o bootstrap js não fizer sozinho)
    const toastEl = document.getElementById('liveToast');
    if (toastEl) {
        setTimeout(() => {
            toastEl.classList.remove('show');
            setTimeout(() => toastEl.remove(), 500); // Remove do DOM
        }, 4000);
    }
</script>