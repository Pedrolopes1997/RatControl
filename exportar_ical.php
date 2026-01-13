<?php
// exportar_ical.php
require 'config/db.php';

// 1. SEGURANÇA
// A chave DEVE ser igual a do calendario.php
$secret = 'RatControlSecretKey_2026'; 

$uid = $_GET['uid'] ?? 0;
$key = $_GET['key'] ?? '';

// Verifica se o hash bate
if (md5($uid . $secret) !== $key) {
    http_response_code(403);
    die("Acesso negado: Chave de sincronização inválida.");
}

// 2. CONFIGURAÇÃO DE CABEÇALHO
// Força o navegador/Google a entender que é um arquivo de calendário
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="ratcontrol_agenda.ics"');

// 3. BUSCA OS EVENTOS
// Trazemos tudo (você pode limitar data se ficar pesado no futuro)
$sql = "SELECT tl.id, tl.inicio, tl.fim, t.descricao, c.nome as nome_cliente, p.nome as nome_projeto, t.status
        FROM tempo_logs tl
        JOIN tarefas t ON tl.tarefa_id = t.id
        JOIN clientes c ON t.cliente_id = c.id
        LEFT JOIN projetos p ON t.projeto_id = p.id
        WHERE t.usuario_id = :uid";
        
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $uid]);
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- FUNÇÕES AUXILIARES ---

// Converte Data do Banco (Cuiabá) para UTC (Padrão iCal - Zulu Time)
function dateToCal($timestamp) {
    // Cria data considerando o fuso do banco/PHP (America/Cuiaba definido no db.php)
    $dt = new DateTime($timestamp); 
    // Converte para UTC (Greenwich) pois o Google Calendar prefere assim
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Ymd\THis\Z');
}

// Limpa textos para não quebrar o formato .ics
function escapeString($str) {
    $str = preg_replace('/([\,;])/','\\\$1', $str); // Escapa , e ;
    $str = str_replace("\n", "\\n", $str); // Troca quebra de linha real por \n textual
    $str = str_replace("\r", "", $str);
    return $str;
}

// --- GERAÇÃO DO ARQUIVO ---

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//RatControl//Minha Agenda v2//PT-BR\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "X-WR-CALNAME:RatControl Tarefas\r\n"; // Nome que aparece no Google
echo "X-WR-TIMEZONE:America/Cuiaba\r\n";

foreach ($eventos as $e) {
    // Define Inicio
    $dtStart = dateToCal($e['inicio']);
    
    // Define Fim
    if ($e['fim']) {
        $dtEnd = dateToCal($e['fim']);
        $status = "CONFIRMED";
    } else {
        // Se a tarefa está rodando (sem fim), definimos o fim como "Agora"
        // para ela aparecer no calendário com o tamanho atual
        $dtEnd = dateToCal(date('Y-m-d H:i:s'));
        $status = "TENTATIVE"; // Status visual diferente
    }

    // Título
    $resumo = $e['nome_cliente'];
    if($e['nome_projeto']) $resumo .= " • " . $e['nome_projeto'];
    if(!$e['fim']) $resumo = "▶ RODANDO: " . $resumo;

    // Descrição detalhada
    $desc = "Cliente: " . $e['nome_cliente'] . "\n";
    if($e['nome_projeto']) $desc .= "Projeto: " . $e['nome_projeto'] . "\n";
    $desc .= "Tarefa: " . $e['descricao'];

    echo "BEGIN:VEVENT\r\n";
    echo "UID:ratcontrol-log-" . $e['id'] . "\r\n"; // ID Único universal
    echo "DTSTAMP:" . dateToCal(date('Y-m-d H:i:s')) . "\r\n"; // Data de geração
    echo "DTSTART:{$dtStart}\r\n";
    echo "DTEND:{$dtEnd}\r\n";
    echo "SUMMARY:" . escapeString($resumo) . "\r\n";
    echo "DESCRIPTION:" . escapeString($desc) . "\r\n";
    echo "STATUS:{$status}\r\n";
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR";
?>