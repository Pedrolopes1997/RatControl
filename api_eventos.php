<?php
// api_eventos.php
require 'config/db.php';

// Garante que a sessão existe
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([]);
    exit;
}

$uid = $_SESSION['usuario_id'];

// 1. FILTRO DE DATA (CRUCIAL PARA PERFORMANCE)
// O FullCalendar envia ?start=2026-01-01&end=2026-02-01 automaticamente
$start = $_GET['start'] ?? date('Y-m-01'); // Padrão: dia 1 do mês atual
$end   = $_GET['end'] ?? date('Y-m-t');    // Padrão: último dia do mês

// 2. QUERY OTIMIZADA
// Buscamos apenas logs que começaram dentro da janela visível do calendário
$sql = "SELECT tl.id, tl.inicio, tl.fim, t.descricao, c.nome as nome_cliente, t.status, p.nome as nome_projeto
        FROM tempo_logs tl
        JOIN tarefas t ON tl.tarefa_id = t.id
        JOIN clientes c ON t.cliente_id = c.id
        LEFT JOIN projetos p ON t.projeto_id = p.id
        WHERE t.usuario_id = :uid 
        AND tl.inicio >= :start 
        AND tl.inicio <= :end";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':uid'   => $uid,
    ':start' => $start,
    ':end'   => $end
]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$eventos = [];

// 3. GERADOR DE CORES "SAFE" (Manteve a ideia, mas com hash MD5 que é mais seguro para Hex)
function gerarCorCliente($str) {
    // Cria um hash MD5 da string
    $hash = md5($str);
    
    // Pega os 6 primeiros caracteres para a cor
    // Dica Visual: Para evitar cores muito claras (que matam o texto branco),
    // podemos usar apenas canais de cor mais escuros, mas vamos no simples:
    return '#' . substr($hash, 0, 6);
}

foreach ($logs as $log) {
    // Formatação do Título
    $titulo = $log['nome_cliente'];
    if ($log['nome_projeto']) {
        $titulo .= ' • ' . $log['nome_projeto']; 
    }

    // Cor baseada no nome do cliente (Consistência visual)
    $cor = gerarCorCliente($log['nome_cliente']);
    
    // Lógica para Timer Rodando (Em Aberto)
    if ($log['fim'] === NULL) {
        $cor = '#dc3545'; // Vermelho (Bootstrap Danger)
        // Se está rodando, definimos o "fim" visual como AGORA para aparecer o tamanho real no calendário
        $fim = date('Y-m-d H:i:s'); 
        $titulo = "▶ RODANDO: " . $titulo;
        $classe = 'border-danger border-3'; // Borda grossa para destacar
    } else {
        $fim = $log['fim'];
        $classe = '';
    }

    $eventos[] = [
        'id'              => $log['id'],
        'title'           => $titulo,
        'start'           => $log['inicio'],
        'end'             => $fim,
        'backgroundColor' => $cor,
        'borderColor'     => $cor,
        'textColor'       => '#ffffff', // Texto branco forçado
        'classNames'      => $classe,   // Classes CSS extras
        'extendedProps'   => [
            'cliente'   => $log['nome_cliente'],
            'projeto'   => $log['nome_projeto'] ?? 'Avulso',
            'descricao' => $log['descricao'],
            'status'    => $log['status']
        ]
    ];
}

echo json_encode($eventos);
?>