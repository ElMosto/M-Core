/* ==========================================================================
   M-Core — utilidades compartidas
   - showToast(): retroalimentación inmediata (éxito / rechazo fiscal)
   - PAC status pill en la topbar, refrescado en tiempo real
   - helpers de inputs de archivo con estilo "dropzone" y toggle de password
   ========================================================================== */

/**
 * Muestra un toast flotante.
 * @param {'ok'|'bad'|'warn'} type
 * @param {string} title
 * @param {string} message
 * @param {string} [code]  código de error SAT / PAC opcional
 */
function showToast(type, title, message, code) {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const icon = type === 'ok' ? 'bi-patch-check-fill' : (type === 'warn' ? 'bi-exclamation-triangle-fill' : 'bi-x-octagon-fill');
  const cls  = type === 'ok' ? 'ok' : 'bad';

  const el = document.createElement('div');
  el.className = `toast toast-timbra ${cls} stamp-anim`;
  el.setAttribute('role', 'alert');
  el.innerHTML = `
    <div class="toast-header">
      <i class="bi ${icon} me-2"></i>
      <strong class="me-auto">${title}</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
    </div>
    <div class="toast-body">
      ${message}
      ${code ? `<div class="code mt-1">Código: ${code}</div>` : ''}
    </div>`;
  container.appendChild(el);
  const toast = new bootstrap.Toast(el, { delay: 6000 });
  toast.show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
}

/**
 * Activa el comportamiento visual de un input de archivo estilo "dropzone"
 * de una sola línea (usado en el formulario de alta de CSD).
 */
function wireFileInput(wrapperEl) {
  const input = wrapperEl.querySelector('input[type=file]');
  const label = wrapperEl.querySelector('.file-drop-name');
  const defaultText = label.textContent;
  input.addEventListener('change', () => {
    if (input.files.length) {
      const f = input.files[0];
      const kb = (f.size / 1024).toFixed(0);
      label.textContent = `${f.name} · ${kb} KB`;
      wrapperEl.classList.add('has-file');
    } else {
      label.textContent = defaultText;
      wrapperEl.classList.remove('has-file');
    }
  });
}

/** Toggle mostrar/ocultar para inputs de contraseña. */
function wirePasswordToggle(btn) {
  btn.addEventListener('click', () => {
    const targetId = btn.getAttribute('data-target');
    const input = document.getElementById(targetId);
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    btn.querySelector('i').className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
  });
}

/* ---------------- PWA: registro del service worker ---------------- */
/**
 * Necesario para que Chrome considere la app "instalable" (requisito para
 * empaquetarla con Bubblewrap como TWA) y para el respaldo sin conexión.
 * Silencioso si falla (ej. en un entorno sin HTTPS/localhost) — nunca debe
 * bloquear el resto de la página.
 */
function timbraRegistrarServiceWorker() {
  if (!('serviceWorker' in navigator)) return;
  navigator.serviceWorker.register('service-worker.js').catch(() => {
    // Silencioso a propósito: en HTTP (no localhost) el navegador rechaza
    // el registro, y no queremos ensuciar la consola en desarrollo.
  });
}

/* ---------------- Sidebar móvil (drawer) ---------------- */
function timbraWireMenuMovil() {
  const btn = document.getElementById('btn-abrir-menu');
  const sidebar = document.getElementById('app-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (!btn || !sidebar || !overlay) return;

  const abrir = () => { sidebar.classList.add('abierto'); overlay.classList.add('activo'); };
  const cerrar = () => { sidebar.classList.remove('abierto'); overlay.classList.remove('activo'); };

  btn.addEventListener('click', abrir);
  overlay.addEventListener('click', cerrar);
  // Si la pantalla crece de vuelta a escritorio (ej. al girar una tablet),
  // que no se quede "abierto" a fuerza estorbando al volver a achicarse.
  window.addEventListener('resize', () => { if (window.innerWidth >= 992) cerrar(); });
}

document.addEventListener('DOMContentLoaded', () => {
  // Modo oscuro
  const btnDark = document.getElementById('btn-dark-mode');
  if (localStorage.getItem('mcore-dark') === '1') {
    document.body.classList.add('dark-mode');
    if (btnDark) btnDark.innerHTML = '<i class="bi bi-sun"></i>';
  }
  if (btnDark) {
    btnDark.addEventListener('click', () => {
      document.body.classList.toggle('dark-mode');
      const on = document.body.classList.contains('dark-mode');
      localStorage.setItem('mcore-dark', on ? '1' : '0');
      btnDark.innerHTML = on ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon"></i>';
    });
  }

  timbraWireMenuMovil();
  timbraRegistrarServiceWorker();


  document.querySelectorAll('[data-file-drop]').forEach(wireFileInput);
  document.querySelectorAll('[data-pw-toggle]').forEach(wirePasswordToggle);
});
