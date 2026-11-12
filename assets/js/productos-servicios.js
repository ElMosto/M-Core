/* ==========================================================================
   Productos y servicios (solo admin)
   ========================================================================== */

function escapeHtmlPS(s) {
  const div = document.createElement('div');
  div.textContent = s == null ? '' : s;
  return div.innerHTML;
}

let psCache = [];

async function cargarTablaPS() {
  const tbody = document.getElementById('tabla-ps-body');
  const incluirBaja = document.getElementById('chk-mostrar-baja').checked;

  let items;
  try {
    const res = await fetch(`api/productos-servicios.php${incluirBaja ? '?todos=1' : ''}`, { cache: 'no-store' });
    if (!res.ok) throw new Error();
    items = await res.json();
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="5" class="hint">No se pudo cargar el catálogo.</td></tr>';
    return;
  }

  psCache = items;

  if (!items.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="hint">Aún no hay productos ni servicios registrados.</td></tr>';
    return;
  }

  tbody.innerHTML = items.map(it => `
    <tr>
      <td><strong>${escapeHtmlPS(it.nombre)}</strong>${it.descripcion ? `<div class="hint mb-0">${escapeHtmlPS(it.descripcion)}</div>` : ''}</td>
      <td><span class="badge-prioridad ${it.tipo === 'servicio' ? 'media' : 'baja'}">${it.tipo === 'servicio' ? 'Servicio' : 'Producto'}</span></td>
      <td class="mono">$${Number(it.precio).toFixed(2)}</td>
      <td><span class="badge ${it.activo == 1 ? 'text-bg-success' : 'text-bg-secondary'}">${it.activo == 1 ? 'Activo' : 'De baja'}</span></td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-secondary btn-editar-ps" data-id="${it.id}" title="Editar"><i class="bi bi-pencil"></i></button>
        ${it.activo == 1
          ? `<button class="btn btn-sm btn-outline-secondary btn-baja-ps" data-id="${it.id}" title="Dar de baja"><i class="bi bi-box-arrow-down"></i></button>`
          : `<button class="btn btn-sm btn-outline-secondary btn-reactivar-ps" data-id="${it.id}" title="Reactivar"><i class="bi bi-arrow-counterclockwise"></i></button>`}
      </td>
    </tr>
  `).join('');

  tbody.querySelectorAll('.btn-editar-ps').forEach(btn => {
    btn.addEventListener('click', () => abrirModalPSParaEditar(parseInt(btn.getAttribute('data-id'), 10)));
  });
  tbody.querySelectorAll('.btn-baja-ps').forEach(btn => {
    btn.addEventListener('click', () => cambiarBajaPS(parseInt(btn.getAttribute('data-id'), 10), 'baja'));
  });
  tbody.querySelectorAll('.btn-reactivar-ps').forEach(btn => {
    btn.addEventListener('click', () => cambiarBajaPS(parseInt(btn.getAttribute('data-id'), 10), 'reactivar'));
  });
}

function resetearModalPS() {
  const form = document.getElementById('form-ps');
  form.reset();
  document.getElementById('ps_accion').value = 'crear';
  document.getElementById('ps_id').value = '';
  document.getElementById('modalPSTitulo').textContent = 'Nuevo producto/servicio';
  document.getElementById('modalPSBoton').textContent = 'Registrar';
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  form.querySelectorAll('.field-error').forEach(el => el.textContent = '');
}

function abrirModalPSParaEditar(id) {
  const it = psCache.find(x => Number(x.id) === id);
  if (!it) return;

  resetearModalPS();
  document.getElementById('ps_accion').value = 'editar';
  document.getElementById('ps_id').value = it.id;
  document.getElementById('modalPSTitulo').textContent = 'Editar producto/servicio';
  document.getElementById('modalPSBoton').textContent = 'Guardar cambios';
  document.getElementById('ps_nombre').value = it.nombre;
  document.getElementById('ps_descripcion').value = it.descripcion || '';
  document.getElementById('ps_precio').value = it.precio;
  document.getElementById(it.tipo === 'servicio' ? 'ps_tipo_servicio' : 'ps_tipo_producto').checked = true;

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPS')).show();
}

async function cambiarBajaPS(id, accion) {
  const it = psCache.find(x => Number(x.id) === id);
  const verbo = accion === 'baja' ? 'dar de baja' : 'reactivar';
  if (!confirm(`¿${verbo.charAt(0).toUpperCase() + verbo.slice(1)} "${it ? it.nombre : ''}"?`)) return;

  try {
    const res = await fetch('api/productos-servicios.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion, id })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', accion === 'baja' ? 'Dado de baja' : 'Reactivado', data.message);
      cargarTablaPS();
    } else {
      showToast('bad', 'No se pudo actualizar', data.message);
    }
  } catch (e) {
    showToast('bad', 'Error de conexión', 'Intenta de nuevo.');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  cargarTablaPS();
  document.getElementById('chk-mostrar-baja').addEventListener('change', cargarTablaPS);
  document.getElementById('btn-nuevo-ps').addEventListener('click', resetearModalPS);

  document.getElementById('form-ps').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const esEdicion = document.getElementById('ps_accion').value === 'editar';
    const payload = Object.fromEntries(new FormData(form).entries());

    try {
      const res = await fetch('api/productos-servicios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', esEdicion ? 'Actualizado' : 'Registrado', data.message);
        bootstrap.Modal.getInstance(document.getElementById('modalPS')).hide();
        cargarTablaPS();
      } else {
        showToast('bad', 'No se pudo guardar', data.message);
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
      showToast('bad', 'Error de conexión', 'No se pudo guardar.');
    }
  });
});
