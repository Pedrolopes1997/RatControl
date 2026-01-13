<?php
require 'config/db.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Reparação do Sistema</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f8; padding: 40px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .log-item { padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .log-item:last-child { border-bottom: none; }
        .success { color: #198754; font-weight: bold; }
        .info { color: #0d6efd; }
        .error { color: #dc3545; font-weight: bold; }
        h2 { border-bottom: 2px solid #eee; padding-bottom: 15px; margin-top: 0; color: #333; }
        .btn { display: inline-block; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; font-weight: bold; }
        .btn:hover { background: #0b5ed7; }
    </style>
</head>
<body>

<div class="container">
    <h2>🛠️ Diagnóstico e Reparação de Banco de Dados</h2>

<?php
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- 1. DEFINIÇÃO DAS TABELAS (Schema Completo) ---
    $tabelas = [
        'usuarios' => "CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            senha VARCHAR(255) NOT NULL,
            permissao VARCHAR(20) DEFAULT 'user',
            avatar VARCHAR(255) NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        'clientes' => "CREATE TABLE IF NOT EXISTS clientes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            telefone VARCHAR(20),
            valor_hora DECIMAL(10,2) DEFAULT 0.00,
            moeda VARCHAR(10) DEFAULT 'R$',
            status VARCHAR(20) DEFAULT 'ativo',
            token_acesso VARCHAR(100),
            logo VARCHAR(255) NULL,
            data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        'projetos' => "CREATE TABLE IF NOT EXISTS projetos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cliente_id INT NOT NULL,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT,
            status VARCHAR(20) DEFAULT 'ativo',
            horas_estimadas INT DEFAULT 0,
            data_inicio DATE,
            data_fim DATE,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
        )",
        'tarefas' => "CREATE TABLE IF NOT EXISTS tarefas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT,
            cliente_id INT,
            projeto_id INT NULL,
            descricao TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'pendente',
            status_pagamento VARCHAR(20) DEFAULT 'pendente',
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            data_finalizacao DATETIME NULL,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
        )",
        'tempo_logs' => "CREATE TABLE IF NOT EXISTS tempo_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tarefa_id INT NOT NULL,
            inicio DATETIME NOT NULL,
            fim DATETIME NULL,
            FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE
        )",
        'checklist_projetos' => "CREATE TABLE IF NOT EXISTS checklist_projetos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            projeto_id INT NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            status VARCHAR(20) DEFAULT 'todo',
            concluido TINYINT(1) DEFAULT 0,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE
        )",
        'orcamentos' => "CREATE TABLE IF NOT EXISTS orcamentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT,
            cliente_id INT,
            titulo VARCHAR(150),
            descricao TEXT,
            prazo_entrega VARCHAR(100),
            tipo_cobranca VARCHAR(20),
            valor_total DECIMAL(10,2),
            valor_hora DECIMAL(10,2),
            horas_estimadas INT,
            status VARCHAR(20) DEFAULT 'pendente',
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        'despesas' => "CREATE TABLE IF NOT EXISTS despesas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT,
            cliente_id INT,
            projeto_id INT NULL,
            descricao VARCHAR(255),
            valor DECIMAL(10,2),
            data_despesa DATE,
            comprovante VARCHAR(255) NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        'projeto_comentarios' => "CREATE TABLE IF NOT EXISTS projeto_comentarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            projeto_id INT NOT NULL,
            autor_tipo VARCHAR(20) NOT NULL, 
            mensagem TEXT NOT NULL,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE
        )",
        'projeto_arquivos' => "CREATE TABLE IF NOT EXISTS projeto_arquivos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            projeto_id INT NOT NULL,
            nome_original VARCHAR(255),
            caminho VARCHAR(255),
            tamanho INT,
            visivel_cliente TINYINT(1) DEFAULT 1,
            data_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE
        )"
    ];

    // --- 2. EXECUÇÃO DA CRIAÇÃO DE TABELAS ---
    foreach ($tabelas as $nome => $sql) {
        $pdo->exec($sql);
        echo "<div class='log-item'><span>Tabela <strong>$nome</strong></span> <span class='success'>Verificada ✓</span></div>";
    }

    // --- 3. ATUALIZAÇÕES DE COLUNAS (UPDATES DO SISTEMA) ---
    // Aqui adicionamos colunas que podem ter sido criadas em versões posteriores
    $updates = [
        "ALTER TABLE usuarios ADD COLUMN avatar VARCHAR(255) NULL",
        "ALTER TABLE despesas ADD COLUMN comprovante VARCHAR(255) NULL",
        "ALTER TABLE tarefas ADD COLUMN projeto_id INT NULL",
        "ALTER TABLE tarefas ADD COLUMN status_pagamento VARCHAR(20) DEFAULT 'pendente'",
        "ALTER TABLE checklist_projetos ADD COLUMN status VARCHAR(20) DEFAULT 'todo'",
        "ALTER TABLE clientes ADD COLUMN logo VARCHAR(255) NULL",
        "ALTER TABLE clientes ADD COLUMN token_acesso VARCHAR(100) NULL"
    ];

    echo "<br><strong>Verificando colunas novas...</strong><br>";
    foreach ($updates as $up) {
        try {
            $pdo->exec($up);
            echo "<div class='log-item'><span>Atualização de Estrutura</span> <span class='success'>Aplicada +</span></div>";
        } catch (Exception $e) {
            // Erro esperado se a coluna já existe
            // echo "<div class='log-item'><span>Estrutura</span> <span class='info'>Já atualizada .</span></div>";
        }
    }

    // --- 4. USUÁRIO ADMIN PADRÃO (SE NÃO EXISTIR NINGUÉM) ---
    $qtdUsers = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ($qtdUsers == 0) {
        $senhaHash = password_hash('123456', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO usuarios (nome, email, senha, permissao) VALUES (?, ?, ?, ?)")
            ->execute(['Administrador', 'admin@admin.com', $senhaHash, 'admin']);
        
        echo "<div class='log-item' style='background:#e8f5e9; border:1px solid #4caf50;'>
                <span><strong>Primeiro Acesso:</strong> Usuário criado!</span> 
                <span>Login: <b>admin@admin.com</b> / Senha: <b>123456</b></span>
              </div>";
    }

    echo "<div style='text-align:center; margin-top:30px;'>
            <h3 class='success'>✅ Sistema Pronto para Uso!</h3>
            <a href='index.php' class='btn'>Acessar Dashboard</a>
          </div>";

} catch (PDOException $e) {
    echo "<div class='log-item'><span class='error'>Erro Fatal:</span> <span>" . $e->getMessage() . "</span></div>";
    echo "<p>Verifique as credenciais no arquivo <code>config/db.php</code>.</p>";
}
?>
</div>
</body>
</html>