    </main>
  </div>
</div>

<!-- Contenedor global de toasts (retroalimentación inmediata) -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container" style="z-index:1080"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<?php if (!empty($extraScripts)): foreach ($extraScripts as $src): ?>
<script src="<?= htmlspecialchars($src) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
