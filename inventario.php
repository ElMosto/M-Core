<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_modulo('inventario');
$esAdmin = $usuarioActual['rol'] === 'admin';

$pageTitle    = 'Inventario';
$activeNav    = 'inventario';
$headerTitle  = 'Inventario';
$headerSub    = $esAdmin
    ? 'Catálogo interno, solicitudes y aprobaciones de préstamo'
    : 'Solicita equipo del inventario interno y da seguimiento a tus préstamos';
$extraScripts = ['assets/js/inventario.js'];
include __DIR__ . '/includes/layout-top.php';
?>

<script>
  const TIMBRA_ES_ADMIN = <?= $esAdmin ? 'true' : 'false' ?>;
</script>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h2 class="font-display mb-0" style="font-size:1.1rem"><i class="bi bi-box-seam me-1"></i> Catálogo</h2>
  <?php if ($esAdmin): ?>
    <div class="d-flex align-items-center gap-2">
      <div class="form-check">
        <input type="checkbox" class="form-check-input" id="chk-mostrar-baja">
        <label class="form-check-label hint mb-0" for="chk-mostrar-baja">Mostrar dados de baja</label>
      </div>
      <button class="btn btn-timbra btn-sm" id="btn-nuevo-item" data-bs-toggle="modal" data-bs-target="#modalItem">
        <i class="bi bi-plus-lg me-1"></i> Nuevo artículo
      </button>
    </div>
  <?php endif; ?>
</div>

<div class="card-timbra mb-4">
  <div class="card-body-timbra">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Artículo</th>
            <th>Disponible</th>
            <th>Total</th>
            <?php if ($esAdmin): ?><th>Estado</th><?php endif; ?>
            <th></th>
          </tr>
        </thead>
        <tbody id="tabla-items-body">
          <tr><td colspan="5" class="hint">Cargando catálogo…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card-timbra mb-4">
  <div class="card-header-timbra">
    <h2><i class="bi bi-clock-history me-1"></i> Mis solicitudes</h2>
  </div>
  <div class="card-body-timbra">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr><th>Artículo</th><th>Cant.</th><th>Solicitado</th><th>Estado</th><th>Aprobó</th><th>Devuelto</th></tr>
        </thead>
        <tbody id="tabla-mis-solicitudes-body">
          <tr><td colspan="6" class="hint">Cargando…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($esAdmin): ?>
<div class="card-timbra mb-4">
  <div class="card-header-timbra">
    <h2><i class="bi bi-hourglass-split me-1"></i> Solicitudes pendientes de aprobación</h2>
    <span class="hint mb-0" id="pendientes-count-label"></span>
  </div>
  <div class="card-body-timbra">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr><th>Colaborador</th><th>Artículo</th><th>Cant.</th><th>Fecha y hora</th><th>Notas</th><th></th></tr>
        </thead>
        <tbody id="tabla-pendientes-body">
          <tr><td colspan="6" class="hint">Cargando…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card-timbra mb-4">
  <div class="card-header-timbra">
    <h2><i class="bi bi-list-check me-1"></i> Historial completo de préstamos</h2>
  </div>
  <div class="card-body-timbra">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr><th>Colaborador</th><th>Artículo</th><th>Cant.</th><th>Solicitado</th><th>Estado</th><th>Aprobó</th><th>Devuelto</th><th></th></tr>
        </thead>
        <tbody id="tabla-historial-body">
          <tr><td colspan="8" class="hint">Cargando…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal: alta/edición de artículo -->
<div class="modal fade" id="modalItem" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display" id="modalItemTitulo">Nuevo artículo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-item">
        <div class="modal-body">
          <input type="hidden" name="accion" id="i_accion" value="crear">
          <input type="hidden" name="id" id="i_id" value="">
          <div class="mb-3">
            <label class="form-label" for="i_nombre">Nombre</label>
            <input type="text" class="form-control" id="i_nombre" name="nombre" required placeholder="Ej. Diadema USB">
            <div class="field-error"></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="i_descripcion">Descripción (opcional)</label>
            <textarea class="form-control" id="i_descripcion" name="descripcion" rows="2"></textarea>
          </div>
          <div class="mb-1">
            <label class="form-label" for="i_cantidad">Cantidad total en existencia</label>
            <input type="number" min="0" step="1" class="form-control font-mono" id="i_cantidad" name="cantidad_total" required>
            <div class="hint" id="i_cantidad_hint"></div>
            <div class="field-error"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-timbra" id="modalItemBoton">Dar de alta</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: solicitar artículo -->
<div class="modal fade" id="modalSolicitar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display" id="modalSolicitarTitulo">Solicitar artículo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-solicitar">
        <div class="modal-body">
          <input type="hidden" name="item_id" id="s_item_id">
          <div class="mb-3">
            <label class="form-label" for="s_cantidad">Cantidad</label>
            <input type="number" min="1" step="1" class="form-control font-mono" id="s_cantidad" name="cantidad" value="1" required>
            <div class="field-error"></div>
          </div>
          <div class="mb-1">
            <label class="form-label" for="s_notas">Notas (opcional)</label>
            <textarea class="form-control" id="s_notas" name="notas" rows="2" placeholder="Ej. Para el equipo nuevo que entra el lunes"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-timbra">Enviar solicitud</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
