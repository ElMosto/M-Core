/* ==========================================================================
   Agenda — calendario mensual (citas + tareas pendientes), invitaciones
   ========================================================================== */

function escapeHtmlAg(s) {
  const div = document.createElement('div');
  div.textContent = s == null ? '' : s;
  return div.innerHTML;
}

const MESES_LARGO = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

function fechaISO(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

let mesVisible = new Date(); // cualquier día dentro del mes que se está viendo
let diaSeleccionado = null;  // 'YYYY-MM-DD' o null
let agendaCache = { citas: [], tareas: [] };
let clientesCacheAgenda = [];

/** Lunes de la semana que contiene `d`. */
function lunesDeLaSemana(d) {
  const copia = new Date(d);
  const diff = (copia.getDay() + 6) % 7; // 0=lunes ... 6=domingo
  copia.setDate(copia.getDate() - diff);
  return copia;
}

async function cargarAgendaDelMes() {
  const primerDia = new Date(mesVisible.getFullYear(), mesVisible.getMonth(), 1);
  const ultimoDia = new Date(mesVisible.getFullYear(), mesVisible.getMonth() + 1, 0);
  const inicioGrid = lunesDeLaSemana(primerDia);
  const finGrid = new Date(lunesDeLaSemana(ultimoDia));
  finGrid.setDate(finGrid.getDate() + 6);

  document.getElementById('calendario-titulo').textContent =
    `${MESES_LARGO[mesVisible.getMonth()]} ${mesVisible.getFullYear()}`;

  try {
    const res = await fetch(`api/agenda.php?desde=${fechaISO(inicioGrid)}&hasta=${fechaISO(finGrid)}`, { cache: 'no-store' });
    if (!res.ok) throw new Error();
    agendaCache = await res.json();
  } catch (e) {
    agendaCache = { citas: [], tareas: [] };
    showToast('bad', 'No se pudo cargar la agenda', 'Intenta de nuevo en unos segundos.');
  }

  renderCalendario(inicioGrid, finGrid);
  if (diaSeleccionado) pintarPanelDia(diaSeleccionado);
}

function eventosDelDia(fechaStr) {
  return {
    citas: agendaCache.citas.filter(c => c.fecha === fechaStr),
    tareas: agendaCache.tareas.filter(t => t.fecha_fin === fechaStr),
  };
}

function renderCalendario(inicioGrid, finGrid) {
  const grid = document.getElementById('calendario-grid');
  const hoyStr = fechaISO(new Date());
  const celdas = [];

  const cursor = new Date(inicioGrid);
  while (cursor <= finGrid) {
    const fechaStr = fechaISO(cursor);
    const fueraDeMes = cursor.getMonth() !== mesVisible.getMonth();
    const { citas, tareas } = eventosDelDia(fechaStr);
    const tengoInvitacionPendiente = citas.some(c => c.mi_estado === 'invitado');

    let badges = '';
    if (citas.length) badges += `<span class="dia-badge citas">${citas.length} cita${citas.length > 1 ? 's' : ''}</span>`;
    if (tareas.length) badges += `<span class="dia-badge tareas">${tareas.length} tarea${tareas.length > 1 ? 's' : ''}</span>`;
    if (tengoInvitacionPendiente) badges += `<span class="dia-badge invitacion">Invitación</span>`;

    celdas.push(`
      <div class="calendario-dia ${fueraDeMes ? 'fuera-de-mes' : ''} ${fechaStr === hoyStr ? 'es-hoy' : ''} ${fechaStr === diaSeleccionado ? 'seleccionado' : ''}"
           data-fecha="${fechaStr}">
        <span class="dia-numero">${cursor.getDate()}</span>
        <div class="dia-badges">${badges}</div>
      </div>
    `);
    cursor.setDate(cursor.getDate() + 1);
  }

  grid.innerHTML = celdas.join('');
  grid.querySelectorAll('.calendario-dia').forEach(el => {
    el.addEventListener('click', () => {
      diaSeleccionado = el.getAttribute('data-fecha');
      grid.querySelectorAll('.calendario-dia').forEach(x => x.classList.remove('seleccionado'));
      el.classList.add('seleccionado');
      pintarPanelDia(diaSeleccionado);
    });
  });
}

function pintarPanelDia(fechaStr) {
  const panel = document.getElementById('panel-dia');
  const { citas, tareas } = eventosDelDia(fechaStr);
  const [anio, mes, dia] = fechaStr.split('-');
  document.getElementById('panel-dia-titulo').textContent = `${parseInt(dia, 10)} de ${MESES_LARGO[parseInt(mes, 10) - 1]}`;

  const body = document.getElementById('panel-dia-body');
  if (!citas.length && !tareas.length) {
    body.innerHTML = '<div class="hint">Sin citas ni tareas pendientes este día.</div>';
  } else {
    const filasCitas = citas.map(c => `
      <div class="evento-fila evento-cita" data-id="${c.id}" style="cursor:pointer">
        <span class="evento-hora">${c.hora_inicio}</span>
        <span class="evento-punto cita"></span>
        <div>
          <div>${escapeHtmlAg(c.titulo)} ${c.mi_estado === 'invitado' ? '<span class="badge text-bg-warning">Invitación pendiente</span>' : ''}</div>
          <div class="hint mb-0">${c.cliente_nombre ? `<i class="bi bi-person-vcard me-1"></i>${escapeHtmlAg(c.cliente_nombre)} · ` : ''}Organiza: ${escapeHtmlAg(c.creado_por_nombre)}</div>
        </div>
      </div>
    `).join('');
    const filasTareas = tareas.map(t => `
      <a class="evento-fila text-decoration-none text-reset" href="tareas.php?id=${t.proyecto_id}">
        <span class="evento-hora">&nbsp;</span>
        <span class="evento-punto tarea"></span>
        <div>
          <div>${escapeHtmlAg(t.titulo)} <span class="badge-prioridad ${t.prioridad}">${t.prioridad}</span></div>
          <div class="hint mb-0"><i class="bi bi-folder2 me-1"></i>${escapeHtmlAg(t.proyecto_nombre)} · ${t.asignado_nombre ? escapeHtmlAg(t.asignado_nombre) : 'Sin asignar'}</div>
        </div>
      </a>
    `).join('');
    body.innerHTML = filasCitas + filasTareas;

    body.querySelectorAll('.evento-cita').forEach(el => {
      el.addEventListener('click', () => verDetalleCita(el.getAttribute('data-id')));
    });
  }

  panel.style.display = 'block';
}

/* ==================== Detalle / cancelar cita ==================== */

async function verDetalleCita(id) {
  const body = document.getElementById('detalle-cita-body');
  body.innerHTML = '<div class="hint">Cargando…</div>';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleCita')).show();

  try {
    const res = await fetch(`api/citas.php?id=${id}`, { cache: 'no-store' });
    if (!res.ok) throw new Error();
    const c = await res.json();

    const ESTADO_BADGE = { aceptado: 'text-bg-success', invitado: 'text-bg-warning', rechazado: 'text-bg-secondary' };
    const ESTADO_LABEL = { aceptado: 'Aceptó', invitado: 'Sin responder', rechazado: 'Rechazó' };

    body.innerHTML = `
      <h5 class="font-display">${escapeHtmlAg(c.titulo)}</h5>
      <div class="hint mb-2"><i class="bi bi-calendar-event me-1"></i>${c.fecha} · ${c.hora_inicio}${c.hora_fin ? ' - ' + c.hora_fin : ''}</div>
      ${c.cliente_nombre ? `<div class="mb-2"><i class="bi bi-person-vcard me-1"></i>${escapeHtmlAg(c.cliente_nombre)}</div>` : ''}
      ${c.descripcion ? `<p>${escapeHtmlAg(c.descripcion)}</p>` : ''}
      <div class="hint mb-1">Organiza: ${escapeHtmlAg(c.creado_por_nombre)}</div>
      <div class="mb-2">
        ${c.participantes.map(p => `<span class="badge ${ESTADO_BADGE[p.estado]} me-1 mb-1">${escapeHtmlAg(p.nombre)} · ${ESTADO_LABEL[p.estado]}</span>`).join('')}
      </div>
      <div id="detalle-cita-acciones"></div>
    `;

    const acciones = document.getElementById('detalle-cita-acciones');
    const miParticipacion = c.participantes.find(p => Number(p.usuario_id) === TIMBRA_USUARIO_ID_AGENDA);
    if (miParticipacion && miParticipacion.estado === 'invitado') {
      acciones.innerHTML = `
        <button class="btn btn-timbra btn-sm" id="btn-aceptar-invitacion-detalle">Aceptar</button>
        <button class="btn btn-outline-secondary btn-sm" id="btn-rechazar-invitacion-detalle">Rechazar</button>
      `;
      document.getElementById('btn-aceptar-invitacion-detalle').addEventListener('click', () => responderInvitacion(c.id, 'aceptado', true));
      document.getElementById('btn-rechazar-invitacion-detalle').addEventListener('click', () => responderInvitacion(c.id, 'rechazado', true));
    } else if (Number(c.creado_por) === TIMBRA_USUARIO_ID_AGENDA || TIMBRA_ROL_ACTUAL_AGENDA === 'admin') {
      acciones.innerHTML = `<button class="btn btn-outline-secondary btn-sm" id="btn-cancelar-cita">Cancelar cita</button>`;
      document.getElementById('btn-cancelar-cita').addEventListener('click', () => cancelarCita(c.id));
    }
  } catch (e) {
    body.innerHTML = '<div class="hint">No se pudo cargar el detalle de esta cita.</div>';
  }
}

async function cancelarCita(id) {
  if (!confirm('¿Cancelar esta cita?')) return;
  try {
    const res = await fetch('api/citas.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'cancelar', id })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', 'Cita cancelada', data.message);
      bootstrap.Modal.getInstance(document.getElementById('modalDetalleCita')).hide();
      cargarAgendaDelMes();
    } else {
      showToast('bad', 'No se pudo cancelar', data.message);
    }
  } catch (e) {
    showToast('bad', 'Error de conexión', 'Intenta de nuevo.');
  }
}

/* ==================== Invitaciones pendientes ==================== */

async function cargarInvitacionesPendientes() {
  const wrap = document.getElementById('invitaciones-pendientes-wrap');
  let invitaciones;
  try {
    const res = await fetch('api/citas.php?invitaciones_pendientes=1', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    invitaciones = await res.json();
  } catch (e) {
    return;
  }

  if (!invitaciones.length) {
    wrap.style.display = 'none';
    return;
  }

  wrap.style.display = 'block';
  document.getElementById('lista-invitaciones').innerHTML = invitaciones.map(c => `
    <div class="evento-fila">
      <span class="evento-hora">${c.fecha.slice(5)}</span>
      <span class="evento-punto cita"></span>
      <div class="flex-grow-1">
        <div>${escapeHtmlAg(c.titulo)} <span class="hint mb-0">· ${c.hora_inicio}${c.cliente_nombre ? ' · ' + escapeHtmlAg(c.cliente_nombre) : ''}</span></div>
        <div class="hint mb-0">Invitó: ${escapeHtmlAg(c.creado_por_nombre)}</div>
      </div>
      <div>
        <button class="btn btn-sm btn-timbra btn-aceptar-inv" data-id="${c.id}">Aceptar</button>
        <button class="btn btn-sm btn-outline-secondary btn-rechazar-inv" data-id="${c.id}">Rechazar</button>
      </div>
    </div>
  `).join('');

  document.querySelectorAll('.btn-aceptar-inv').forEach(btn => {
    btn.addEventListener('click', () => responderInvitacion(btn.getAttribute('data-id'), 'aceptado'));
  });
  document.querySelectorAll('.btn-rechazar-inv').forEach(btn => {
    btn.addEventListener('click', () => responderInvitacion(btn.getAttribute('data-id'), 'rechazado'));
  });
}

async function responderInvitacion(citaId, respuesta, esDesdeDetalle) {
  try {
    const res = await fetch('api/citas.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'responder_invitacion', cita_id: citaId, respuesta })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', respuesta === 'aceptado' ? 'Invitación aceptada' : 'Invitación rechazada', data.message);
      if (esDesdeDetalle) bootstrap.Modal.getInstance(document.getElementById('modalDetalleCita'))?.hide();
      cargarInvitacionesPendientes();
      cargarAgendaDelMes();
    } else {
      showToast('bad', 'No se pudo responder', data.message);
    }
  } catch (e) {
    showToast('bad', 'Error de conexión', 'Intenta de nuevo.');
  }
}

/* ==================== Nueva cita ==================== */

async function cargarClientesParaCita() {
  const select = document.getElementById('c_cliente');
  try {
    const res = await fetch('api/clientes.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    clientesCacheAgenda = await res.json();
    select.innerHTML = '<option value="">Sin cliente</option>' +
      clientesCacheAgenda.map(c => `<option value="${c.id}">${escapeHtmlAg(c.nombre)}</option>`).join('');
  } catch (e) {
    select.innerHTML = '<option value="">Sin cliente</option>';
  }
}

async function cargarInvitablesParaCita() {
  const cont = document.getElementById('c_invitados_checklist');
  try {
    const res = await fetch('api/citas.php?usuarios_invitables=1', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    const usuarios = await res.json();
    cont.innerHTML = usuarios.length
      ? usuarios.map(u => `
        <div class="form-check">
          <input type="checkbox" class="form-check-input" id="inv_${u.id}" value="${u.id}">
          <label class="form-check-label" for="inv_${u.id}">${escapeHtmlAg(u.nombre)} <span class="hint mb-0">(${escapeHtmlAg(u.rol_nombre || '')})</span></label>
        </div>
      `).join('')
      : '<div class="hint">No hay más personas activas para invitar.</div>';
  } catch (e) {
    cont.innerHTML = '<div class="hint">No se pudo cargar la lista de personas.</div>';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  cargarAgendaDelMes();
  cargarInvitacionesPendientes();

  document.getElementById('btn-mes-prev').addEventListener('click', () => {
    mesVisible = new Date(mesVisible.getFullYear(), mesVisible.getMonth() - 1, 1);
    cargarAgendaDelMes();
  });
  document.getElementById('btn-mes-next').addEventListener('click', () => {
    mesVisible = new Date(mesVisible.getFullYear(), mesVisible.getMonth() + 1, 1);
    cargarAgendaDelMes();
  });
  document.getElementById('btn-mes-hoy').addEventListener('click', () => {
    mesVisible = new Date();
    diaSeleccionado = fechaISO(new Date());
    cargarAgendaDelMes();
  });

  document.getElementById('modalCita').addEventListener('show.bs.modal', () => {
    const form = document.getElementById('form-cita');
    form.reset();
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');
    if (diaSeleccionado) document.getElementById('c_fecha').value = diaSeleccionado;
    cargarClientesParaCita();
    cargarInvitablesParaCita();
  });

  document.getElementById('form-cita').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const invitados = Array.from(document.querySelectorAll('#c_invitados_checklist input:checked')).map(i => parseInt(i.value, 10));
    const payload = {
      accion: 'crear',
      titulo: document.getElementById('c_titulo').value,
      descripcion: document.getElementById('c_descripcion').value,
      fecha: document.getElementById('c_fecha').value,
      hora_inicio: document.getElementById('c_hora_inicio').value,
      hora_fin: document.getElementById('c_hora_fin').value,
      cliente_id: document.getElementById('c_cliente').value || null,
      invitados,
    };

    try {
      const res = await fetch('api/citas.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Cita registrada', data.message);
        bootstrap.Modal.getInstance(document.getElementById('modalCita')).hide();
        diaSeleccionado = payload.fecha;
        cargarAgendaDelMes();
      } else {
        showToast('bad', 'No se pudo registrar la cita', data.message);
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
      showToast('bad', 'Error de conexión', 'No se pudo registrar la cita.');
    }
  });
});
