/* ==========================================================================
   Apariencia — logo del sistema y paleta de colores (solo admin)
   ========================================================================== */

function escapeHtmlAp(s) {
  const div = document.createElement('div');
  div.textContent = s == null ? '' : s;
  return div.innerHTML;
}

// 5 paletas de fábrica. La primera es la que ya traía el sistema de
// origen (el "sello verde"), para que quien no quiera cambiar nada la
// reconozca de inmediato.
const PALETAS_MCORE = [
  { clave: 'verde_sello', nombre: 'Verde sello',  color_ink: '#12211F', color_brand: '#0F6E5C', color_brand_dark: '#0B4F42' },
  { clave: 'azul_acero',  nombre: 'Azul acero',   color_ink: '#0F1B2D', color_brand: '#1B6FA8', color_brand_dark: '#124F79' },
  { clave: 'vino',        nombre: 'Vino',          color_ink: '#241019', color_brand: '#8E2F49', color_brand_dark: '#6B2138' },
  { clave: 'grafito_ambar', nombre: 'Grafito y ámbar', color_ink: '#1E1E1E', color_brand: '#B8651F', color_brand_dark: '#8C4B16' },
  { clave: 'indigo',       nombre: 'Índigo',        color_ink: '#191A32', color_brand: '#4F46E5', color_brand_dark: '#3730A3' },
];

function pintarLogoPreview(wrapId, tamano) {
  const wrap = document.getElementById(wrapId);
  if (!wrap) return;
  if (MCORE_APARIENCIA_ACTUAL.logo_path) {
    const src = escapeHtmlAp(MCORE_APARIENCIA_ACTUAL.logo_path);
    wrap.innerHTML = `<img src="${src}" alt="" style="width:${tamano}px;height:${tamano}px;object-fit:contain;border-radius:6px">`;
  } else {
    wrap.innerHTML = `<svg width="${tamano}" height="${tamano}" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
      <circle cx="20" cy="20" r="18" stroke="#5FBFA9" stroke-width="2.2"/>
      <circle cx="20" cy="20" r="12.5" stroke="#5FBFA9" stroke-width="1.4" stroke-dasharray="2.6 3"/>
      <path d="M13 21.5l4.5 4.5L27.5 15" stroke="#5FBFA9" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>`;
  }
}

function actualizarVistaPrevia() {
  const ink = document.getElementById('c_ink').value;
  const brand = document.getElementById('c_brand').value;
  document.getElementById('preview-sidebar').style.background = ink;
  document.getElementById('preview-btn-primario').style.background = brand;
  document.getElementById('preview-box').style.setProperty('--ink', ink);
}

function pintarPaletas() {
  const grid = document.getElementById('paletas-grid');
  const paletaActual = document.getElementById('c_paleta').value;

  grid.innerHTML = PALETAS_MCORE.map((p) => `
    <div class="col">
      <button type="button" class="btn-paleta ${p.clave === paletaActual ? 'seleccionada' : ''}" data-clave="${p.clave}"
              style="width:100%; text-align:left; border:1px solid var(--line); border-radius:10px; padding:10px 12px; background:#fff; cursor:pointer;">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <strong style="font-size:.86rem">${escapeHtmlAp(p.nombre)}</strong>
          ${p.clave === paletaActual ? '<i class="bi bi-check-circle-fill" style="color:var(--brand)"></i>' : ''}
        </div>
        <div class="d-flex gap-1">
          <span style="display:block;width:22px;height:22px;border-radius:5px;background:${p.color_ink}"></span>
          <span style="display:block;width:22px;height:22px;border-radius:5px;background:${p.color_brand}"></span>
          <span style="display:block;width:22px;height:22px;border-radius:5px;background:${p.color_brand_dark}"></span>
        </div>
      </button>
    </div>
  `).join('');

  grid.querySelectorAll('.btn-paleta').forEach((btn) => {
    btn.addEventListener('click', () => {
      const p = PALETAS_MCORE.find((x) => x.clave === btn.getAttribute('data-clave'));
      document.getElementById('c_ink').value = p.color_ink;
      document.getElementById('c_brand').value = p.color_brand;
      document.getElementById('c_brand_dark').value = p.color_brand_dark;
      document.getElementById('c_paleta').value = p.clave;
      actualizarVistaPrevia();
      pintarPaletas();
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  pintarLogoPreview('preview-logo-wrap', 24);
  pintarPaletas();
  actualizarVistaPrevia();

  ['c_ink', 'c_brand', 'c_brand_dark'].forEach((id) => {
    document.getElementById(id).addEventListener('input', () => {
      document.getElementById('c_paleta').value = 'personalizada';
      actualizarVistaPrevia();
      pintarPaletas();
    });
  });

  document.getElementById('form-colores').addEventListener('submit', async (e) => {
    e.preventDefault();
    document.getElementById('colores-error').textContent = '';

    const payload = {
      accion: 'guardar_colores',
      paleta: document.getElementById('c_paleta').value,
      color_ink: document.getElementById('c_ink').value,
      color_brand: document.getElementById('c_brand').value,
      color_brand_dark: document.getElementById('c_brand_dark').value,
    };

    try {
      const res = await fetch('api/apariencia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Colores actualizados', 'Recarga cualquier pantalla del sistema para verlos aplicados.');
        MCORE_APARIENCIA_ACTUAL.color_ink = payload.color_ink;
        MCORE_APARIENCIA_ACTUAL.color_brand = payload.color_brand;
        MCORE_APARIENCIA_ACTUAL.color_brand_dark = payload.color_brand_dark;
        MCORE_APARIENCIA_ACTUAL.paleta = payload.paleta;
      } else {
        showToast('bad', 'No se pudo guardar', data.message);
        document.getElementById('colores-error').textContent = data.message || '';
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudieron guardar los colores.');
    }
  });

  document.getElementById('input-logo').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    document.getElementById('logo-error').textContent = '';

    if (file.type !== 'image/png') {
      document.getElementById('logo-error').textContent = 'Sube un archivo PNG.';
      e.target.value = '';
      return;
    }
    if (file.size > 1024 * 1024) {
      document.getElementById('logo-error').textContent = 'El logo es demasiado pesado — máximo 1 MB.';
      e.target.value = '';
      return;
    }

    const fd = new FormData();
    fd.append('accion', 'subir_logo');
    fd.append('logo', file);

    try {
      const res = await fetch('api/apariencia.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Logo actualizado', data.message);
        MCORE_APARIENCIA_ACTUAL.logo_path = data.logo_path;
        document.getElementById('logo-preview-wrap').innerHTML =
          `<img src="${escapeHtmlAp(data.logo_path)}" alt="" style="width:44px;height:44px;object-fit:contain;border-radius:6px">`;
        document.getElementById('logo-estado-label').textContent = 'Usando tu logo personalizado.';
        document.getElementById('btn-quitar-logo').disabled = false;
        pintarLogoPreview('preview-logo-wrap', 24);
      } else {
        showToast('bad', 'No se pudo subir el logo', data.message);
        document.getElementById('logo-error').textContent = (data.errors && data.errors.logo) || data.message || '';
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo subir el logo.');
    } finally {
      e.target.value = '';
    }
  });

  document.getElementById('btn-quitar-logo').addEventListener('click', async () => {
    if (!confirm('¿Quitar el logo personalizado y volver al sello de fábrica?')) return;
    try {
      const res = await fetch('api/apariencia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'quitar_logo' })
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Logo quitado', data.message);
        MCORE_APARIENCIA_ACTUAL.logo_path = null;
        document.getElementById('logo-preview-wrap').innerHTML = `<svg width="44" height="44" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="20" cy="20" r="18" stroke="#5FBFA9" stroke-width="2.2"/>
          <circle cx="20" cy="20" r="12.5" stroke="#5FBFA9" stroke-width="1.4" stroke-dasharray="2.6 3"/>
          <path d="M13 21.5l4.5 4.5L27.5 15" stroke="#5FBFA9" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>`;
        document.getElementById('logo-estado-label').textContent = 'Usando el sello de fábrica.';
        document.getElementById('btn-quitar-logo').disabled = true;
        pintarLogoPreview('preview-logo-wrap', 24);
      } else {
        showToast('bad', 'No se pudo quitar el logo', data.message);
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'Intenta de nuevo.');
    }
  });
});
