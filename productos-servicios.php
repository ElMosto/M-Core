<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_modulo('productos_servicios');

$pageTitle    = 'Productos y servicios';
$activeNav    = 'productos_servicios';
$headerTitle  = 'Productos y servicios';
$headerSub    = 'Catálogo que aparece disponible para vender en el módulo de Ventas';
$extraScripts = ['assets/js/productos-servicios.js'];
include __DIR__ . '/includes/layout-top.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="form-check">
    <input type="checkbox" class="form-check-input" id="chk-mostrar-baja">
    <label class="form-check-label hint mb-0" for="chk-mostrar-baja">Mostrar dados de baja</label>
  </div>
  <button class="btn btn-timbra btn-sm" id="btn-nuevo-ps" data-bs-toggle="modal" data-bs-target="#modalPS">
    <i class="bi bi-plus-lg me-1"></i> Nuevo producto/servicio
  </button>
</div>

<div class="card-timbra">
  <div class="card-body-timbra">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr><th>Nombre</th><th>Tipo</th><th>Precio</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody id="tabla-ps-body">
          <tr><td colspan="5" class="hint">Cargando catálogo…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: alta/edición -->
<div class="modal fade" id="modalPS" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display" id="modalPSTitulo">Nuevo producto/servicio</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-ps">
        <div class="modal-body">
          <input type="hidden" name="accion" id="ps_accion" value="crear">
          <input type="hidden" name="id" id="ps_id" value="">
          <div class="mb-3">
            <label class="form-label" for="ps_nombre">Nombre</label>
            <input type="text" class="form-control" id="ps_nombre" name="nombre" required placeholder="Ej. Diseño de landing page">
            <div class="field-error"></div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label d-block">Tipo</label>
              <div class="btn-group w-100" role="group">
                <input type="radio" class="btn-check" name="tipo" id="ps_tipo_producto" value="producto" checked>
                <label class="btn btn-outline-timbra" for="ps_tipo_producto">Producto</label>
                <input type="radio" class="btn-check" name="tipo" id="ps_tipo_servicio" value="servicio">
                <label class="btn btn-outline-timbra" for="ps_tipo_servicio">Servicio</label>
              </div>
              <div class="field-error"></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="ps_precio">Precio</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" min="0" class="form-control font-mono" id="ps_precio" name="precio" required placeholder="299.00">
              </div>
              <div class="field-error"></div>
            </div>
          </div>
          <div class="mb-1">
            <label class="form-label" for="ps_descripcion">Descripción (opcional)</label>
            <textarea class="form-control" id="ps_descripcion" name="descripcion" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-timbra" id="modalPSBoton">Registrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
