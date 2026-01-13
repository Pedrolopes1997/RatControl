<?php
// config/db.php

// 1. DEFINIÇÃO DE FUSO HORÁRIO (Cuiabá/MT)
date_default_timezone_set('America/Cuiaba'); 

// Dados de Conexão
$host     = '127.0.0.1'; // IP direto é levemente mais rápido que 'localhost'
$dbname   = 'u672544197_ratcontrol'; 
$username = 'u672544197_ratcontrol';    
$password = 'Paola1106*'; // <--- Troque a senha no painel e coloque aqui

try {
    // Opções de conexão (Performance e Segurança)
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança erros para podermos tratar
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      // Traz dados como array associativo
        PDO::ATTR_EMULATE_PREPARES   => false,                 // Segurança: Usa prepared statements REAIS do MySQL
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"    // Garante caracteres especiais/emojis na conexão
    ];

    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $options);
    
    // --- SINCRONIA DE FUSO HORÁRIO (PHP <-> MySQL) ---
    // Garante que NOW() do banco seja igual ao date() do PHP
    $offset = date('P'); 
    $pdo->exec("SET time_zone = '$offset'");

} catch (PDOException $e) {
    // Em produção, nunca mostre $e->getMessage() para o usuário (vaza dados)
    // Apenas registre no log do servidor
    error_log($e->getMessage());
    die("Erro de conexão (500). Por favor, contate o administrador.");
}
?>