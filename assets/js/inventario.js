/* ==========================================================================
   Inventario — catálogo, solicitudes de préstamo y (admin) aprobaciones
   ========================================================================== */

function escapeHtmlInv(s) {
  const div = document.createElement('div');
  div.textContent = s == null ? '' : s;
  return div.innerHTML;
}

const ESTADO_SOLICITUD_LABEL = { pendiente: 'Pendiente', aprobado: 'Aprobado', rechazado: 'Rechazado' };
const ESTADO_SOLICITUD_BADGE = { pendiente: 'media', aprobado: 'baja', rechazado: 'alta' };

function fechaCorta(fechaSql) {
  if (!fechaSql) return '—';
  return fechaSql.replace('T', ' ').slice(0, 16);
}

/* ==================== Catálogo ==================== */

let itemsCache = [];

async function cargarCatalogo() {
  const tbody = document.getElementById('tabla-items-body');
  const colspan = TIMBRA_ES_ADMIN ? 5 : 4;
  const incluirBaja = TIMBRA_ES_ADMIN && document.getElementById('chk-mostrar-baja')?.checked;

  let items;
  try {
    const res = await fetch(`api/inventario-items.php${incluirBaja ? '?todos=1' : ''}`, { cache: 'no-store' });
    if (!res.ok) throw new Error();
    items = await res.json();
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="hint">No se pudo cargar el catálogo.</td></tr>`;
    return;
  }

  itemsCache = items;

  if (!items.length) {
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="hint">Aún no hay artículos en el inventario.</td></tr>`;
    return;
  }

  tbody.innerHTML = items.map(it => {
    const puedeSolicitar = it.activo == 1 && it.cantidad_disponible > 0;
    let acciones = '';
    if (!TIMBRA_ES_ADMIN) {
      acciones = puedeSolicitar
        ? `<button class="btn btn-sm btn-timbra btn-solicitar" data-id="${it.id}">Solicitar</button>`
        : `<span class="hint mb-0">${it.activo == 1 ? 'Sin existencias' : 'No disponible'}</span>`;
    } else {
      acciones = `
        ${puedeSolicitar ? `<button class="btn btn-sm btn-outline-timbra btn-solicitar" data-id="${it.id}">Solicitar</button>` : ''}
        <button class="btn btn-sm btn-outline-secondary btn-editar-item" data-id="${it.id}" title="Editar"><i class="bi bi-pencil"></i></button>
        ${it.activo == 1
          ? `<button class="btn btn-sm btn-outline-secondary btn-baja-item" data-id="${it.id}" title="Dar de baja"><i class="bi bi-box-arrow-down"></i></button>`
          : `<button class="btn btn-sm btn-outline-secondary btn-reactivar-item" data-id="${it.id}" title="Reactivar"><i class="bi bi-arrow-counterclockwise"></i></button>`}
      `;
    }

    return `
      <tr>
        <td><strong>${escapeHtmlInv(it.nombre)}</strong>${it.descripcion ? `<div class="hint mb-0">${escapeHtmlInv(it.descripcion)}</div>` : ''}</td>
        <td class="mono">${it.cantidad_disponible}</td>
        <td class="mono">${it.cantidad_total}</td>
        ${TIMBRA_ES_ADMIN ? `<td><span class="badge ${it.activo == 1 ? 'text-bg-success' : 'text-bg-secondary'}">${it.activo == 1 ? 'Activo' : 'De baja'}</span></td>` : ''}
        <td class="text-end">${acciones}</td>
      </tr>
    `;
  }).join('');

  tbody.querySelectorAll('.btn-solicitar').forEach(btn => {
    btn.addEventListener('click', () => abrirModalSolicitar(parseInt(btn.getAttribute('data-id'), 10)));
  });
  if (!TIMBRA_ES_ADMIN) return;

  tbody.querySelectorAll('.btn-editar-item').forEach(btn => {
    btn.addEventListener('click', () => abrirModalItemParaEditar(parseInt(btn.getAttribute('data-id'), 10)));
  });
  tbody.querySelectorAll('.btn-baja-item').forEach(btn => {
    btn.addEventListener('click', () => cambiarBajaItem(parseInt(btn.getAttribute('data-id'), 10), 'baja'));
  });
  tbody.querySelectorAll('.btn-reactivar-item').forEach(btn => {
    btn.addEventListener('click', () => cambiarBajaItem(parseInt(btn.getAttribute('data-id'), 10), 'reactivar'));
  });
}

function resetearModalItem() {
  const form = document.getElementById('form-item');
  form.reset();
  document.getElementById('i_accion').value = 'crear';
  document.getElementById('i_id').value = '';
  document.getElementById('modalItemTitulo').textContent = 'Nuevo artículo';
  document.getElementById('modalItemBoton').textContent = 'Dar de alta';
  document.getElementById('i_cantidad_hint').textContent = '';
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  form.querySelectorAll('.field-error').forEach(el => el.textContent = '');
}

function abrirModalItemParaEditar(id) {
  const it = itemsCache.find(x => Number(x.id) === id);
  if (!it) return;

  resetearModalItem();
  document.getElementById('i_accion').value = 'editar';
  document.getElementById('i_id').value = it.id;
  document.getElementById('modalItemTitulo').textContent = 'Editar artículo';
  document.getElementById('modalItemBoton').textContent = 'Guardar cambios';
  document.getElementById('i_nombre').value = it.nombre;
  document.getElementById('i_descripcion').value = it.descripcion || '';
  document.getElementById('i_cantidad').value = it.cantidad_total;

  const prestadas = it.cantidad_total - it.cantidad_disponible;
  document.getElementById('i_cantidad_hint').textContent =
    prestadas > 0 ? `Hay ${prestadas} unidad(es) prestadas ahora mismo — no puedes bajar del total de eso.` : '';

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalItem')).show();
}

async function cambiarBajaItem(id, accion) {
  const it = itemsCache.find(x => Number(x.id) === id);
  const verbo = accion === 'baja' ? 'dar de baja' : 'reactivar';
  if (!confirm(`¿${verbo.charAt(0).toUpperCase() + verbo.slice(1)} "${it ? it.nombre : ''}"?`)) return;

  try {
    const res = await fetch('api/inventario-items.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion, id })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', accion === 'baja' ? 'Artículo dado de baja' : 'Artículo reactivado', data.message);
      cargarCatalogo();
    } else {
      showToast('bad', 'No se pudo actualizar', data.message);
    }
  } catch (e) {
    showToast('bad', 'Error de conexión', 'Intenta de nuevo.');
  }
}

/* ==================== Mis solicitudes ==================== */

async function cargarMisSolicitudes() {
  const tbody = document.getElementById('tabla-mis-solicitudes-body');
  let solicitudes;
  try {
    const res = await fetch('api/inventario-solicitudes.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    solicitudes = await res.json();
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="6" class="hint">No se pudieron cargar tus solicitudes.</td></tr>';
    return;
  }

  tbody.innerHTML = solicitudes.length
    ? solicitudes.map(s => `
      <tr>
        <td>${escapeHtmlInv(s.item_nombre)}</td>
        <td class="mono">${s.cantidad}</td>
        <td class="hint mb-0">${fechaCorta(s.fecha_solicitud)}</td>
        <td><span class="badge-prioridad ${ESTADO_SOLICITUD_BADGE[s.estado] || 'media'}">${ESTADO_SOLICITUD_LABEL[s.estado]}</span></td>
        <td>${s.aprobado_por_nombre ? escapeHtmlInv(s.aprobado_por_nombre) : '<span class="hint mb-0">—</span>'}</td>
        <td>${s.estado !== 'aprobado' ? '<span class="hint mb-0">—</span>' : (s.devuelto == 1 ? `<span class="badge text-bg-success">Sí</span>` : '<span class="badge text-bg-warning">No</span>')}</td>
      </tr>
    `).join('')
    : '<tr><td colspan="6" class="hint">Aún no has solicitado nada.</td></tr>';
}

function abrirModalSolicitar(itemId) {
  const it = itemsCache.find(x => Number(x.id) === itemId);
  const form = document.getElementById('form-solicitar');
  form.reset();
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  form.querySelectorAll('.field-error').forEach(el => el.textContent = '');
  document.getElementById('s_item_id').value = itemId;
  document.getElementById('s_cantidad').max = it ? it.cantidad_disponible : '';
  document.getElementById('modalSolicitarTitulo').textContent = it ? `Solicitar: ${it.nombre}` : 'Solicitar artículo';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSolicitar')).show();
}

/* ==================== Admin: pendientes + historial ==================== */

async function cargarPendientes() {
  const tbody = document.getElementById('tabla-pendientes-body');
  if (!tbody) return;
  let solicitudes;
  try {
    const res = await fetch('api/inventario-solicitudes.php?pendientes=1', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    solicitudes = await res.json();
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="6" class="hint">No se pudieron cargar las solicitudes pendientes.</td></tr>';
    return;
  }

  document.getElementById('pendientes-count-label').textContent = solicitudes.length ? `${solicitudes.length} pendiente(s)` : '';

  tbody.innerHTML = solicitudes.length
    ? solicitudes.map(s => `
      <tr>
        <td>${escapeHtmlInv(s.solicitado_por_nombre)}</td>
        <td>${escapeHtmlInv(s.item_nombre)}</td>
        <td class="mono">${s.cantidad}</td>
        <td class="hint mb-0">${fechaCorta(s.fecha_solicitud)}</td>
        <td class="hint mb-0">${s.notas ? escapeHtmlInv(s.notas) : '—'}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-timbra btn-aprobar-sol" data-id="${s.id}">Aprobar</button>
          <button class="btn btn-sm btn-outline-secondary btn-rechazar-sol" data-id="${s.id}">Rechazar</button>
        </td>
      </tr>
    `).join('')
    : '<tr><td colspan="6" class="hint">No hay solicitudes pendientes.</td></tr>';

  tbody.querySelectorAll('.btn-aprobar-sol').forEach(btn => {
    btn.addEventListener('click', () => resolverSolicitud(btn.getAttribute('data-id'), 'aprobar'));
  });
  tbody.querySelectorAll('.btn-rechazar-sol').forEach(btn => {
    btn.addEventListener('click', () => resolverSolicitud(btn.getAttribute('data-id'), 'rechazar'));
  });
}

async function resolverSolicitud(id, accion) {
  try {
    const res = await fetch('api/inventario-solicitudes.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion, id })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', accion === 'aprobar' ? 'Solicitud aprobada' : 'Solicitud rechazada', data.message);
    } else {
      showToast('bad', 'No se pudo resolver la solicitud', data.message);
    }
    cargarPendientes(); cargarHistorial(); cargarCatalogo();
  } catch (e) {
    showToast('bad', 'Error de conexión', 'Intenta de nuevo.');
  }
}

async function cargarHistorial() {
  const tbody = document.getElementById('tabla-historial-body');
  if (!tbody) return;
  let solicitudes;
  try {
    const res = await fetch('api/inventario-solicitudes.php?todas=1', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    solicitudes = await res.json();
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="8" class="hint">No se pudo cargar el historial.</td></tr>';
    return;
  }

  tbody.innerHTML = solicitudes.length
    ? solicitudes.map(s => {
        const puedeMarcarDevuelto = s.estado === 'aprobado' && s.devuelto == 0;
        return `
          <tr>
            <td>${escapeHtmlInv(s.solicitado_por_nombre)}</td>
            <td>${escapeHtmlInv(s.item_nombre)}</td>
            <td class="mono">${s.cantidad}</td>
            <td class="hint mb-0">${fechaCorta(s.fecha_solicitud)}</td>
            <td><span class="badge-prioridad ${ESTADO_SOLICITUD_BADGE[s.estado] || 'media'}">${ESTADO_SOLICITUD_LABEL[s.estado]}</span></td>
            <td>${s.aprobado_por_nombre ? escapeHtmlInv(s.aprobado_por_nombre) : '<span class="hint mb-0">—</span>'}</td>
            <td>${s.estado !== 'aprobado' ? '<span class="hint mb-0">—</span>' : (s.devuelto == 1 ? `<span class="badge text-bg-success">Sí · ${fechaCorta(s.fecha_devolucion)}</span>` : '<span class="badge text-bg-warning">No</span>')}</td>
            <td class="text-end">${puedeMarcarDevuelto ? `<button class="btn btn-sm btn-outline-timbra btn-marcar-devuelto" data-id="${s.id}">Marcar devuelto</button>` : ''}</td>
          </tr>
        `;
      }).join('')
    : '<tr><td colspan="8" class="hint">Todavía no hay solicitudes.</td></tr>';

  tbody.querySelectorAll('.btn-marcar-devuelto').forEach(btn => {
    btn.addEventListener('click', () => marcarDevuelto(btn.getAttribute('data-id')));
  });
}

async function marcarDevuelto(id) {
  try {
    const res = await fetch('api/inventario-solicitudes.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'marcar_devuelto', id })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', 'Préstamo devuelto', data.message);
    } else {
      showToast('bad', 'No se pudo marcar como devuelto', data.message);
    }
    cargarHistorial(); cargarCatalogo();
  } catch (e) {
    showToast('bad', 'Error de conexión', 'Intenta de nuevo.');
  }
}

/* ==================== Inicio ==================== */

document.addEventListener('DOMContentLoaded', () => {
  cargarCatalogo();
  cargarMisSolicitudes();
  if (TIMBRA_ES_ADMIN) {
    cargarPendientes();
    cargarHistorial();
    document.getElementById('chk-mostrar-baja').addEventListener('change', cargarCatalogo);
    document.getElementById('btn-nuevo-item').addEventListener('click', resetearModalItem);

    document.getElementById('form-item').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

      const esEdicion = document.getElementById('i_accion').value === 'editar';
      const payload = Object.fromEntries(new FormData(form).entries());

      try {
        const res = await fetch('api/inventario-items.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
          showToast('ok', esEdicion ? 'Artículo actualizado' : 'Artículo dado de alta', data.message);
          bootstrap.Modal.getInstance(document.getElementById('modalItem')).hide();
          cargarCatalogo();
        } else {
          showToast('bad', 'No se pudo guardar el artículo', data.message);
          Object.entries(data.errors || {}).forEach(([campo, msg]) => {
            const input = form.querySelector(`[name="${campo}"]`);
            if (input) {
              input.classList.add('is-invalid');
              const err = input.closest('.mb-3, .mb-1')?.querySelector('.field-error');
              if (err) err.textContent = msg;
            }
          });
        }
      } catch (err) {
        showToast('bad', 'Error de conexión', 'No se pudo guardar el artículo.');
      }
    });
  }

  document.getElementById('form-solicitar').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const payload = Object.fromEntries(new FormData(form).entries());
    payload.accion = 'solicitar';

    try {
      const res = await fetch('api/inventario-solicitudes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Solicitud enviada', data.message);
        bootstrap.Modal.getInstance(document.getElementById('modalSolicitar')).hide();
        cargarMisSolicitudes();
        if (TIMBRA_ES_ADMIN) cargarPendientes();
      } else {
        showToast('bad', 'No se pudo enviar la solicitud', data.message);
        Object.entries(data.errors || {}).forEach(([campo, msg]) => {
          const input = form.querySelector(`[name="${campo}"]`);
          if (input) {
            input.classList.add('is-invalid');
            const err = input.closest('.mb-3, .mb-1')?.querySelector('.field-error');
            if (err) err.textContent = msg;
          }
        });
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo enviar la solicitud.');
    }
  });
});
