<?php
require 'config/db.php';

echo "<h3>Diagnóstico e Reparação do Banco de Dados</h3>";

try {
    // 1. Tenta criar a tabela se ela sumiu
    $sql = "CREATE TABLE IF NOT EXISTS checklist_projetos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        projeto_id INT NOT NULL,
        descricao VARCHAR(255) NOT NULL,
        status VARCHAR(20) DEFAULT 'todo',
        concluido TINYINT(1) DEFAULT 0,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (projeto_id) REFERENCES projetos(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "✅ Tabela 'checklist_projetos': Verificada.<br>";

    // 2. Tenta adicionar a coluna 'status' se faltar
    try {
        $pdo->exec("ALTER TABLE checklist_projetos ADD COLUMN status VARCHAR(20) DEFAULT 'todo'");
        echo "✅ Coluna 'status': Adicionada com sucesso.<br>";
    } catch (Exception $e) {
        echo "ℹ️ Coluna 'status': Já existia (OK).<br>";
    }

    // 3. Tenta adicionar a coluna 'concluido' se faltar
    try {
        $pdo->exec("ALTER TABLE checklist_projetos ADD COLUMN concluido TINYINT(1) DEFAULT 0");
        echo "✅ Coluna 'concluido': Adicionada com sucesso.<br>";
    } catch (Exception $e) {
        echo "ℹ️ Coluna 'concluido': Já existia (OK).<br>";
    }

    echo "<hr><h2 style='color:green'>Sucesso! Tudo reparado.</h2>";
    echo "<a href='projetos.php'>Voltar para Projetos</a>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>Erro Fatal:</h2>";
    echo $e->getMessage();
    echo "<br><br>Verifique se o usuário do banco tem permissão ALTER/CREATE.";
}
?>