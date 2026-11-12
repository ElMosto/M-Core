/* ==========================================================================
   Leads — configuración de fuentes (admin), simulación, tabla, asignación
   ========================================================================== */

function escapeHtmlLead(s) {
  const div = document.createElement('div');
  div.textContent = s == null ? '' : s;
  return div.innerHTML;
}

const ESTADO_LEAD_LABEL = { nuevo: 'Nuevo', asignado: 'Asignado', contactado: 'Contactado', convertido: 'Convertido', descartado: 'Descartado' };
const FUENTE_ICONO = { meta: 'bi-facebook', linkedin: 'bi-linkedin' };
const FUENTE_LABEL = { meta: 'Meta', linkedin: 'LinkedIn' };

let leadsCache = [];
let paginaActualLeads = 1;
const LEADS_POR_PAGINA = 20;

/* ==================== Configuración de fuentes (solo admin) ==================== */

async function cargarConfigFuentes() {
  try {
    const res = await fetch('api/leads-config.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    const cfg = await res.json();
    document.getElementById('cfg_meta').checked = !!cfg.meta_activo;
    document.getElementById('cfg_linkedin').checked = !!cfg.linkedin_activo;
  } catch (e) { /* deja las casillas como estén */ }
}

/* ==================== Tabla de leads (paginada, 20 por página) ==================== */

async function cargarLeads() {
  const tbody = document.getElementById('tabla-leads-body');
  const colspan = TIMBRA_PUEDE_GESTIONAR_LEADS ? 8 : 7;

  let leads;
  try {
    const res = await fetch('api/leads.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    leads = await res.json();
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="hint">No se pudieron cargar los leads.</td></tr>`;
    document.getElementById('paginacion-leads').style.display = 'none';
    return;
  }

  leadsCache = leads;
  paginaActualLeads = 1;
  renderTablaLeads();
}

function renderTablaLeads() {
  const tbody = document.getElementById('tabla-leads-body');
  const colspan = TIMBRA_PUEDE_GESTIONAR_LEADS ? 8 : 7;
  const total = leadsCache.length;

  document.getElementById('leads-count-label').textContent = total ? `${total} lead(s)` : '';

  if (!total) {
    tbody.innerHTML = `<tr><td colspan="${colspan}" class="hint">${TIMBRA_PUEDE_GESTIONAR_LEADS ? 'Todavía no hay leads.' : 'No tienes leads asignados por ahora.'}</td></tr>`;
    document.getElementById('paginacion-leads').style.display = 'none';
    return;
  }

  const totalPaginas = Math.max(1, Math.ceil(total / LEADS_POR_PAGINA));
  if (paginaActualLeads > totalPaginas) paginaActualLeads = totalPaginas;

  const inicio = (paginaActualLeads - 1) * LEADS_POR_PAGINA;
  const leadsPagina = leadsCache.slice(inicio, inicio + LEADS_POR_PAGINA);

  tbody.innerHTML = leadsPagina.map(l => {
    const contacto = [l.email, l.telefono].filter(Boolean).map(escapeHtmlLead).join('<br>') || '<span class="hint mb-0">—</span>';
    const selectEstado = `
      <select class="form-select form-select-sm select-estado-lead" data-id="${l.id}" style="width:auto">
        ${Object.entries(ESTADO_LEAD_LABEL).map(([val, label]) => `<option value="${val}" ${l.estado === val ? 'selected' : ''}>${label}</option>`).join('')}
      </select>`;

    return `
      <tr>
        <td><strong>${escapeHtmlLead(l.nombre)}</strong>${l.mensaje ? `<div class="hint mb-0">${escapeHtmlLead(l.mensaje)}</div>` : ''}</td>
        <td>${l.empresa ? escapeHtmlLead(l.empresa) : '<span class="hint mb-0">—</span>'}</td>
        <td class="mono" style="font-size:.8rem">${contacto}</td>
        <td><span class="hint mb-0"><i class="bi ${FUENTE_ICONO[l.fuente] || 'bi-globe'} me-1"></i>${FUENTE_LABEL[l.fuente] || l.fuente}</span></td>
        ${TIMBRA_PUEDE_GESTIONAR_LEADS ? `<td>${l.asignado_nombre ? escapeHtmlLead(l.asignado_nombre) : '<span class="hint mb-0">Sin asignar</span>'}</td>` : ''}
        <td>${selectEstado}</td>
        <td class="hint mb-0">${(l.created_at || '').slice(0, 10)}</td>
        <td class="text-end">${TIMBRA_PUEDE_GESTIONAR_LEADS ? `<button class="btn btn-sm btn-outline-timbra btn-asignar-lead" data-id="${l.id}">Asignar a</button>` : ''}</td>
      </tr>
    `;
  }).join('');

  tbody.querySelectorAll('.select-estado-lead').forEach(sel => {
    sel.addEventListener('change', () => cambiarEstadoLead(sel.getAttribute('data-id'), sel.value));
  });
  tbody.querySelectorAll('.btn-asignar-lead').forEach(btn => {
    btn.addEventListener('click', () => abrirModalAsignar(parseInt(btn.getAttribute('data-id'), 10)));
  });

  const paginacion = document.getElementById('paginacion-leads');
  paginacion.style.display = totalPaginas > 1 ? 'flex' : 'none';
  document.getElementById('paginacion-leads-info').textContent =
    `Mostrando ${inicio + 1}–${Math.min(inicio + LEADS_POR_PAGINA, total)} de ${total} · página ${paginaActualLeads} de ${totalPaginas}`;
  document.getElementById('btn-leads-pagina-anterior').disabled = paginaActualLeads <= 1;
  document.getElementById('btn-leads-pagina-siguiente').disabled = paginaActualLeads >= totalPaginas;
}

async function cambiarEstadoLead(id, estado) {
  try {
    const res = await fetch('api/leads.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'cambiar_estado', id, estado })
    });
    const data = await res.json();
    if (!data.success) { showToast('bad', 'No se pudo actualizar', data.message); renderTablaLeads(); return; }
    const lead = leadsCache.find(x => Number(x.id) === Number(id));
    if (lead) lead.estado = estado;
    showToast('ok', 'Estado actualizado', `Ahora está "${ESTADO_LEAD_LABEL[estado]}".`);
  } catch (e) {
    showToast('bad', 'Error de conexión', 'Intenta de nuevo.');
  }
}

/* ==================== Asignar ==================== */

async function abrirModalAsignar(id) {
  const lead = leadsCache.find(x => Number(x.id) === id);
  if (!lead) return;

  document.getElementById('al_lead_id').value = id;
  document.getElementById('al_lead_nombre').textContent = `Lead: ${lead.nombre}${lead.empresa ? ' · ' + lead.empresa : ''}`;

  const select = document.getElementById('al_colaborador');
  select.innerHTML = '<option value="" disabled selected>Cargando…</option>';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAsignarLead')).show();

  try {
    const res = await fetch('api/leads.php?colaboradores_asignables=1', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    const usuarios = await res.json();
    select.innerHTML = usuarios.length
      ? '<option value="" disabled selected>Selecciona a alguien…</option>' +
        usuarios.map(u => `<option value="${u.id}" ${Number(lead.asignado_a) === u.id ? 'selected' : ''}>${escapeHtmlLead(u.nombre)} (${escapeHtmlLead(u.rol_nombre || '')})</option>`).join('')
      : '<option value="">No hay colaboradores disponibles</option>';
  } catch (e) {
    select.innerHTML = '<option value="">No se pudo cargar la lista</option>';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  cargarLeads();

  document.getElementById('btn-leads-pagina-anterior').addEventListener('click', () => {
    if (paginaActualLeads > 1) { paginaActualLeads--; renderTablaLeads(); }
  });
  document.getElementById('btn-leads-pagina-siguiente').addEventListener('click', () => {
    const totalPaginas = Math.max(1, Math.ceil(leadsCache.length / LEADS_POR_PAGINA));
    if (paginaActualLeads < totalPaginas) { paginaActualLeads++; renderTablaLeads(); }
  });

  if (TIMBRA_ES_ADMIN_LEADS) {
    cargarConfigFuentes();

    document.getElementById('form-fuentes').addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = {
        meta_activo: document.getElementById('cfg_meta').checked,
        linkedin_activo: document.getElementById('cfg_linkedin').checked,
      };
      try {
        const res = await fetch('api/leads-config.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        showToast(data.success ? 'ok' : 'bad', data.success ? 'Fuentes actualizadas' : 'No se pudo guardar', data.message);
      } catch (err) {
        showToast('bad', 'Error de conexión', 'No se pudo guardar la configuración.');
      }
    });

    document.getElementById('btn-simular-leads').addEventListener('click', async (e) => {
      const btn = e.currentTarget;
      btn.disabled = true;
      const textoOriginal = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generando…';

      try {
        const res = await fetch('api/leads.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ accion: 'simular' })
        });
        const data = await res.json();
        if (data.success) {
          showToast('ok', 'Leads generados', data.message);
          cargarLeads();
        } else {
          showToast('bad', 'No se pudo simular', data.message);
        }
      } catch (err) {
        showToast('bad', 'Error de conexión', 'No se pudo generar la simulación.');
      } finally {
        btn.disabled = false;
        btn.innerHTML = textoOriginal;
      }
    });
  }

  document.getElementById('form-asignar-lead')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const id = document.getElementById('al_lead_id').value;
    const colaboradorId = document.getElementById('al_colaborador').value;

    try {
      const res = await fetch('api/leads.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'asignar', id, colaborador_id: colaboradorId })
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Lead asignado', data.message);
        bootstrap.Modal.getInstance(document.getElementById('modalAsignarLead')).hide();
        cargarLeads();
      } else {
        showToast('bad', 'No se pudo asignar', data.message);
        if (data.errors && data.errors.colaborador_id) {
          document.getElementById('al_colaborador').classList.add('is-invalid');
          document.querySelector('#form-asignar-lead .field-error').textContent = data.errors.colaborador_id;
        }
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo asignar el lead.');
    }
  });
});
