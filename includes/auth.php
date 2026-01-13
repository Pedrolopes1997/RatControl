<?php
// includes/auth.php

// 1. INÍCIO SEGURO DA SESSÃO
// Verifica se a sessão já não foi iniciada antes para evitar erros de PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. VERIFICAÇÃO DE LOGIN BÁSICA
if (!isset($_SESSION['usuario_id'])) {
    // Redireciona imediatamente se não houver ID na sessão
    header("Location: login.php");
    exit;
}

// 3. SEGURANÇA: TIMEOUT POR INATIVIDADE (Auto-Logout)
// Define o tempo limite em segundos (ex: 1800s = 30 minutos)
$timeout_duration = 1800; 

if (isset($_SESSION['last_activity'])) {
    // Calcula quanto tempo passou desde a última ação
    $elapsed_time = time() - $_SESSION['last_activity'];
    
    if ($elapsed_time > $timeout_duration) {
        // Se passou do tempo, destrói a sessão e chuta para o login
        session_unset();
        session_destroy();
        
        // Redireciona com aviso (opcional, se seu login.php tratar GET)
        header("Location: login.php?msg=timeout"); 
        exit;
    }
}

// Atualiza o tempo da última atividade para "AGORA"
$_SESSION['last_activity'] = time();
?>