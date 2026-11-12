<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_admin();

$pageTitle    = 'Roles';
$activeNav    = 'roles';
$headerTitle  = 'Roles';
$headerSub    = 'Crea roles y decide qué módulos puede ver cada uno';
$extraScripts = ['assets/js/roles.js'];
include __DIR__ . '/includes/layout-top.php';
?>

<script>
  const TIMBRA_MODULOS_ASIGNABLES = <?= json_encode(TIMBRA_MODULOS_ASIGNABLES, JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="d-flex justify-content-end mb-3">
  <button class="btn btn-timbra btn-sm" data-bs-toggle="modal" data-bs-target="#modalRol">
    <i class="bi bi-plus-lg me-1"></i> Nuevo rol
  </button>
</div>

<div class="card-timbra mb-4">
  <div class="card-body-timbra">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr><th>Rol</th><th>Clave</th><th>Módulos</th><th>Usuarios</th><th></th></tr>
        </thead>
        <tbody id="tabla-roles-body">
          <tr><td colspan="5" class="hint">Cargando roles…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card-timbra">
  <div class="card-header-timbra">
    <h2><i class="bi bi-info-circle me-1"></i> Cómo funciona</h2>
  </div>
  <div class="card-body-timbra">
    <ul class="hint mb-0 ps-3">
      <li class="mb-1"><strong>Admin</strong> siempre tiene acceso a todo — es fijo, no depende de esta pantalla.</li>
      <li class="mb-1"><strong>Usuarios</strong> y <strong>Roles</strong> (esta misma pantalla) son exclusivos de admin siempre — ningún rol se los puede conceder a sí mismo.</li>
      <li class="mb-1"><strong>Mi usuario</strong> (perfil propio) lo tiene cualquier sesión, sin importar el rol.</li>
      <li>Los 3 roles originales (Contador, Líder de proyecto, Colaborador) están marcados como "Sistema": no se pueden eliminar ni cambiarles la clave, pero sí puedes editar libremente a qué módulos tienen acceso.</li>
    </ul>
  </div>
</div>

<!-- Modal: nuevo rol -->
<div class="modal fade" id="modalRol" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display">Nuevo rol</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-rol">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="r_nombre">Nombre</label>
            <input type="text" class="form-control" id="r_nombre" name="nombre" required placeholder="Ej. Supervisor de ventas">
            <div class="field-error"></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="r_clave">Clave interna</label>
            <input type="text" class="form-control font-mono" id="r_clave" name="clave" required placeholder="supervisor_ventas" pattern="[a-z][a-z0-9_]{2,30}">
            <div class="hint">Minúsculas, números y guion bajo. No se puede cambiar después de crear el rol.</div>
            <div class="field-error"></div>
          </div>
          <div class="mb-1">
            <label class="form-label d-block">Módulos con acceso</label>
            <div id="r_modulos_checklist" class="row row-cols-2 g-2"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-timbra">Crear rol</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: editar permisos -->
<div class="modal fade" id="modalPermisos" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display" id="modalPermisosTitulo">Permisos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-permisos">
        <div class="modal-body">
          <input type="hidden" name="id" id="p_id">
          <div id="p_modulos_checklist" class="row row-cols-2 g-2"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-timbra">Guardar permisos</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: renombrar -->
<div class="modal fade" id="modalRenombrar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-display">Renombrar rol</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="form-renombrar">
        <div class="modal-body">
          <input type="hidden" name="id" id="rn_id">
          <div class="mb-1">
            <label class="form-label" for="rn_nombre">Nombre</label>
            <input type="text" class="form-control" id="rn_nombre" name="nombre" required>
            <div class="field-error"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-timbra">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
