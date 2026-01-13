<?php
require 'config/db.php';
require 'includes/header.php';

// GARANTIA DE SESSÃO
if (session_status() === PHP_SESSION_NONE) session_start();
$uid = $_SESSION['usuario_id'] ?? 0;

// CONFIGURAÇÃO DE SINCRONIZAÇÃO (iCal)
// Importante: Este segredo deve ser O MESMO no arquivo exportar_ical.php
$secret = 'RatControlSecretKey_2026'; 
$hash = md5($uid . $secret);

// Gera URL absoluta para o Google Calendar
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
// Remove barras extras se houver
$base_url = rtrim($base_url, '/');
$ical_link = $base_url . "/exportar_ical.php?uid=$uid&key=$hash";
?>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/locales/pt-br.global.min.js"></script>

<style>
    /* Estilização Premium do Calendário */
    .fc { font-family: 'Inter', sans-serif; }
    .fc-theme-standard .fc-scrollgrid { border: 1px solid #e9ecef; border-radius: 12px; overflow: hidden; }
    .fc-theme-standard td, .fc-theme-standard th { border-color: #f0f2f5; }
    
    .fc-col-header-cell-cushion { 
        padding: 15px 0; color: #6c757d; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; font-weight: 700;
    }
    
    .fc-event { 
        border: none; border-radius: 6px; padding: 3px 6px; font-size: 0.8rem; cursor: pointer; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.1s;
    }
    .fc-event:hover { transform: scale(1.02); z-index: 5; }
    
    .fc-day-today { background-color: rgba(13, 110, 253, 0.02) !important; }
    
    /* Botões do FullCalendar com estilo Bootstrap */
    .fc-button-primary { background-color: var(--primary-color) !important; border: none; text-transform: capitalize; padding: 0.4rem 1rem; border-radius: 6px !important; }
    .fc-button-active { background-color: #0a58ca !important; box-shadow: inset 0 3px 5px rgba(0,0,0,0.125); }
    .fc-toolbar-title { font-size: 1.5rem !important; font-weight: 700; color: #344767; }
    
    /* Tooltip Customizado */
    .tooltip-inner { max-width: 300px; text-align: left; padding: 10px; }
</style>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0">Minha Agenda</h2>
        <p class="text-muted mb-0">Visão geral de tarefas e alocações de tempo.</p>
    </div>
    <button class="btn btn-white border shadow-sm text-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalGoogle">
        <i class="fab fa-google me-2"></i> Sincronizar Google Agenda
    </button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <div id='calendar' style="min-height: 800px;"></div>
    </div>
</div>

<div class="modal fade" id="modalGoogle" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="fab fa-google text-danger me-2"></i> Sincronizar com Google</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted mb-3">Siga os passos para ver suas tarefas do RatControl direto no celular:</p>
        <ol class="small text-muted list-group list-group-numbered mb-3">
            <li class="list-group-item border-0 py-1">Copie o <strong>Link Seguro (iCal)</strong> abaixo.</li>
            <li class="list-group-item border-0 py-1">Abra o <a href="https://calendar.google.com" target="_blank">Google Calendar</a> no PC.</li>
            <li class="list-group-item border-0 py-1">No menu esquerdo, clique no <strong>"+"</strong> ao lado de "Outras agendas".</li>
            <li class="list-group-item border-0 py-1">Escolha <strong>"Do URL"</strong>.</li>
            <li class="list-group-item border-0 py-1">Cole o link e confirme.</li>
        </ol>
        
        <label class="form-label small fw-bold text-muted">Seu Link iCal</label>
        <div class="input-group mb-3">
            <input type="text" class="form-control font-monospace text-muted" value="<?php echo $ical_link; ?>" id="linkIcal" readonly style="font-size: 0.85rem;">
            <button class="btn btn-primary" onclick="copiarLink()"><i class="fas fa-copy"></i> Copiar</button>
        </div>
        
        <div class="alert alert-warning py-2 small d-flex align-items-center">
            <i class="fas fa-exclamation-triangle me-2"></i> 
            <div>O Google Agenda pode levar de <strong>12 a 24 horas</strong> para atualizar novos eventos automaticamente.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
        timeZone: 'local', // Usa o fuso do navegador do usuário
        themeSystem: 'bootstrap5',
        
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        
        buttonText: {
            today:    'Hoje',
            month:    'Mês',
            week:     'Semana',
            list:     'Lista'
        },
        
        // Puxa eventos da nossa API otimizada
        events: 'api_eventos.php',
        
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
        dayMaxEvents: true, // Mostra "+2 mais"
        
        // --- TOOLTIP RICO (Com Cliente e Projeto) ---
        eventDidMount: function(info) {
            var props = info.event.extendedProps;
            
            // Monta o HTML do tooltip
            var conteudo = `
                <div class="text-start">
                    <strong class="text-warning">${props.cliente}</strong><br>
                    <small class="text-white-50">${props.projeto || ''}</small>
                    <hr class="my-1 border-white opacity-25">
                    ${props.descricao}
                </div>
            `;

            var tooltip = new bootstrap.Tooltip(info.el, {
                title: conteudo,
                html: true, // Permite HTML dentro do tooltip
                placement: 'top',
                trigger: 'hover',
                container: 'body'
            });
        }
    });
    
    calendar.render();
});

function copiarLink() {
    var copyText = document.getElementById("linkIcal");
    copyText.select();
    copyText.setSelectionRange(0, 99999); // Mobile
    
    // Tenta API moderna, fallback para antiga
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(copyText.value).then(() => alert("Link copiado com sucesso!"));
    } else {
        document.execCommand('copy');
        alert("Link copiado!");
    }
}
</script>

<?php require 'includes/footer.php'; ?>