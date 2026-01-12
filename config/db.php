<?php
// config/db.php

// 1. DEFINA SEU FUSO HORÁRIO AQUI
// Use 'America/Cuiaba' para Mato Grosso (-04:00)
// Use 'America/Sao_Paulo' para Brasília (-03:00)
date_default_timezone_set('America/Cuiaba'); 

$host = 'localhost';
// --- PREENCHA COM SEUS DADOS DA HOSTINGER ---
$dbname = 'u672544197_ratcontrol'; 
$username = 'u672544197_ratcontrol';   
$password = 'Paola1106*'; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Configurações de erro e retorno
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- CORREÇÃO CRÍTICA DE FUSO HORÁRIO ---
    // Pega o deslocamento atual do PHP (ex: "-04:00") e aplica no MySQL
    // Assim, o NOW() do banco será igual ao date() do PHP
    $offset = date('P'); 
    $pdo->exec("SET time_zone = '$offset'");

} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados. Verifique as configurações.");
}
?>