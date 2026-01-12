let timerInterval;
let segundosTotais = 0;

function iniciarTimerVisual(segundosIniciais) {
    segundosTotais = segundosIniciais;
    clearInterval(timerInterval);
    
    timerInterval = setInterval(() => {
        segundosTotais++;
        document.getElementById('display-timer').innerText = formatarTempo(segundosTotais);
    }, 1000);
}

function acaoTimer(acao, tarefaId) {
    // acao pode ser 'iniciar', 'pausar', 'finalizar'
    fetch('api.php', {
        method: 'POST',
        body: JSON.stringify({ acao: acao, tarefa_id: tarefaId }),
        headers: { 'Content-Type': 'application/json' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            if (acao === 'iniciar' || acao === 'retomar') {
                iniciarTimerVisual(data.tempo_atual);
            } else {
                clearInterval(timerInterval);
            }
            atualizarStatusVisual(acao);
        }
    });
}