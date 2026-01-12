<?php
// includes/auth.php
session_start();

// Verifica se a variável de sessão existe
if (!isset($_SESSION['usuario_id'])) {
    // Se não estiver logado, redireciona para o login
    header("Location: login.php");
    exit;
}
?>