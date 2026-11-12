/* ==========================================================================
   Usuarios y roles (solo admin)
   Los roles ya no son 4 fijos — se cargan desde api/roles.php, así que
   cualquier rol nuevo que crees en la pantalla de Roles aparece aquí solo.
   ========================================================================== */

let rolesCache = []; // [{ id, clave, nombre, es_sistema }]

async function cargarRolesDisponibles() {
  try {
    const res = await fetch('api/roles.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    rolesCache = await res.json();
  } catch (e) {
    rolesCache = [];
  }
}

function nombreDeRol(clave) {
  const r = rolesCache.find(x => x.clave === clave);
  return r ? r.nombre : clave;
}

function poblarSelectRolNuevoUsuario() {
  const select = document.getElementById('u_rol');
  select.innerHTML = rolesCache.map(r =>
    `<option value="${r.clave}" ${r.clave === 'colaborador' ? 'selected' : ''}>${escapeHtmlUsr(r.nombre)}</option>`
  ).join('');
}

function escapeHtmlUsr(s) {
  const div = document.createElement('div');
  div.textContent = s == null ? '' : s;
  return div.innerHTML;
}

let usuariosCache = [];

async function cargarUsuarios() {
  const tbody = document.getElementById('tabla-usuarios-body');
  let usuarios;
  try {
    const res = await fetch('api/usuarios.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    usuarios = await res.json();
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="5" class="hint">No se pudo cargar la lista de usuarios.</td></tr>';
    return;
  }

  usuariosCache = usuarios;

  if (!usuarios.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="hint">Aún no hay usuarios.</td></tr>';
    return;
  }

  tbody.innerHTML = usuarios.map(u => `
    <tr data-id="${u.id}">
      <td>${escapeHtmlUsr(u.nombre)}</td>
      <td class="mono" style="font-size:.82rem">${escapeHtmlUsr(u.email)}</td>
      <td>
        <select class="form-select form-select-sm select-rol" style="min-width:180px" data-id="${u.id}">
          ${rolesCache.map(r => `<option value="${r.clave}" ${u.rol === r.clave ? 'selected' : ''}>${escapeHtmlUsr(r.nombre)}</option>`).join('')}
        </select>
      </td>
      <td>
        <span class="badge ${u.activo ? 'text-bg-success' : 'text-bg-secondary'}">${u.activo ? 'Activo' : 'Inactivo'}</span>
      </td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-secondary btn-editar-usuario" data-id="${u.id}" title="Editar"><i class="bi bi-pencil"></i></button>
        <button class="btn btn-sm btn-outline-secondary btn-toggle-activo" data-id="${u.id}">
          ${u.activo ? 'Desactivar' : 'Activar'}
        </button>
      </td>
    </tr>
  `).join('');

  tbody.querySelectorAll('.btn-editar-usuario').forEach(btn => {
    btn.addEventListener('click', () => abrirModalEditarUsuario(parseInt(btn.getAttribute('data-id'), 10)));
  });

  tbody.querySelectorAll('.select-rol').forEach(sel => {
    sel.addEventListener('change', async () => {
      const id = sel.getAttribute('data-id');
      try {
        const res = await fetch('api/usuarios.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ accion: 'cambiar_rol', id, rol: sel.value })
        });
        const data = await res.json();
        if (!data.success) { showToast('bad', 'No se pudo cambiar el rol', data.message); cargarUsuarios(); return; }
        showToast('ok', 'Rol actualizado', `Ahora es ${nombreDeRol(sel.value)}.`);
      } catch (e) { showToast('bad', 'Error de conexión', 'No se pudo actualizar el rol.'); }
    });
  });

  tbody.querySelectorAll('.btn-toggle-activo').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.getAttribute('data-id');
      try {
        const res = await fetch('api/usuarios.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ accion: 'toggle_activo', id })
        });
        const data = await res.json();
        if (!data.success) { showToast('bad', 'No se pudo actualizar', data.message); return; }
        cargarUsuarios();
      } catch (e) { showToast('bad', 'Error de conexión', 'Intenta de nuevo.'); }
    });
  });
}

function abrirModalEditarUsuario(id) {
  const u = usuariosCache.find(x => Number(x.id) === id);
  if (!u) return;

  const form = document.getElementById('form-editar-usuario');
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

  document.getElementById('eu_id').value = u.id;
  document.getElementById('eu_nombre').value = u.nombre;
  document.getElementById('eu_email').value = u.email;
  document.getElementById('eu_password').value = '';

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarUsuario')).show();
}

document.addEventListener('DOMContentLoaded', async () => {
  await cargarRolesDisponibles();
  poblarSelectRolNuevoUsuario();
  cargarUsuarios();

  const form = document.getElementById('form-usuario');
  const btn = document.getElementById('btn-usuario-submit');
  const btnText = btn.querySelector('.btn-text');
  const btnSpinner = btn.querySelector('.btn-spinner');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const payload = Object.fromEntries(new FormData(form).entries());
    payload.accion = 'crear';

    btn.disabled = true; btnSpinner.classList.remove('d-none'); btnText.classList.add('d-none');
    try {
      const res = await fetch('api/usuarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Usuario creado', data.message);
        form.reset();
        poblarSelectRolNuevoUsuario();
        cargarUsuarios();
      } else {
        showToast('bad', 'No se pudo crear el usuario', data.message);
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
      showToast('bad', 'Error de conexión', 'No se pudo crear el usuario.');
    } finally {
      btn.disabled = false; btnSpinner.classList.add('d-none'); btnText.classList.remove('d-none');
    }
  });

  const formEditar = document.getElementById('form-editar-usuario');
  formEditar.addEventListener('submit', async (e) => {
    e.preventDefault();
    formEditar.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    formEditar.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const payload = {
      accion: 'editar',
      id: document.getElementById('eu_id').value,
      nombre: document.getElementById('eu_nombre').value,
      email: document.getElementById('eu_email').value,
      password: document.getElementById('eu_password').value,
    };

    try {
      const res = await fetch('api/usuarios.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Usuario actualizado', data.message);
        bootstrap.Modal.getInstance(document.getElementById('modalEditarUsuario')).hide();
        cargarUsuarios();
      } else {
        showToast('bad', 'No se pudo guardar', data.message);
        Object.entries(data.errors || {}).forEach(([campo, msg]) => {
          const input = formEditar.querySelector(`[name="${campo}"]`);
          if (input) {
            input.classList.add('is-invalid');
            const err = input.closest('.mb-3, .mb-1')?.querySelector('.field-error');
            if (err) err.textContent = msg;
          }
        });
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo guardar los cambios.');
    }
  });
});
