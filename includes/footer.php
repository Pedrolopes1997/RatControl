</div> 

<footer class="footer mt-auto py-3 bg-white border-top mt-5">
    <div class="container text-center">
        <span class="text-muted small">
            RatControl &copy; <?php echo date('Y'); ?> 
            &bull; WeCare Consultoria 
            &bull; v1.0
        </span>
    </div>
</footer>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
  <?php if(isset($msg_toast)): ?>
  <div id="liveToast" class="toast align-items-center text-bg-<?php echo $msg_toast['tipo']; ?> border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body fw-bold">
        <?php echo $msg_toast['texto']; ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
  <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script src="assets/js/timer.js"></script> 

<script>
    $(document).ready(function() {
        // Inicializa Select2 em todos os <select> automaticamente
        // (Exceto nos que tiverem classe 'no-select2' ou id específicos do header que já tratamos lá)
        $('select:not(.no-select2, #ht_cliente, #ht_projeto)').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Selecione uma opção...'
        });

        // Inicializa Toast se houver mensagem PHP
        const toastEl = document.getElementById('liveToast');
        if (toastEl) {
            const toast = new bootstrap.Toast(toastEl, { delay: 4000 }); // Fecha em 4s
            toast.show();
        }
    });
</script>

</body>
</html>