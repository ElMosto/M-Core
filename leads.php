<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_modulo('leads');
$puedeGestionar = in_array($usuarioActual['rol'], ['admin', 'lider_proyecto'], true);

$pageTitle    = 'Leads';
$activeNav    = 'leads';
$headerTitle  = 'Leads';
$headerSub    = $puedeGestionar
    ? 'Todos los leads generados, listos para asignar'
    : 'Los leads que tienes asignados';
$extraScripts = ['assets/js/leads.js'];
include __DIR__ . '/includes/layout-top.php';
?>

<script>
  const TIMBRA_PUEDE_GESTIONAR_LEADS = <?= $puedeGestionar ? 'true' : 'false' ?>;
  const TIMBRA_ES_ADMIN_LEADS = <?= $usuarioActual['rol'] === 'admin' ? 'true' : 'false' ?>;
</script>

<?php if ($usuarioActual['rol'] === 'admin'): ?>
<div class="card-timbra mb-4">
  <div class="card-header-timbra">
    <h2><i class="bi bi-plug me-1"></i> Fuentes de leads (APIs)</h2>
  </div>
  <div class="card-body-timbra">
    <form id="form-fuentes" class="mb-3">
      <div class="d-flex flex-wrap gap-4 mb-3">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="cfg_meta" name="meta_activo">
          <label class="form-check-label" for="cfg_meta"><i class="bi bi-facebook me-1"></i>Meta (Facebook/Instagram Ads)</label>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="cfg_linkedin" name="linkedin_activo">
          <label class="form-check-label" for="cfg_linkedin"><i class="bi bi-linkedin me-1"></i>LinkedIn Ads</label>
        </div>
      </div>
      <button type="submit" class="btn btn-outline-timbra btn-sm">Guardar fuentes activas</button>
    </form>

    <div class="demo-banner">
      <i class="bi bi-tools"></i>
      <div>
        Todavía no hay integración real con Meta/LinkedIn — este botón simula la llegada de leads
        mientras se conecta la API de verdad. <strong>Es temporal, se quitará más adelante.</strong>
        <div class="mt-2">
          <button type="button" class="btn btn-timbra btn-sm" id="btn-simular-leads">
            <i class="bi bi-magic me-1"></i> Simular generación de leads (100 por fuente activa)
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card-timbra">
  <div class="card-header-timbra">
    <h2><i class="bi bi-magnet me-1"></i> Leads</h2>
    <span class="hint mb-0" id="leads-count-label"></span>
  </div>
  <div class="card-body-timbra">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Empresa</th>
            <th>Contacto</th>
            <th>Fuente</th>
            <?php if ($puedeGestionar): ?><th>Asignado a</th><?php endif; ?>
            <th>Estado</th>
            <th>Fecha</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="tabla-leads-body">
          <tr><td colspan="8" class="hint">Cargando leads…</td></tr>
        </tbody>
      </table>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-2" id="paginacion-leads" style="display:none">
      <span class="hint mb-0" id="paginacion-leads-info"></span>
      <div class="btn-group">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-leads-pagina-anterior">
          <i class="bi bi-chevron-left"></i> Anterior
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-leads-pagina-siguiente">
          Siguiente <i class="bi bi-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: asignar lead -->
<div class="modal fade" id="modalAsignarLead" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display">Asignar lead</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-asignar-lead">
        <div class="modal-body">
          <input type="hidden" id="al_lead_id">
          <p class="hint" id="al_lead_nombre"></p>
          <label class="form-label" for="al_colaborador">Asignar a</label>
          <select class="form-select" id="al_colaborador" required>
            <option value="" disabled selected>Selecciona a alguien…</option>
          </select>
          <div class="field-error"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-timbra">Asignar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
