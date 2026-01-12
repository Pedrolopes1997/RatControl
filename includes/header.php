<?php
// includes/header.php
require_once __DIR__ . '/auth.php';

// Lógica de Toast
$msg_toast = null;
if (isset($_SESSION['toast_msg'])) {
    $msg_toast = $_SESSION['toast_msg'];
    unset($_SESSION['toast_msg']);
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RatControl</title>
    
    <link rel="icon" href="https://fav.farm/🐭" />
    
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
            --primary-color: #0d6efd;
            --bg-body: #f4f6f8; /* Fundo levemente cinza */
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-body);
            color: #344767;
            -webkit-font-smoothing: antialiased;
        }

        /* Nav Bar Glass effect */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.05) !important;
        }
        .navbar-brand { font-weight: 700; letter-spacing: -0.5px; font-size: 1.3rem; }
        .nav-link { font-weight: 500; font-size: 0.95rem; color: #67748e; }
        .nav-link.active { color: var(--primary-color) !important; font-weight: 600; }

        /* Cards modernos */
        .card {
            border: none;
            border-radius: 16px; /* Mais arredondado */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            background: #fff;
            margin-bottom: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            color: #344767;
        }
        
        .card-body { padding: 1.5rem; }

        /* Inputs mais amigáveis */
        .form-control, .form-select {
            padding: 0.65rem 1rem;
            border-radius: 8px;
            border: 1px solid #d2d6da;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
        }

        /* Botões */
        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.3px;
            transition: all 0.2s;
        }
        .btn-primary { box-shadow: 0 3px 5px rgba(13, 110, 253, 0.2); }
        
        /* Tabelas */
        .table thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #8392ab;
            font-weight: 700;
            border-bottom-width: 1px;
            padding-bottom: 1rem;
        }
        .table td {
            vertical-align: middle;
            padding: 1rem 0.5rem;
            font-size: 0.95rem;
            border-bottom-color: #f0f2f5;
        }

        /* SOLUÇÃO PARA DESCRIÇÕES LONGAS (Use a classe .texto-limitado na TD) */
        .texto-limitado {
            max-width: 250px; /* Largura máxima da coluna */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: help; /* Mostra ? no mouse */
        }

        /* Dark Mode Fixes */
        [data-bs-theme="dark"] body { background-color: #1a1d20; color: #e9ecef; }
        [data-bs-theme="dark"] .navbar { background: rgba(33, 37, 41, 0.95) !important; border-bottom: 1px solid #373b3e !important; }
        [data-bs-theme="dark"] .card { background-color: #212529; box-shadow: none; border: 1px solid #373b3e; }
        [data-bs-theme="dark"] .table thead th { color: #adb5bd; }
        [data-bs-theme="dark"] .form-control { background-color: #212529; border-color: #495057; color: #fff; }
    </style>

    <script>
        const storedTheme = localStorage.getItem('theme');
        if (storedTheme) { document.documentElement.setAttribute('data-bs-theme', storedTheme); }
    </script>
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    
    <a class="navbar-brand text-primary" href="index.php">
        <i class="fas fa-mouse-pointer"></i> RatControl
    </a>
    
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4 gap-lg-3">
        <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Trabalho</a>
          <ul class="dropdown-menu shadow border-0 mt-2">
            <li><a class="dropdown-item py-2" href="timer.php"><i class="fas fa-stopwatch me-2 text-primary"></i> Timer</a></li>
            <li><a class="dropdown-item py-2" href="kanban.php"><i class="fas fa-columns me-2 text-warning"></i> Quadro Kanban</a></li> <li><a class="dropdown-item py-2" href="manual.php"><i class="fas fa-pen-square me-2 text-success"></i> Manual</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2" href="calendario.php"><i class="fas fa-calendar-alt me-2 text-info"></i> Calendário</a></li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Gestão</a>
          <ul class="dropdown-menu shadow border-0 mt-2">
            <li><a class="dropdown-item py-2" href="orcamentos.php"><i class="fas fa-file-contract me-2 text-dark"></i> Propostas / Orçamentos</a></li> <li><a class="dropdown-item py-2" href="clientes.php"><i class="fas fa-briefcase me-2 text-secondary"></i> Clientes</a></li>
            <li><a class="dropdown-item py-2" href="projetos.php"><i class="fas fa-project-diagram me-2 text-secondary"></i> Projetos</a></li>
            <li><a class="dropdown-item py-2" href="tags.php"><i class="fas fa-tags me-2 text-warning"></i> Tags</a></li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">Financeiro</a>
          <ul class="dropdown-menu shadow border-0 mt-2">
            <li><a class="dropdown-item py-2" href="relatorios.php"><i class="fas fa-chart-line me-2 text-success"></i> Relatórios</a></li>
            <li><a class="dropdown-item py-2" href="despesas.php"><i class="fas fa-receipt me-2 text-danger"></i> Despesas</a></li>
          </ul>
        </li>
      </ul>
      
      <ul class="navbar-nav align-items-center">
        <li class="nav-item me-2">
            <button class="btn btn-link nav-link" id="btn-theme" title="Alternar Tema"><i class="fas fa-moon"></i></button>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
            <div class="bg-gradient-primary bg-primary text-white rounded-circle d-flex justify-content-center align-items-center shadow-sm" style="width: 35px; height: 35px; font-size: 0.9rem;">
                <?php echo strtoupper(substr($_SESSION['usuario_nome'], 0, 1)); ?>
            </div>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
            <li><span class="dropdown-header">Olá, <?php echo htmlspecialchars(explode(' ', $_SESSION['usuario_nome'])[0]); ?></span></li>
            <li><a class="dropdown-item py-2" href="perfil.php">Perfil</a></li>
            <li><a class="dropdown-item py-2" href="backup.php">Backup</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2 text-danger" href="logout.php">Sair</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container flex-grow-1 py-4">

<script>
    const btnTheme = document.getElementById('btn-theme');
    const iconTheme = btnTheme.querySelector('i');
    if(document.documentElement.getAttribute('data-bs-theme') === 'dark') iconTheme.classList.replace('fa-moon', 'fa-sun');

    btnTheme.addEventListener('click', () => {
        const newTheme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        iconTheme.classList.toggle('fa-moon'); iconTheme.classList.toggle('fa-sun');
    });
    
    // ATIVAR TOOLTIPS (Para as descrições)
    document.addEventListener("DOMContentLoaded", function(){
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"], .texto-limitado'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            // Se for nossa classe customizada, define o title como o próprio texto se não tiver
            if (tooltipTriggerEl.classList.contains('texto-limitado') && !tooltipTriggerEl.getAttribute('title')) {
                 tooltipTriggerEl.setAttribute('title', tooltipTriggerEl.innerText);
                 // Adiciona atributos do Bootstrap
                 new bootstrap.Tooltip(tooltipTriggerEl);
            } else {
                 new bootstrap.Tooltip(tooltipTriggerEl);
            }
        });
    });
</script>