</div> <div class="toast-container position-fixed bottom-0 end-0 p-3">
  <?php if(isset($msg_toast)): ?>
  <div id="liveToast" class="toast align-items-center text-bg-<?php echo $msg_toast['tipo']; ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body">
        <?php echo $msg_toast['texto']; ?>
      </div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const toastEl = document.getElementById('liveToast')
  if (toastEl) {
    const toast = new bootstrap.Toast(toastEl)
    toast.show()
  }
</script>

</body>
</html>