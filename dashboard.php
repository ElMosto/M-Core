<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_modulo('dashboard');

$pageTitle   = 'Panel de control';
$activeNav   = 'dashboard';
$headerTitle = 'Panel de control';
$headerSub   = 'Resumen general del sistema';
$extraScripts = ['assets/js/dashboard.js?v=6'];
include __DIR__ . '/includes/layout-top.php';
?>

<script>
  const TIMBRA_USER_ID = <?= (int) $usuarioActual['id'] ?>;
  const TIMBRA_USER_ROL = <?= json_encode($usuarioActual['rol']) ?>;
</script>

<div class="d-flex justify-content-end mb-3">
  <button type="button" class="btn btn-timbra btn-sm" data-bs-toggle="modal" data-bs-target="#modalPersonalizar">
    <i class="bi bi-sliders me-1"></i> Personalizar dashboard
  </button>
</div>

<div id="dashboard-widgets" class="dashboard-grid"></div>

<!-- Modal personalizar -->
<div class="modal fade" id="modalPersonalizar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display">Personalizar dashboard</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="lista-widgets-config"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-timbra" id="btn-guardar-widgets">Guardar</button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>