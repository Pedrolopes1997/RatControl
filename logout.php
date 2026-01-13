<?php
// logout.php - Encerramento Seguro
require 'config/db.php'; // Carrega configurações (caso defina parâmetros de sessão lá)

// Inicia a sessão para ter acesso a ela
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Limpa todas as variáveis de sessão da memória agora
$_SESSION = array();

// 2. Apaga o cookie da sessão do navegador (Crucial para segurança)
// Isso invalida o ID da sessão no browser do usuário
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destroi a sessão no armazenamento do servidor
session_destroy();

// 4. Redireciona para o login
header("Location: login.php");
exit;
?>