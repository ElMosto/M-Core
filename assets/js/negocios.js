function escapeHtmlN(s){const d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}

let negociosCache = [];

async function cargarNegocios() {
  const res = await fetch('api/negocios.php?todos=1', { cache: 'no-store' });
  const data = await res.json();
  negociosCache = Array.isArray(data) ? data : [];
  renderNegocios();
}

function renderNegocios() {
  const tbody = document.getElementById('tbody-negocios');
  if (!negociosCache.length) {
    tbody.innerHTML = '<tr><td colspan="4" class="hint text-center py-4">No hay negocios registrados.</td></tr>';
    return;
  }
  tbody.innerHTML = negociosCache.map(n => `
    <tr>
      <td>${n.logo_path ? `<img src="${escapeHtmlN(n.logo_path)}" alt="" style="width:40px;height:40px;object-fit:contain;border-radius:6px">` : '<span class="hint">Sin logo</span>'}</td>
      <td><strong>${escapeHtmlN(n.nombre)}</strong></td>
      <td>${n.activo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'}</td>
      <td class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-secondary btn-sm" onclick="editarNegocio(${n.id})"><i class="bi bi-pencil"></i> Editar</button>
        <label class="btn btn-outline-secondary btn-sm mb-0" style="cursor:pointer">
          <i class="bi bi-upload"></i> Logo
          <input type="file" accept="image/*" class="d-none" onchange="subirLogoNegocio(${n.id}, this)">
        </label>
        ${n.logo_path ? `<button class="btn btn-outline-danger btn-sm" onclick="quitarLogoNegocio(${n.id})"><i class="bi bi-trash"></i></button>` : ''}
        <button class="btn btn-outline-secondary btn-sm" onclick="toggleNegocio(${n.id})">${n.activo ? 'Desactivar' : 'Activar'}</button>
      </td>
    </tr>
  `).join('');
}

function editarNegocio(id) {
  const n = negociosCache.find(x => x.id === id);
  if (!n) return;
  document.getElementById('negocio-id').value = n.id;
  document.getElementById('negocio-nombre').value = n.nombre;
  document.getElementById('modal-negocio-titulo').textContent = 'Editar negocio';
  new bootstrap.Modal(document.getElementById('modal-nuevo-negocio')).show();
}

document.getElementById('modal-nuevo-negocio').addEventListener('hidden.bs.modal', () => {
  document.getElementById('negocio-id').value = '';
  document.getElementById('negocio-nombre').value = '';
  document.getElementById('modal-negocio-titulo').textContent = 'Nuevo negocio';
});

document.getElementById('btn-guardar-negocio').addEventListener('click', async () => {
  const id = document.getElementById('negocio-id').value;
  const nombre = document.getElementById('negocio-nombre').value.trim();
  if (!nombre) { alert('El nombre es obligatorio.'); return; }
  const accion = id ? 'editar' : 'crear';
  const body = id ? { accion, id: Number(id), nombre } : { accion, nombre };
  const res = await fetch('api/negocios.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  const data = await res.json();
  if (!data.success) { alert(data.message || 'Error'); return; }
  bootstrap.Modal.getInstance(document.getElementById('modal-nuevo-negocio')).hide();
  await cargarNegocios();
});

async function toggleNegocio(id) {
  await fetch('api/negocios.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'toggle_activo', id }) });
  await cargarNegocios();
}

async function subirLogoNegocio(id, input) {
  if (!input.files || !input.files[0]) return;
  const fd = new FormData();
  fd.append('accion', 'subir_logo');
  fd.append('negocio_id', id);
  fd.append('logo', input.files[0]);
  const res = await fetch('api/negocios.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (!data.success) { alert(data.message || 'Error'); return; }
  await cargarNegocios();
}

async function quitarLogoNegocio(id) {
  if (!confirm('¿Quitar el logo de este negocio?')) return;
  await fetch('api/negocios.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ accion: 'quitar_logo', id }) });
  await cargarNegocios();
}

cargarNegocios();
