<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_modulo('perfil');

$pageTitle    = 'Mi usuario';
$activeNav    = 'perfil';
$headerTitle  = 'Mi usuario';
$headerSub    = 'Tu información de cuenta — visible solo para ti';
$extraScripts = ['assets/js/perfil.js'];
include __DIR__ . '/includes/layout-top.php';
?>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card-timbra">
      <div class="card-header-timbra">
        <h2><i class="bi bi-person-circle me-1"></i> Perfil</h2>
      </div>
      <div class="card-body-timbra">
        <form id="form-perfil">
          <div class="d-flex align-items-center gap-3 mb-4">
            <div id="avatar-preview-wrap">
              <?php if (!empty($usuarioActual['foto_path'])): ?>
                <img src="<?= htmlspecialchars($usuarioActual['foto_path']) ?>" alt="" class="avatar-perfil" id="avatar-preview">
              <?php else: ?>
                <i class="bi bi-person-circle avatar-perfil-fallback" id="avatar-preview"></i>
              <?php endif; ?>
            </div>
            <div>
              <label class="avatar-upload-label" for="p_foto">
                <i class="bi bi-camera"></i> Cambiar foto
              </label>
              <input type="file" id="p_foto" name="foto" accept="image/jpeg,image/png,image/webp" class="d-none">
              <div class="hint mb-0">JPG, PNG o WEBP · máx. 2 MB</div>
              <div class="field-error" id="err-foto"></div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="p_nombre">Nombre</label>
            <input type="text" class="form-control" id="p_nombre" name="nombre" required
                   value="<?= htmlspecialchars($usuarioActual['nombre']) ?>">
            <div class="field-error"></div>
          </div>

          <div class="mb-3">
            <label class="form-label">Correo</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($usuarioActual['email']) ?>" disabled>
            <div class="hint">El correo no se puede cambiar desde aquí — pide a un administrador que lo actualice.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Rol</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars(timbra_rol_nombre($usuarioActual['rol'])) ?>" disabled>
          </div>

          <button type="submit" class="btn btn-timbra px-4" id="btn-perfil-submit">
            <span class="btn-text"><i class="bi bi-check2 me-1"></i> Guardar cambios</span>
            <span class="btn-spinner spinner-border spinner-border-sm ms-1 d-none"></span>
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card-timbra">
      <div class="card-header-timbra">
        <h2><i class="bi bi-shield-lock me-1"></i> Contraseña</h2>
      </div>
      <div class="card-body-timbra">
        <form id="form-password">
          <div class="mb-3">
            <label class="form-label" for="pw_actual">Contraseña actual</label>
            <input type="password" class="form-control" id="pw_actual" name="password_actual" required>
            <div class="field-error"></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="pw_nueva">Nueva contraseña</label>
            <input type="password" class="form-control" id="pw_nueva" name="password_nueva" required minlength="8">
            <div class="hint">Mínimo 8 caracteres.</div>
            <div class="field-error"></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="pw_confirmar">Confirmar nueva contraseña</label>
            <input type="password" class="form-control" id="pw_confirmar" name="password_nueva_confirmar" required minlength="8">
            <div class="field-error"></div>
          </div>
          <button type="submit" class="btn btn-outline-timbra px-4" id="btn-password-submit">
            <span class="btn-text"><i class="bi bi-key me-1"></i> Actualizar contraseña</span>
            <span class="btn-spinner spinner-border spinner-border-sm ms-1 d-none"></span>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
