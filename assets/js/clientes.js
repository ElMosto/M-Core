/* ==========================================================================
   Clientes
   ========================================================================== */

async function cargarTablaClientes() {
  const tbody = document.getElementById('tabla-clientes-body');
  let clientes;
  try {
    const res = await fetch('api/clientes.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    clientes = await res.json();
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="5" class="hint">No se pudieron cargar los clientes.</td></tr>';
    return;
  }

  if (!clientes.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="hint">Aún no hay clientes registrados.</td></tr>';
    return;
  }

  const esc = (s) => { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };

  tbody.innerHTML = clientes.map(c => `
    <tr>
      <td><strong>${esc(c.nombre)}</strong>${c.notas ? `<div class="hint mb-0">${esc(c.notas)}</div>` : ''}</td>
      <td>${c.contacto_nombre ? esc(c.contacto_nombre) : '<span class="hint mb-0">—</span>'}</td>
      <td class="mono" style="font-size:.82rem">${c.email ? esc(c.email) : '<span class="hint mb-0">—</span>'}</td>
      <td class="mono" style="font-size:.82rem">${c.telefono ? esc(c.telefono) : '<span class="hint mb-0">—</span>'}</td>
      <td>${c.total_proyectos}</td>
    </tr>
  `).join('');
}

document.addEventListener('DOMContentLoaded', () => {
  cargarTablaClientes();

  const form = document.getElementById('form-cliente');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const payload = Object.fromEntries(new FormData(form).entries());
    try {
      const res = await fetch('api/clientes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Cliente registrado', data.message);
        form.reset();
        bootstrap.Modal.getInstance(document.getElementById('modalCliente')).hide();
        cargarTablaClientes();
      } else {
        showToast('bad', 'No se pudo registrar el cliente', data.message);
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
      showToast('bad', 'Error de conexión', 'No se pudo registrar el cliente.');
    }
  });
});
