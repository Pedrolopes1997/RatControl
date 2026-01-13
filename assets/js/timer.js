/**
 * timer.js - Controle de Cronômetro Preciso (Anti-Drift)
 */

let timerInterval;
let horaInicioLocal = 0; // Armazena o timestamp de quando começou/retomou

// Formata segundos em HH:MM:SS
function formatarTempo(segundos) {
    const h = Math.floor(segundos / 3600);
    const m = Math.floor((segundos % 3600) / 60);
    const s = seconds = segundos % 60;
    
    // Adiciona o zero à esquerda se for menor que 10
    const hh = h < 10 ? '0' + h : h;
    const mm = m < 10 ? '0' + m : m;
    const ss = s < 10 ? '0' + s : s;
    
    return `${hh}:${mm}:${ss}`;
}

function iniciarTimerVisual(segundosJaDecorridos) {
    // Para qualquer intervalo anterior para não encavalar
    clearInterval(timerInterval);

    // LÓGICA DE PRECISÃO:
    // Em vez de somar +1, definimos qual foi o "Timestamp" de início baseando-se no que veio do banco.
    // Ex: Agora é 10:00. O banco diz que já rodou 5 min (300s).
    // Então fingimos que o start foi às 09:55.
    const agora = Date.now(); 
    horaInicioLocal = agora - (segundosJaDecorridos * 1000);

    // Atualiza a tela imediatamente
    document.getElementById('display-timer').innerText = formatarTempo(segundosJaDecorridos);
    
    timerInterval = setInterval(() => {
        const timestampAtual = Date.now();
        // A mágica: (Agora - InicioFalso) / 1000
        const diferenca = Math.floor((timestampAtual - horaInicioLocal) / 1000);
        
        document.getElementById('display-timer').innerText = formatarTempo(diferenca);
        
        // (Opcional) Atualiza o título da aba para ver o tempo sem entrar no site
        document.title = formatarTempo(diferenca) + " - RatControl";
    }, 1000);
}

function pararTimerVisual() {
    clearInterval(timerInterval);
    document.title = "RatControl"; // Reseta título da aba
}

function acaoTimer(acao, tarefaId) {
    // Feedback visual imediato (Opcional: desabilitar botão para evitar duplo clique)
    const btn = document.activeElement; // Pega o botão clicado
    if(btn) btn.disabled = true;

    fetch('api.php', {
        method: 'POST',
        body: JSON.stringify({ acao: acao, tarefa_id: tarefaId }),
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if(btn) btn.disabled = false;

        if (data.sucesso) {
            if (acao === 'iniciar' || acao === 'retomar') {
                // O backend DEVE retornar 'tempo_atual' em segundos (calculado via PHP)
                iniciarTimerVisual(data.tempo_atual);
                
                // Se tiver função para trocar ícone de play/pause, chama aqui
                if(typeof atualizarBotoes === 'function') atualizarBotoes('rodando');
                
            } else if (acao === 'pausar' || acao === 'parar_timer') {
                pararTimerVisual();
                if(typeof atualizarBotoes === 'function') atualizarBotoes('parado');
                
                // Se for finalizar, talvez recarregar a página ou limpar o timer
                if(acao === 'parar_timer') {
                    document.getElementById('display-timer').innerText = "00:00:00";
                    // location.reload(); // Opcional, se quiser atualizar a lista de tarefas
                }
            }
        } else {
            alert('Erro: ' + data.msg);
        }
    })
    .catch(error => {
        if(btn) btn.disabled = false;
        console.error('Erro na requisição:', error);
        alert('Erro de conexão com o servidor.');
    });
}