<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_modulo('clientes');

$pageTitle    = 'Clientes';
$activeNav    = 'clientes';
$headerTitle  = 'Clientes';
$headerSub    = 'Empresas y personas para las que se hacen proyectos externos';
$extraScripts = ['assets/js/clientes.js'];
include __DIR__ . '/includes/layout-top.php';
?>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-timbra btn-sm" data-bs-toggle="modal" data-bs-target="#modalCliente">
    <i class="bi bi-person-plus me-1"></i> Nuevo cliente
  </button>
</div>

<div class="card-timbra">
  <div class="card-body-timbra">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Contacto</th>
            <th>Correo</th>
            <th>Teléfono</th>
            <th>Proyectos</th>
          </tr>
        </thead>
        <tbody id="tabla-clientes-body">
          <tr><td colspan="5" class="hint">Cargando clientes…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: nuevo cliente -->
<div class="modal fade" id="modalCliente" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display">Nuevo cliente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-cliente">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="c_nombre">Nombre o razón social</label>
            <input type="text" class="form-control" id="c_nombre" name="nombre" required>
            <div class="field-error"></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="c_contacto">Persona de contacto (opcional)</label>
            <input type="text" class="form-control" id="c_contacto" name="contacto_nombre">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="c_email">Correo (opcional)</label>
              <input type="email" class="form-control" id="c_email" name="email">
              <div class="field-error"></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="c_telefono">Teléfono (opcional)</label>
              <input type="text" class="form-control" id="c_telefono" name="telefono">
            </div>
          </div>
          <div class="mb-1">
            <label class="form-label" for="c_notas">Notas (opcional)</label>
            <textarea class="form-control" id="c_notas" name="notas" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-timbra">Registrar cliente</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
