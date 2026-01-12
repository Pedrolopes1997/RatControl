<?php
// exportar_ical.php
require 'config/db.php';

// Segurança: Token simples baseado no ID do usuário (em produção usaria um token aleatório no banco)
// Link esperado: exportar_ical.php?uid=1&key=HASH
$uid = $_GET['uid'] ?? 0;
$key = $_GET['key'] ?? '';
$secret = 'RatControlSecretKey'; // Em produção, isso estaria no .env

if (md5($uid . $secret) !== $key) {
    die("Acesso negado.");
}

// Cabeçalhos para o Google entender que é um calendário
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="ratcontrol_agenda.ics"');

// Busca logs
$sql = "SELECT tl.id, tl.inicio, tl.fim, t.descricao, c.nome as nome_cliente, p.nome as nome_projeto
        FROM tempo_logs tl
        JOIN tarefas t ON tl.tarefa_id = t.id
        JOIN clientes c ON t.cliente_id = c.id
        LEFT JOIN projetos p ON t.projeto_id = p.id
        WHERE t.usuario_id = :uid";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $uid]);
$eventos = $stmt->fetchAll();

// Formata data para iCal (Ymd\THis\Z)
function dateToCal($timestamp) {
    return date('Ymd\THis\Z', strtotime($timestamp) + (4*3600)); // Ajuste UTC se necessário
}

// Imprime estrutura do arquivo .ics
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//RatControl//Minha Agenda//PT-BR\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";

foreach ($eventos as $e) {
    $inicio = dateToCal($e['inicio']);
    $fim = $e['fim'] ? dateToCal($e['fim']) : dateToCal($e['inicio']); // Se rodando, põe inicio=fim
    
    $titulo = $e['nome_cliente'];
    if($e['nome_projeto']) $titulo .= " - " . $e['nome_projeto'];
    
    echo "BEGIN:VEVENT\r\n";
    echo "UID:ratcontrol-" . $e['id'] . "\r\n";
    echo "DTSTART:{$inicio}\r\n";
    echo "DTEND:{$fim}\r\n";
    echo "SUMMARY:" . $titulo . "\r\n";
    echo "DESCRIPTION:" . $e['descricao'] . "\r\n";
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR";
?>