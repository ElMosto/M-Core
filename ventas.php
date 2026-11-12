<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_modulo('ventas');

$pageTitle    = 'Ventas';
$activeNav    = 'ventas';
$headerTitle  = 'Ventas';
$headerSub    = 'Registra ventas de productos o servicios, aparte de los proyectos';
$extraScripts = ['assets/js/ventas.js?v=2'];
include __DIR__ . '/includes/layout-top.php';
?>

<script>
  const TIMBRA_ES_ADMIN_VENTAS = <?= $usuarioActual['rol'] === 'admin' ? 'true' : 'false' ?>;
</script>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-timbra btn-sm" data-bs-toggle="modal" data-bs-target="#modalVenta" id="btn-nueva-venta">
    <i class="bi bi-cart-plus me-1"></i> Nueva venta
  </button>
</div>

<div class="card-timbra">
  <div class="card-body-timbra">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr><th>Fecha</th><th>Cliente</th><th>Vendió</th><th>Líneas</th><th>Total</th><th></th></tr>
        </thead>
        <tbody id="tabla-ventas-body">
          <tr><td colspan="6" class="hint">Cargando ventas…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: nueva venta -->
<div class="modal fade" id="modalVenta" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display">Nueva venta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-venta">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="v_negocio">Negocio</label>
            <select class="form-select" id="v_negocio" name="negocio_id"></select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="v_cliente">Cliente (opcional)</label>
            <select class="form-select" id="v_cliente" name="cliente_id">
              <option value="">Sin cliente registrado</option>
            </select>
          </div>

          <label class="form-label">Productos / servicios</label>
          <div id="lineas-venta"></div>
          <button type="button" class="btn btn-sm btn-outline-timbra mb-3" id="btn-agregar-linea">
            <i class="bi bi-plus-lg me-1"></i> Agregar línea
          </button>
          <div class="field-error mb-3" id="err-items"></div>

          <div class="mb-3">
            <label class="form-label" for="v_notas">Notas (opcional)</label>
            <textarea class="form-control" id="v_notas" name="notas" rows="2"></textarea>
          </div>

          <div class="d-flex justify-content-end">
            <div class="text-end">
              <div class="hint mb-0">Total</div>
              <div class="font-display" id="v_total_display" style="font-size:1.4rem">$0.00</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-timbra">Registrar venta</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: detalle de venta -->
<div class="modal fade" id="modalDetalleVenta" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display">Detalle de venta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detalle-venta-body">
        <div class="hint">Cargando…</div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: editar venta (solo admin) -->
<div class="modal fade" id="modalEditarVenta" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display">Editar venta</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-editar-venta">
        <div class="modal-body">
          <input type="hidden" id="ev_id">
          <div class="mb-3">
            <label class="form-label" for="ev_negocio">Negocio</label>
            <select class="form-select" id="ev_negocio"></select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="ev_cliente">Cliente</label>
            <select class="form-select" id="ev_cliente">
              <option value="">Sin cliente registrado</option>
            </select>
          </div>
          <div class="mb-1">
            <label class="form-label" for="ev_notas">Notas</label>
            <textarea class="form-control" id="ev_notas" rows="2"></textarea>
          </div>
          <div class="hint mt-2">Solo se corrigen cliente y notas — para cambiar los artículos, elimina esta venta y regístrala de nuevo.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-timbra">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Plantilla de una línea de venta -->
<template id="tpl-linea-venta">
  <div class="row g-2 align-items-start mb-2 linea-venta">
    <div class="col-6">
      <select class="form-select form-select-sm select-producto" required>
        <option value="" disabled selected>Selecciona…</option>
      </select>
    </div>
    <div class="col-3">
      <input type="number" min="1" step="1" value="1" class="form-control form-control-sm input-cantidad">
    </div>
    <div class="col-2">
      <input type="text" class="form-control form-control-sm subtotal-linea" disabled value="$0.00">
    </div>
    <div class="col-1">
      <button type="button" class="btn btn-sm btn-outline-secondary btn-quitar-linea"><i class="bi bi-x"></i></button>
    </div>
  </div>
</template>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
