<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_admin();

$pageTitle    = 'Usuarios';
$activeNav    = 'usuarios';
$headerTitle  = 'Usuarios y roles';
$headerSub    = 'Crea cuentas y define qué módulos puede ver cada quién';
$extraScripts = ['assets/js/usuarios.js'];
include __DIR__ . '/includes/layout-top.php';
?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card-timbra">
      <div class="card-header-timbra">
        <h2><i class="bi bi-person-plus me-1"></i> Nuevo usuario</h2>
      </div>
      <div class="card-body-timbra">
        <form id="form-usuario" novalidate>
          <div class="mb-3">
            <label for="u_nombre" class="form-label">Nombre</label>
            <input type="text" class="form-control" id="u_nombre" name="nombre" required>
            <div class="field-error"></div>
          </div>
          <div class="mb-3">
            <label for="u_email" class="form-label">Correo</label>
            <input type="email" class="form-control" id="u_email" name="email" required>
            <div class="field-error"></div>
          </div>
          <div class="mb-3">
            <label for="u_password" class="form-label">Contraseña temporal</label>
            <input type="password" class="form-control" id="u_password" name="password" required minlength="8">
            <div class="hint">Mínimo 8 caracteres. Pide que la cambien al primer ingreso.</div>
            <div class="field-error"></div>
          </div>
          <div class="mb-3">
            <label for="u_rol" class="form-label">Rol</label>
            <select class="form-select" id="u_rol" name="rol" required>
              <option value="" disabled selected>Cargando roles…</option>
            </select>
            <div class="hint">¿Necesitas otro rol o quitarle/agregarle módulos a uno? Ve a <a href="roles.php">Roles</a>.</div>
            <div class="field-error"></div>
          </div>
          <button type="submit" class="btn btn-timbra px-4" id="btn-usuario-submit">
            <span class="btn-text"><i class="bi bi-person-check me-1"></i> Crear usuario</span>
            <span class="btn-spinner spinner-border spinner-border-sm ms-1 d-none"></span>
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card-timbra">
      <div class="card-header-timbra">
        <h2><i class="bi bi-people me-1"></i> Cuentas existentes</h2>
      </div>
      <div class="card-body-timbra">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody id="tabla-usuarios-body">
              <tr><td colspan="5" class="hint">Cargando…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: editar usuario -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display">Editar usuario</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-editar-usuario">
        <div class="modal-body">
          <input type="hidden" id="eu_id">
          <div class="mb-3">
            <label for="eu_nombre" class="form-label">Nombre</label>
            <input type="text" class="form-control" id="eu_nombre" name="nombre" required>
            <div class="field-error"></div>
          </div>
          <div class="mb-3">
            <label for="eu_email" class="form-label">Correo</label>
            <input type="email" class="form-control" id="eu_email" name="email" required>
            <div class="field-error"></div>
          </div>
          <div class="mb-1">
            <label for="eu_password" class="form-label">Nueva contraseña (opcional)</label>
            <input type="password" class="form-control" id="eu_password" name="password" minlength="8">
            <div class="hint">Déjalo en blanco para no cambiarla. Si la llenas, mínimo 8 caracteres.</div>
            <div class="field-error"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-timbra">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
