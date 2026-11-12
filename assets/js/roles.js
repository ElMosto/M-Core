/* ==========================================================================
   Roles (solo admin)
   ========================================================================== */

function escapeHtmlRol(s) {
  const div = document.createElement('div');
  div.textContent = s == null ? '' : s;
  return div.innerHTML;
}

let rolesTablaCache = [];

function checklistHtml(idPrefix, modulosMarcados) {
  return Object.entries(TIMBRA_MODULOS_ASIGNABLES).map(([clave, label]) => `
    <div class="col">
      <div class="form-check">
        <input type="checkbox" class="form-check-input" id="${idPrefix}_${clave}" value="${clave}"
               ${modulosMarcados.includes(clave) ? 'checked' : ''}>
        <label class="form-check-label" for="${idPrefix}_${clave}">${escapeHtmlRol(label)}</label>
      </div>
    </div>
  `).join('');
}

function modulosMarcadosDe(idPrefix) {
  return Array.from(document.querySelectorAll(`#${idPrefix}_modulos_checklist input:checked`)).map(i => i.value);
}

async function cargarRoles() {
  const tbody = document.getElementById('tabla-roles-body');
  let roles;
  try {
    const res = await fetch('api/roles.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    roles = await res.json();
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="5" class="hint">No se pudieron cargar los roles.</td></tr>';
    return;
  }

  rolesTablaCache = roles;

  tbody.innerHTML = roles.map(r => {
    const esAdmin = r.clave === 'admin';
    const modulosTxt = esAdmin
      ? 'Todos (fijo)'
      : (r.modulos.length ? r.modulos.map(m => TIMBRA_MODULOS_ASIGNABLES[m] || m).join(', ') : '<span class="hint mb-0">Ninguno</span>');

    let acciones = '';
    if (!esAdmin) {
      acciones += `<button class="btn btn-sm btn-outline-timbra btn-permisos-rol" data-id="${r.id}">Permisos</button> `;
      acciones += `<button class="btn btn-sm btn-outline-secondary btn-renombrar-rol" data-id="${r.id}" title="Renombrar"><i class="bi bi-pencil"></i></button> `;
      if (!r.es_sistema && r.num_usuarios === 0) {
        acciones += `<button class="btn btn-sm btn-outline-secondary btn-eliminar-rol" data-id="${r.id}" title="Eliminar"><i class="bi bi-trash"></i></button>`;
      }
    }

    return `
      <tr>
        <td><strong>${escapeHtmlRol(r.nombre)}</strong> ${r.es_sistema ? '<span class="badge text-bg-secondary">Sistema</span>' : ''}</td>
        <td class="mono">${escapeHtmlRol(r.clave)}</td>
        <td class="hint mb-0" style="max-width:340px">${modulosTxt}</td>
        <td>${r.num_usuarios}</td>
        <td class="text-end">${acciones}</td>
      </tr>
    `;
  }).join('');

  tbody.querySelectorAll('.btn-permisos-rol').forEach(btn => {
    btn.addEventListener('click', () => abrirModalPermisos(parseInt(btn.getAttribute('data-id'), 10)));
  });
  tbody.querySelectorAll('.btn-renombrar-rol').forEach(btn => {
    btn.addEventListener('click', () => abrirModalRenombrar(parseInt(btn.getAttribute('data-id'), 10)));
  });
  tbody.querySelectorAll('.btn-eliminar-rol').forEach(btn => {
    btn.addEventListener('click', () => eliminarRol(parseInt(btn.getAttribute('data-id'), 10)));
  });
}

function abrirModalPermisos(id) {
  const r = rolesTablaCache.find(x => Number(x.id) === id);
  if (!r) return;
  document.getElementById('p_id').value = r.id;
  document.getElementById('modalPermisosTitulo').textContent = `Permisos de "${r.nombre}"`;
  document.getElementById('p_modulos_checklist').innerHTML = checklistHtml('p', r.modulos);
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPermisos')).show();
}

function abrirModalRenombrar(id) {
  const r = rolesTablaCache.find(x => Number(x.id) === id);
  if (!r) return;
  document.getElementById('rn_id').value = r.id;
  document.getElementById('rn_nombre').value = r.nombre;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRenombrar')).show();
}

async function eliminarRol(id) {
  const r = rolesTablaCache.find(x => Number(x.id) === id);
  if (!confirm(`¿Eliminar el rol "${r ? r.nombre : ''}"? Esta acción no se puede deshacer.`)) return;

  try {
    const res = await fetch('api/roles.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'eliminar', id })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', 'Rol eliminado', data.message);
      cargarRoles();
    } else {
      showToast('bad', 'No se pudo eliminar', data.message);
    }
  } catch (e) {
    showToast('bad', 'Error de conexión', 'Intenta de nuevo.');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('r_modulos_checklist').innerHTML = checklistHtml('r', []);
  cargarRoles();

  document.getElementById('form-rol').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const payload = {
      accion: 'crear',
      nombre: document.getElementById('r_nombre').value,
      clave: document.getElementById('r_clave').value,
      modulos: modulosMarcadosDe('r'),
    };

    try {
      const res = await fetch('api/roles.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Rol creado', data.message);
        form.reset();
        document.getElementById('r_modulos_checklist').innerHTML = checklistHtml('r', []);
        bootstrap.Modal.getInstance(document.getElementById('modalRol')).hide();
        cargarRoles();
      } else {
        showToast('bad', 'No se pudo crear el rol', data.message);
        Object.entries(data.errors || {}).forEach(([campo, msg]) => {
          const input = form.querySelector(`[name="${campo}"]`);
          if (input) {
            input.classList.add('is-invalid');
            const err = input.closest('.mb-3')?.querySelector('.field-error');
            if (err) err.textContent = msg;
          }
        });
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo crear el rol.');
    }
  });

  document.getElementById('form-permisos').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = parseInt(document.getElementById('p_id').value, 10);
    const modulos = modulosMarcadosDe('p');

    try {
      const res = await fetch('api/roles.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'editar_permisos', id, modulos })
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Permisos actualizados', data.message);
        bootstrap.Modal.getInstance(document.getElementById('modalPermisos')).hide();
        cargarRoles();
      } else {
        showToast('bad', 'No se pudo guardar', data.message);
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo guardar los permisos.');
    }
  });

  document.getElementById('form-renombrar').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const payload = Object.fromEntries(new FormData(form).entries());
    payload.accion = 'renombrar';

    try {
      const res = await fetch('api/roles.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Rol renombrado', data.message);
        bootstrap.Modal.getInstance(document.getElementById('modalRenombrar')).hide();
        cargarRoles();
      } else {
        showToast('bad', 'No se pudo renombrar', data.message);
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
      showToast('bad', 'Error de conexión', 'No se pudo renombrar el rol.');
    }
  });
});
