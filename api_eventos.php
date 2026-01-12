<?php
// api_eventos.php
require 'config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([]);
    exit;
}

// Busca logs de tempo unidos com tarefas e clientes
$sql = "SELECT tl.id, tl.inicio, tl.fim, t.descricao, c.nome as nome_cliente, t.status, p.nome as nome_projeto
        FROM tempo_logs tl
        JOIN tarefas t ON tl.tarefa_id = t.id
        JOIN clientes c ON t.cliente_id = c.id
        LEFT JOIN projetos p ON t.projeto_id = p.id
        WHERE t.usuario_id = :uid";

$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $_SESSION['usuario_id']]);
$logs = $stmt->fetchAll();

$eventos = [];

// Função para gerar cor consistente baseada numa string (Nome do Cliente)
function stringToColor($str) {
    $code = dechex(crc32($str));
    $code = substr($code, 0, 6);
    return '#' . $code;
}

foreach ($logs as $log) {
    $titulo = $log['nome_cliente'];
    if ($log['nome_projeto']) {
        $titulo .= ' • ' . $log['nome_projeto']; // Usando • para ficar bonito
    }

    // Cor baseada no cliente para agrupar visualmente
    $cor = stringToColor($log['nome_cliente']);

    // Se estiver rodando (sem data fim), destaca em Vermelho
    if ($log['fim'] === NULL) {
        $cor = '#dc3545'; // Danger Bootstrap
        $fim = date('Y-m-d H:i:s');
        $titulo = "⏳ RODANDO: " . $titulo;
    } else {
        $fim = $log['fim'];
    }

    $eventos[] = [
        'id' => $log['id'],
        'title' => $titulo,
        'start' => $log['inicio'],
        'end' => $fim,
        'backgroundColor' => $cor,
        'borderColor' => $cor,
        'textColor' => '#fff', // Texto sempre branco para contraste
        'extendedProps' => [
            'descricao' => $log['descricao'] // Para o Tooltip
        ]
    ];
}

echo json_encode($eventos);