<?php
require 'config/db.php';
require 'includes/header.php';

// Gera a URL de sincronização para o usuário atual
$uid = $_SESSION['usuario_id'];
$secret = 'RatControlSecretKey'; // Tem que ser igual ao do exportar_ical.php
$hash = md5($uid . $secret);

// Monta URL completa
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
$ical_link = $base_url . "/exportar_ical.php?uid=$uid&key=$hash";
?>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/locales/pt-br.global.min.js"></script>

<style>
    /* Customização Visual do Calendário */
    .fc-theme-standard .fc-scrollgrid { border: none; }
    .fc-theme-standard td, .fc-theme-standard th { border-color: #f0f2f5; }
    
    .fc-col-header-cell-cushion { 
        padding: 10px; color: #6c757d; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; 
    }
    
    .fc-event { 
        border: none; border-radius: 6px; padding: 2px 4px; font-size: 0.85rem; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .fc-day-today { background-color: rgba(13, 110, 253, 0.03) !important; }
    .fc-button-primary { background-color: var(--primary-color) !important; border: none; text-transform: capitalize; }
    .fc-button-active { background-color: #0a58ca !important; }
    .fc-toolbar-title { font-size: 1.5rem !important; font-weight: 700; color: #344767; }
</style>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="fw-bold text-dark mb-0">Minha Agenda</h2>
        <p class="text-muted mb-0">Visualize sua produtividade no tempo.</p>
    </div>
    <button class="btn btn-outline-dark shadow-sm" data-bs-toggle="modal" data-bs-target="#modalGoogle">
        <i class="fab fa-google me-2"></i> Sincronizar Google Agenda
    </button>
</div>

<div class="card shadow border-0">
    <div class="card-body p-4">
        <div id='calendar' style="min-height: 750px;"></div>
    </div>
</div>

<div class="modal fade" id="modalGoogle" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="fab fa-google text-danger me-2"></i> Sincronizar Agenda</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Para ver suas tarefas do RatControl no seu celular ou Google Agenda:</p>
        <ol class="small text-muted">
            <li>Copie o link abaixo.</li>
            <li>Abra o <strong>Google Calendar</strong> no PC.</li>
            <li>No menu esquerdo, clique no <strong>"+"</strong> ao lado de "Outras agendas".</li>
            <li>Escolha <strong>"Do URL"</strong>.</li>
            <li>Cole o link e confirme.</li>
        </ol>
        
        <div class="input-group mb-3">
            <input type="text" class="form-control" value="<?php echo $ical_link; ?>" id="linkIcal" readonly>
            <button class="btn btn-primary" onclick="copiarLink()">Copiar</button>
        </div>
        
        <div class="alert alert-info py-2 small">
            <i class="fas fa-info-circle"></i> O Google pode levar até 12h para atualizar novos eventos.
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
        events: 'api_eventos.php',
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
        
        eventDidMount: function(info) {
            // Adiciona Tooltip com a descrição completa
            var tooltip = new bootstrap.Tooltip(info.el, {
                title: info.event.extendedProps.descricao,
                placement: 'top',
                trigger: 'hover',
                container: 'body'
            });
        },
        
        height: 'auto',
        dayMaxEvents: true // Mostra "+2 mais" se tiver muitos eventos no dia
    });
    
    calendar.render();
});

function copiarLink() {
    var copyText = document.getElementById("linkIcal");
    copyText.select();
    navigator.clipboard.writeText(copyText.value);
    alert("Link copiado! Agora adicione no seu Google Calendar.");
}
</script>

<?php require 'includes/footer.php'; ?>