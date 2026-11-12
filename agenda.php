<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_modulo('agenda');

$pageTitle    = 'Agenda';
$activeNav    = 'agenda';
$headerTitle  = 'Agenda';
$headerSub    = 'Tus citas y tus tareas pendientes en un calendario';
$extraScripts = ['assets/js/agenda.js'];
include __DIR__ . '/includes/layout-top.php';
?>

<script>
  const TIMBRA_ROL_ACTUAL_AGENDA = <?= json_encode($usuarioActual['rol']) ?>;
  const TIMBRA_USUARIO_ID_AGENDA = <?= (int) $usuarioActual['id'] ?>;
</script>

<div id="invitaciones-pendientes-wrap" class="mb-4" style="display:none">
  <div class="card-timbra">
    <div class="card-header-timbra">
      <h2><i class="bi bi-envelope-paper me-1"></i> Invitaciones pendientes</h2>
    </div>
    <div class="card-body-timbra" id="lista-invitaciones"></div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-2">
    <button class="btn btn-outline-secondary btn-sm" id="btn-mes-prev"><i class="bi bi-chevron-left"></i></button>
    <h2 class="font-display mb-0" id="calendario-titulo" style="font-size:1.15rem; min-width:180px; text-align:center"></h2>
    <button class="btn btn-outline-secondary btn-sm" id="btn-mes-next"><i class="bi bi-chevron-right"></i></button>
    <button class="btn btn-outline-timbra btn-sm" id="btn-mes-hoy">Hoy</button>
  </div>
  <button class="btn btn-timbra btn-sm" id="btn-nueva-cita" data-bs-toggle="modal" data-bs-target="#modalCita">
    <i class="bi bi-calendar-plus me-1"></i> Nueva cita
  </button>
</div>

<div class="card-timbra mb-4">
  <div class="card-body-timbra">
    <div class="calendario-semana-head">
      <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span>
    </div>
    <div class="calendario-grid" id="calendario-grid">
      <div class="hint">Cargando calendario…</div>
    </div>
  </div>
</div>

<div class="card-timbra" id="panel-dia" style="display:none">
  <div class="card-header-timbra">
    <h2><i class="bi bi-calendar-event me-1"></i> <span id="panel-dia-titulo"></span></h2>
  </div>
  <div class="card-body-timbra" id="panel-dia-body"></div>
</div>

<!-- Modal: nueva cita -->
<div class="modal fade" id="modalCita" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display">Nueva cita</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-cita">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="c_titulo">Título</label>
            <input type="text" class="form-control" id="c_titulo" name="titulo" required placeholder="Ej. Presentación de propuesta">
            <div class="field-error"></div>
          </div>
          <div class="row">
            <div class="col-md-5 mb-3">
              <label class="form-label" for="c_fecha">Fecha</label>
              <input type="date" class="form-control" id="c_fecha" name="fecha" required>
              <div class="field-error"></div>
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label" for="c_hora_inicio">Hora inicio</label>
              <input type="time" class="form-control" id="c_hora_inicio" name="hora_inicio" required>
              <div class="field-error"></div>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label" for="c_hora_fin">Hora fin (opcional)</label>
              <input type="time" class="form-control" id="c_hora_fin" name="hora_fin">
              <div class="field-error"></div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="c_cliente">Cliente (opcional)</label>
            <select class="form-select" id="c_cliente" name="cliente_id">
              <option value="">Sin cliente</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="c_descripcion">Descripción (opcional)</label>
            <textarea class="form-control" id="c_descripcion" name="descripcion" rows="2"></textarea>
          </div>
          <div class="mb-1">
            <label class="form-label d-block">Invitar a (opcional)</label>
            <div id="c_invitados_checklist" class="invitados-checklist">
              <div class="hint">Cargando personas…</div>
            </div>
            <div class="hint">Cada invitado debe aceptar antes de que la cita se confirme para él.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-timbra">Registrar cita</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: detalle de cita -->
<div class="modal fade" id="modalDetalleCita" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display">Detalle de la cita</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detalle-cita-body">
        <div class="hint">Cargando…</div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
