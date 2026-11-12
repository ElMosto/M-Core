<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_modulo('negocios');

$pageTitle    = 'Negocios';
$activeNav    = 'negocios';
$headerTitle  = 'Negocios';
$headerSub    = 'Administra los negocios de tu empresa y sus logos (usados en tickets y finanzas)';
$extraScripts = ['assets/js/negocios.js'];
include __DIR__ . '/includes/layout-top.php';
?>

<div class="card-timbra mb-4">
  <div class="card-header-timbra d-flex justify-content-between align-items-center">
    <h2><i class="bi bi-shop me-1"></i> Negocios registrados</h2>
    <button type="button" class="btn btn-timbra btn-sm" data-bs-toggle="modal" data-bs-target="#modal-nuevo-negocio">
      <i class="bi bi-plus-lg me-1"></i> Nuevo negocio
    </button>
  </div>
  <div class="card-body-timbra p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Logo</th><th>Nombre</th><th>Estado</th><th>Acciones</th></tr></thead>
        <tbody id="tbody-negocios"><tr><td colspan="4" class="hint text-center py-4">Cargando…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal nuevo/editar -->
<div class="modal fade" id="modal-nuevo-negocio" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-negocio-titulo">Nuevo negocio</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="negocio-id">
        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input type="text" class="form-control" id="negocio-nombre" maxlength="80">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-timbra" id="btn-guardar-negocio">Guardar</button>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>