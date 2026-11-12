<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_modulo('apariencia');
$apariencia = timbra_apariencia();

$pageTitle    = 'Apariencia';
$activeNav    = 'apariencia';
$headerTitle  = 'Apariencia';
$headerSub    = 'Logo y colores del sistema — se aplican para todos los usuarios';
$extraScripts = ['assets/js/apariencia.js'];
include __DIR__ . '/includes/layout-top.php';
?>

<script>
  const MCORE_APARIENCIA_ACTUAL = <?= json_encode($apariencia, JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card-timbra mb-4">
      <div class="card-header-timbra"><h2><i class="bi bi-image me-1"></i> Logo del sistema</h2></div>
      <div class="card-body-timbra">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div id="logo-preview-wrap" style="width:64px; height:64px; display:flex; align-items:center; justify-content:center; background:var(--ink); border-radius:10px">
            <?= timbra_logo_html(44) ?>
          </div>
          <div>
            <div class="hint mb-0" id="logo-estado-label">
              <?= !empty($apariencia['logo_path']) ? 'Usando tu logo personalizado.' : 'Usando el sello de fábrica.' ?>
            </div>
            <div class="hint">PNG, máx. 1 MB. Se ve mejor cuadrado y con fondo transparente.</div>
          </div>
        </div>

        <form id="form-logo" class="d-flex align-items-center gap-2 flex-wrap">
          <label class="btn btn-timbra btn-sm mb-0" for="input-logo">
            <i class="bi bi-upload me-1"></i> Subir logo (PNG)
          </label>
          <input type="file" id="input-logo" accept="image/png" class="d-none">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-quitar-logo" <?= empty($apariencia['logo_path']) ? 'disabled' : '' ?>>
            Quitar y usar el de fábrica
          </button>
        </form>
        <div class="field-error mt-2" id="logo-error"></div>
      </div>
    </div>

    <div class="card-timbra">
      <div class="card-header-timbra"><h2><i class="bi bi-eye me-1"></i> Vista previa</h2></div>
      <div class="card-body-timbra">
        <div id="preview-box" style="border-radius:10px; overflow:hidden; border:1px solid var(--line)">
          <div id="preview-sidebar" style="padding:14px; display:flex; align-items:center; gap:8px;">
            <span id="preview-logo-wrap"></span>
            <strong style="color:#fff; font-family:var(--font-display)">M-Core</strong>
          </div>
          <div style="padding:16px; background:var(--panel)">
            <button type="button" class="btn btn-sm mb-2" id="preview-btn-primario" style="color:#fff">Botón primario</button>
            <div class="hint mb-0">Así se ve el color de acento en botones y elementos activos.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card-timbra mb-4">
      <div class="card-header-timbra"><h2><i class="bi bi-palette me-1"></i> Paletas sugeridas</h2></div>
      <div class="card-body-timbra">
        <div class="row row-cols-1 row-cols-sm-2 g-3" id="paletas-grid"></div>
      </div>
    </div>

    <div class="card-timbra">
      <div class="card-header-timbra"><h2><i class="bi bi-sliders me-1"></i> O elige tus propios colores</h2></div>
      <div class="card-body-timbra">
        <form id="form-colores">
          <input type="hidden" id="c_paleta" value="<?= htmlspecialchars($apariencia['paleta']) ?>">
          <div class="row g-3 mb-3">
            <div class="col-sm-4">
              <label class="form-label" for="c_ink">Fondo oscuro (sidebar, login)</label>
              <input type="color" class="form-control form-control-color w-100" id="c_ink" value="<?= htmlspecialchars($apariencia['color_ink']) ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label" for="c_brand">Color principal</label>
              <input type="color" class="form-control form-control-color w-100" id="c_brand" value="<?= htmlspecialchars($apariencia['color_brand']) ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label" for="c_brand_dark">Principal (oscuro)</label>
              <input type="color" class="form-control form-control-color w-100" id="c_brand_dark" value="<?= htmlspecialchars($apariencia['color_brand_dark']) ?>">
            </div>
          </div>
          <div class="field-error mb-2" id="colores-error"></div>
          <button type="submit" class="btn btn-timbra">
            <i class="bi bi-check2 me-1"></i> Guardar colores
          </button>
          <span class="hint ms-2">Se aplica para todos los usuarios apenas recarguen la página.</span>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
