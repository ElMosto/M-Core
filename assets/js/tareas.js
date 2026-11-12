/* ==========================================================================
   Tareas — dos vistas:
   - TIMBRA_MODO === 'lista'   -> tabla de proyectos (tareas.php)
   - TIMBRA_MODO === 'detalle' -> etapas + tablero kanban de UN proyecto (tareas.php?id=)
   ========================================================================== */

function escapeHtml(s) {
  const div = document.createElement('div');
  div.textContent = s == null ? '' : s;
  return div.innerHTML;
}

/* ==================== VISTA: LISTA DE PROYECTOS ==================== */

let proyectosListaCache = [];

async function cargarTablaProyectos() {
  const tbody = document.getElementById('tabla-proyectos-body');
  let proyectos;
  try {
    const res = await fetch('api/proyectos.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    proyectos = await res.json();
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="6" class="hint">No se pudieron cargar los proyectos.</td></tr>';
    return;
  }

  proyectosListaCache = proyectos;

  if (!proyectos.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="hint">Aún no hay proyectos visibles para ti.</td></tr>';
    return;
  }

  const puedeAdministrar = !!document.getElementById('modalProyecto');

  tbody.innerHTML = proyectos.map(p => {
    const fechas = (p.fecha_inicio || p.fecha_fin_tentativa)
      ? `${p.fecha_inicio || '¿?'} → ${p.fecha_fin_tentativa || '¿?'}`
      : '<span class="hint mb-0">Sin definir</span>';
    const pct = p.total_tareas > 0 ? Math.round((p.tareas_completadas / p.total_tareas) * 100) : 0;
    const tipoBadge = p.tipo === 'externo'
      ? `<span class="badge-prioridad alta">Externo</span><div class="hint mb-0 mono">$${Number(p.costo).toFixed(2)}</div><div class="hint mb-0"><i class="bi bi-person-vcard me-1"></i>${escapeHtml(p.cliente_nombre || '—')}</div>`
      : `<span class="badge-prioridad baja">Interno</span>`;
    return `
      <tr>
        <td><strong>${escapeHtml(p.nombre)}</strong>${p.descripcion ? `<div class="hint mb-0">${escapeHtml(p.descripcion)}</div>` : ''}</td>
        <td>${tipoBadge}</td>
        <td class="mono" style="font-size:.82rem">${fechas}</td>
        <td style="min-width:140px">
          <div class="progress progress-timbra mb-1"><div class="progress-bar" style="width:${pct}%"></div></div>
          <span class="hint mb-0">${p.tareas_completadas}/${p.total_tareas} tareas · ${p.total_etapas} etapa(s)</span>
        </td>
        <td>${escapeHtml(p.creado_por_nombre)}</td>
        <td class="text-end">
          <a href="tareas.php?id=${p.id}" class="btn btn-sm btn-outline-timbra">Ver proyecto</a>
          ${puedeAdministrar ? `
            <button class="btn btn-sm btn-outline-secondary btn-editar-proyecto" data-id="${p.id}" title="Editar"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-secondary btn-eliminar-proyecto" data-id="${p.id}" title="Eliminar"><i class="bi bi-trash"></i></button>
          ` : ''}
        </td>
      </tr>
    `;
  }).join('');

  if (!puedeAdministrar) return;

  tbody.querySelectorAll('.btn-editar-proyecto').forEach(btn => {
    btn.addEventListener('click', () => abrirModalProyectoParaEditar(parseInt(btn.getAttribute('data-id'), 10)));
  });
  tbody.querySelectorAll('.btn-eliminar-proyecto').forEach(btn => {
    btn.addEventListener('click', () => eliminarProyecto(parseInt(btn.getAttribute('data-id'), 10)));
  });
}

async function eliminarProyecto(id) {
  const proyecto = proyectosListaCache.find(p => p.id === id);
  if (!confirm(`¿Eliminar el proyecto "${proyecto ? proyecto.nombre : ''}"? Sus etapas y tareas dejarán de verse, pero no se borran del historial.`)) {
    return;
  }
  try {
    const res = await fetch('api/proyectos.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'eliminar', id })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', 'Proyecto eliminado', data.message);
      cargarTablaProyectos();
    } else {
      showToast('bad', 'No se pudo eliminar', data.message);
    }
  } catch (e) {
    showToast('bad', 'Error de conexión', 'No se pudo eliminar el proyecto.');
  }
}

async function cargarNegociosParaSelectProyecto() {
  const sel = document.getElementById('p_negocio');
  if (!sel) return;
  try {
    const res = await fetch('api/negocios.php', { cache: 'no-store' });
    const negocios = await res.json();
    sel.innerHTML = negocios.map(n => `<option value="${n.id}">${escapeHtml(n.nombre)}</option>`).join('');
  } catch (e) { sel.innerHTML = ''; }
}

function resetearModalProyecto() {
  const form = document.getElementById('form-proyecto');
  form.reset();
  cargarNegociosParaSelectProyecto();
  document.getElementById('p_accion').value = 'crear';
  document.getElementById('p_id').value = '';
  document.getElementById('modalProyectoTitulo').textContent = 'Nuevo proyecto';
  document.getElementById('modalProyectoBoton').textContent = 'Crear proyecto';
  document.getElementById('p_costo_wrap').style.display = 'none';
  document.getElementById('p_cliente_wrap').style.display = 'none';
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  form.querySelectorAll('.field-error').forEach(el => el.textContent = '');
}

async function abrirModalProyectoParaEditar(id) {
  const p = proyectosListaCache.find(x => Number(x.id) === id);
  if (!p) return;

  resetearModalProyecto();
  document.getElementById('p_accion').value = 'editar';
  document.getElementById('p_id').value = p.id;
  document.getElementById('modalProyectoTitulo').textContent = 'Editar proyecto';
  document.getElementById('modalProyectoBoton').textContent = 'Guardar cambios';

  document.getElementById('p_nombre').value = p.nombre;

  await cargarNegociosParaSelectProyecto();
  const selNeg = document.getElementById('p_negocio');
  if (selNeg && p.negocio_id) {
    selNeg.value = p.negocio_id;
  }
  document.getElementById('p_descripcion').value = p.descripcion || '';
  document.getElementById('p_fecha_inicio').value = p.fecha_inicio || '';
  document.getElementById('p_fecha_fin').value = p.fecha_fin_tentativa || '';

  const radio = document.getElementById(p.tipo === 'externo' ? 'p_tipo_externo' : 'p_tipo_interno');
  radio.checked = true;

  if (p.tipo === 'externo') {
    document.getElementById('p_costo_wrap').style.display = 'block';
    document.getElementById('p_costo').required = true;
    document.getElementById('p_costo').value = p.costo;
    document.getElementById('p_cliente_wrap').style.display = 'block';
    document.getElementById('p_cliente').required = true;
    const select = document.getElementById('p_cliente');
    try {
      const res = await fetch('api/clientes.php', { cache: 'no-store' });
      const clientes = await res.json();
      select.innerHTML = clientes.map(c => `<option value="${c.id}" ${c.id === p.cliente_id ? 'selected' : ''}>${escapeHtml(c.nombre)}</option>`).join('');
    } catch (e) { /* deja el select como esté */ }
  }

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalProyecto')).show();
}

function iniciarVistaLista() {
  cargarTablaProyectos();

  const formProyecto = document.getElementById('form-proyecto');
  if (!formProyecto) return; // colaborador/contador no ven el botón ni el modal

  (async function cargarNegociosProyecto() {
    const sel = document.getElementById('p_negocio');
    if (!sel) return;
    try {
      const res = await fetch('api/negocios.php', { cache: 'no-store' });
      const negocios = await res.json();
      sel.innerHTML = negocios.map(n => `<option value="${n.id}">${escapeHtml(n.nombre)}</option>`).join('');
    } catch (e) { sel.innerHTML = ''; }
  })();

  document.getElementById('btn-nuevo-proyecto').addEventListener('click', resetearModalProyecto);

  // Mostrar/ocultar y (des)requerir el campo de costo y cliente según el tipo elegido.
  const costoWrap = document.getElementById('p_costo_wrap');
  const costoInput = document.getElementById('p_costo');
  const clienteWrap = document.getElementById('p_cliente_wrap');
  const clienteSelect = document.getElementById('p_cliente');
  let clientesCargados = false;

  async function cargarClientesParaSelect() {
    if (clientesCargados) return;
    try {
      const res = await fetch('api/clientes.php', { cache: 'no-store' });
      if (!res.ok) throw new Error();
      const clientes = await res.json();
      clienteSelect.innerHTML = clientes.length
        ? '<option value="" disabled selected>Selecciona un cliente…</option>' +
          clientes.map(c => `<option value="${c.id}">${escapeHtml(c.nombre)}</option>`).join('')
        : '<option value="">No hay clientes registrados todavía</option>';
      clientesCargados = true;
    } catch (e) {
      clienteSelect.innerHTML = '<option value="">No se pudo cargar la lista</option>';
    }
  }

  formProyecto.querySelectorAll('input[name="tipo"]').forEach(radio => {
    radio.addEventListener('change', () => {
      const esExterno = formProyecto.querySelector('input[name="tipo"]:checked').value === 'externo';
      costoWrap.style.display = esExterno ? 'block' : 'none';
      costoInput.required = esExterno;
      clienteWrap.style.display = esExterno ? 'block' : 'none';
      clienteSelect.required = esExterno;
      if (!esExterno) { costoInput.value = ''; clienteSelect.value = ''; }
      else cargarClientesParaSelect();
    });
  });

  formProyecto.addEventListener('submit', async (e) => {
    e.preventDefault();
    formProyecto.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    formProyecto.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const esEdicion = document.getElementById('p_accion').value === 'editar';
    const payload = Object.fromEntries(new FormData(formProyecto).entries());
    if (payload.tipo !== 'externo') delete payload.costo;

    try {
      const res = await fetch('api/proyectos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        if (esEdicion) {
          showToast('ok', 'Proyecto actualizado', data.message);
          bootstrap.Modal.getInstance(document.getElementById('modalProyecto')).hide();
          cargarTablaProyectos();
        } else {
          window.location.href = `tareas.php?id=${data.id}`;
        }
      } else {
        showToast('bad', esEdicion ? 'No se pudo guardar el proyecto' : 'No se pudo crear el proyecto', data.message);
        Object.entries(data.errors || {}).forEach(([campo, msg]) => {
          const input = formProyecto.querySelector(`[name="${campo}"]`);
          if (input) {
            input.classList.add('is-invalid');
            const err = input.closest('.mb-3')?.querySelector('.field-error');
            if (err) err.textContent = msg;
          }
        });
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo guardar el proyecto.');
    }
  });
}

/* ==================== VISTA: DETALLE DE PROYECTO (etapas + kanban) ==================== */

let etapasCache = [];
let etapaPreseleccionada = null;
let tareasCache = [];

function resetearModalTarea() {
  const form = document.getElementById('form-tarea');
  form.reset();
  document.getElementById('t_accion').value = 'crear';
  document.getElementById('t_id').value = '';
  document.getElementById('modalTareaTitulo').textContent = 'Nueva tarea';
  document.getElementById('modalTareaBoton').textContent = 'Crear tarea';
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  form.querySelectorAll('.field-error').forEach(el => el.textContent = '');
}

async function abrirModalTareaParaEditar(id) {
  const t = tareasCache.find(x => Number(x.id) === id);
  if (!t) return;

  resetearModalTarea();
  document.getElementById('t_accion').value = 'editar';
  document.getElementById('t_id').value = t.id;
  document.getElementById('modalTareaTitulo').textContent = 'Editar tarea';
  document.getElementById('modalTareaBoton').textContent = 'Guardar cambios';

  etapaPreseleccionada = t.etapa_id;
  sincronizarSelectEtapaTarea();

  document.getElementById('t_titulo').value = t.titulo;
  document.getElementById('t_descripcion').value = t.descripcion || '';
  document.getElementById('t_prioridad').value = t.prioridad;
  document.getElementById('t_fecha_inicio').value = t.fecha_inicio || '';
  document.getElementById('t_fecha_fin').value = t.fecha_fin || '';

  await cargarUsuariosAsignables();
  if (t.asignado_a) document.getElementById('t_asignado').value = t.asignado_a;

  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTarea')).show();
}

async function eliminarTarea(id) {
  const t = tareasCache.find(x => Number(x.id) === id);
  if (!confirm(`¿Eliminar la tarea "${t ? t.titulo : ''}"?`)) return;
  try {
    const res = await fetch('api/tareas.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'eliminar', id })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', 'Tarea eliminada', data.message);
      cargarEtapasYTareas();
    } else {
      showToast('bad', 'No se pudo eliminar la tarea', data.message);
    }
  } catch (e) {
    showToast('bad', 'Error de conexión', 'No se pudo eliminar la tarea.');
  }
}

async function cargarUsuariosAsignables() {
  const select = document.getElementById('t_asignado');
  if (!select) return;
  const valorPrevio = select.value;
  try {
    const res = await fetch('api/usuarios-asignables.php', { cache: 'no-store' });
    if (!res.ok) throw new Error('respuesta no válida (' + res.status + ')');
    const usuarios = await res.json();
    select.innerHTML = '<option value="" disabled selected>Selecciona a alguien…</option>' +
      usuarios.map(u => `<option value="${u.id}">${escapeHtml(u.nombre)} (${escapeHtml(u.rol_nombre || u.rol)})</option>`).join('');
    if (valorPrevio) select.value = valorPrevio;
  } catch (e) {
    select.innerHTML = '<option value="" disabled selected>No se pudo cargar la lista</option>';
    showToast('bad', 'No se pudo cargar la lista de personas', 'Vuelve a intentar en unos segundos.');
  }
}

function sincronizarSelectEtapaTarea() {
  const select = document.getElementById('t_etapa');
  if (!select) return;
  select.innerHTML = etapasCache.map(e =>
    `<option value="${e.id}" ${e.id === etapaPreseleccionada ? 'selected' : ''}>${escapeHtml(e.nombre)}</option>`
  ).join('');
}

function resetearModalEtapa() {
  const form = document.getElementById('form-etapa');
  form.reset();
  document.getElementById('e_accion').value = 'crear';
  document.getElementById('e_id').value = '';
  document.getElementById('modalEtapaTitulo').textContent = 'Nueva etapa';
  document.getElementById('modalEtapaBoton').textContent = 'Crear etapa';
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  form.querySelectorAll('.field-error').forEach(el => el.textContent = '');
}

function abrirModalEtapaParaEditar(etapa) {
  resetearModalEtapa();
  document.getElementById('e_accion').value = 'editar';
  document.getElementById('e_id').value = etapa.id;
  document.getElementById('e_nombre').value = etapa.nombre;
  document.getElementById('modalEtapaTitulo').textContent = 'Editar etapa';
  document.getElementById('modalEtapaBoton').textContent = 'Guardar cambios';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEtapa')).show();
}

async function eliminarEtapa(etapa) {
  if (!confirm(`¿Eliminar la etapa "${etapa.nombre}"? Solo se puede si ya no tiene tareas.`)) return;
  try {
    const res = await fetch('api/etapas.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'eliminar', id: etapa.id })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', 'Etapa eliminada', data.message);
      cargarEtapasYTareas();
    } else {
      showToast('bad', 'No se pudo eliminar la etapa', data.message);
    }
  } catch (e) {
    showToast('bad', 'Error de conexión', 'No se pudo eliminar la etapa.');
  }
}

async function cargarEtapasYTareas() {
  const contenedor = document.getElementById('etapas-container');
  let etapas, tareas;
  try {
    const [resEtapas, resTareas] = await Promise.all([
      fetch(`api/etapas.php?proyecto_id=${TIMBRA_PROYECTO_ID}`, { cache: 'no-store' }),
      fetch(`api/tareas.php?proyecto_id=${TIMBRA_PROYECTO_ID}`, { cache: 'no-store' })
    ]);
    if (!resEtapas.ok || !resTareas.ok) throw new Error();
    etapas = await resEtapas.json();
    tareas = await resTareas.json();
  } catch (e) {
    contenedor.innerHTML = '<div class="hint">No se pudieron cargar las etapas de este proyecto.</div>';
    return;
  }

  etapasCache = etapas;
  tareasCache = tareas;
  sincronizarSelectEtapaTarea();

  if (!etapas.length) {
    contenedor.innerHTML = TIMBRA_PUEDE_CREAR_TAREA_AQUI
      ? '<div class="hint">Este proyecto todavía no tiene etapas — crea la primera con el botón "Nueva etapa".</div>'
      : '<div class="hint">Este proyecto todavía no tiene etapas ni tareas.</div>';
    return;
  }

  const tpl = document.getElementById('tpl-etapa');
  contenedor.innerHTML = '';

  etapas.forEach(etapa => {
    const nodo = tpl.content.cloneNode(true);
    const card = nodo.querySelector('.etapa-card');
    card.dataset.etapaId = etapa.id;
    nodo.querySelector('.etapa-nombre').textContent = etapa.nombre;
    nodo.querySelector('.etapa-avance').textContent = `${etapa.tareas_completadas}/${etapa.total_tareas} completadas`;

    const btnNuevaTarea = nodo.querySelector('.btn-nueva-tarea-etapa');
    if (btnNuevaTarea) {
      btnNuevaTarea.addEventListener('click', () => {
        resetearModalTarea();
        etapaPreseleccionada = etapa.id;
        sincronizarSelectEtapaTarea();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTarea')).show();
      });
    }

    const btnEditarEtapa = nodo.querySelector('.btn-editar-etapa');
    if (btnEditarEtapa) {
      btnEditarEtapa.addEventListener('click', () => abrirModalEtapaParaEditar(etapa));
    }
    const btnEliminarEtapa = nodo.querySelector('.btn-eliminar-etapa');
    if (btnEliminarEtapa) {
      btnEliminarEtapa.addEventListener('click', () => eliminarEtapa(etapa));
    }

    const tareasDeEtapa = tareas.filter(t => t.etapa_id === etapa.id);
    pintarKanbanDeEtapa(card, tareasDeEtapa);

    contenedor.appendChild(nodo);
  });
}

function pintarKanbanDeEtapa(card, tareas) {
  const columnas = { pendiente: [], en_progreso: [], completada: [] };
  tareas.forEach(t => { (columnas[t.estado] || columnas.pendiente).push(t); });

  Object.entries(columnas).forEach(([estado, lista]) => {
    card.querySelector(`.count-${estado}`).textContent = lista.length;
    const col = card.querySelector(`.col-${estado}`);
    col.innerHTML = lista.length ? lista.map(tarjetaHtml).join('') : '<div class="kanban-empty">Sin tareas aquí.</div>';
  });

  card.querySelectorAll('.select-estado-tarea').forEach(sel => {
    sel.addEventListener('change', () => cambiarEstadoDirecto(sel.getAttribute('data-id'), sel.value));
  });
  card.querySelectorAll('.select-proponer-estado').forEach(sel => {
    sel.addEventListener('change', () => proponerCambioEstado(sel.getAttribute('data-id'), sel.value));
  });
  card.querySelectorAll('.btn-aprobar-estado').forEach(btn => {
    btn.addEventListener('click', () => resolverPropuesta(btn.getAttribute('data-id'), 'aprobar_cambio_estado'));
  });
  card.querySelectorAll('.btn-rechazar-estado').forEach(btn => {
    btn.addEventListener('click', () => resolverPropuesta(btn.getAttribute('data-id'), 'rechazar_cambio_estado'));
  });
  card.querySelectorAll('.btn-editar-tarea').forEach(btn => {
    btn.addEventListener('click', () => abrirModalTareaParaEditar(parseInt(btn.getAttribute('data-id'), 10)));
  });
  card.querySelectorAll('.btn-eliminar-tarea').forEach(btn => {
    btn.addEventListener('click', () => eliminarTarea(parseInt(btn.getAttribute('data-id'), 10)));
  });
}

const ESTADO_LABEL = { pendiente: 'Pendiente', en_progreso: 'En progreso', completada: 'Completada' };

function tarjetaHtml(t) {
  const hoy = new Date().toISOString().slice(0, 10);
  const vencida = t.fecha_fin && t.fecha_fin < hoy && t.estado !== 'completada';
  const esAprobador = TIMBRA_PUEDE_CREAR_TAREA_AQUI; // admin o líder dueño de este proyecto
  const esAsignado = Number(t.asignado_a) === Number(TIMBRA_USUARIO_ID);
  const tienePropuesta = !!t.estado_propuesto;

  let controlEstado;
  if (tienePropuesta) {
    if (esAprobador) {
      controlEstado = `
        <div class="task-pending w-100">
          <span><i class="bi bi-hourglass-split me-1"></i>${escapeHtml(t.propuesto_por_nombre)} propone: <strong>${ESTADO_LABEL[t.estado_propuesto]}</strong></span>
          <button class="btn btn-timbra btn-aprobar-estado ms-auto" data-id="${t.id}">Aprobar</button>
          <button class="btn btn-outline-secondary btn-rechazar-estado" data-id="${t.id}">Rechazar</button>
        </div>`;
    } else {
      controlEstado = `
        <div class="task-pending w-100">
          <span><i class="bi bi-hourglass-split me-1"></i>Pendiente de aprobación: <strong>${ESTADO_LABEL[t.estado_propuesto]}</strong></span>
        </div>`;
    }
  } else if (esAprobador) {
    controlEstado = `
      <select class="form-select form-select-sm select-estado-tarea ms-auto" data-id="${t.id}" style="width:auto">
        <option value="pendiente" ${t.estado === 'pendiente' ? 'selected' : ''}>Pendiente</option>
        <option value="en_progreso" ${t.estado === 'en_progreso' ? 'selected' : ''}>En progreso</option>
        <option value="completada" ${t.estado === 'completada' ? 'selected' : ''}>Completada</option>
      </select>`;
  } else if (esAsignado) {
    controlEstado = `
      <select class="form-select form-select-sm select-proponer-estado ms-auto" data-id="${t.id}" style="width:auto">
        <option value="pendiente" ${t.estado === 'pendiente' ? 'selected' : ''}>Pendiente</option>
        <option value="en_progreso" ${t.estado === 'en_progreso' ? 'selected' : ''}>En progreso</option>
        <option value="completada" ${t.estado === 'completada' ? 'selected' : ''}>Completada</option>
      </select>`;
  } else {
    controlEstado = `<span class="task-readonly-estado ms-auto">${ESTADO_LABEL[t.estado]}</span>`;
  }

  return `
    <div class="task-card">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div class="task-title">${escapeHtml(t.titulo)}</div>
        <div class="d-flex align-items-center gap-1">
          <span class="badge-prioridad ${t.prioridad}">${t.prioridad}</span>
          ${esAprobador ? `
            <button class="btn btn-sm btn-outline-secondary btn-editar-tarea py-0 px-1" data-id="${t.id}" title="Editar"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-secondary btn-eliminar-tarea py-0 px-1" data-id="${t.id}" title="Eliminar"><i class="bi bi-trash"></i></button>
          ` : ''}
        </div>
      </div>
      ${t.descripcion ? `<div class="task-desc">${escapeHtml(t.descripcion)}</div>` : ''}
      <div class="task-meta">
        <span class="task-assignee"><i class="bi bi-person"></i> ${t.asignado_nombre ? escapeHtml(t.asignado_nombre) : 'Sin asignar'}</span>
        <span class="task-due ${vencida ? 'overdue' : ''}"><i class="bi bi-calendar-event"></i> ${t.fecha_inicio || '¿?'} → ${t.fecha_fin || '¿?'}</span>
        ${controlEstado}
      </div>
    </div>
  `;
}

async function cambiarEstadoDirecto(id, estado) {
  try {
    const res = await fetch('api/tareas.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'cambiar_estado', id, estado })
    });
    const data = await res.json();
    if (!data.success) { showToast('bad', 'No se pudo actualizar', data.message); }
    cargarEtapasYTareas();
  } catch (e) {
    showToast('bad', 'Error de conexión', 'No se pudo actualizar la tarea.');
  }
}

async function proponerCambioEstado(id, estado) {
  try {
    const res = await fetch('api/tareas.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'solicitar_cambio_estado', id, estado })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', 'Cambio propuesto', 'Queda pendiente de que tu líder o un admin lo apruebe.');
    } else {
      showToast('bad', 'No se pudo proponer el cambio', data.message);
    }
    cargarEtapasYTareas();
  } catch (e) {
    showToast('bad', 'Error de conexión', 'No se pudo proponer el cambio.');
  }
}

async function resolverPropuesta(id, accion) {
  try {
    const res = await fetch('api/tareas.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion, id })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', accion === 'aprobar_cambio_estado' ? 'Cambio aprobado' : 'Cambio rechazado', data.message);
    } else {
      showToast('bad', 'No se pudo resolver la propuesta', data.message);
    }
    cargarEtapasYTareas();
  } catch (e) {
    showToast('bad', 'Error de conexión', 'No se pudo resolver la propuesta.');
  }
}

/* ---- Costos extra (solo proyectos externos) ---- */

async function cargarCostosExtra() {
  const lista = document.getElementById('lista-costos-extra');
  if (!lista) return; // proyecto interno: esta sección ni se renderiza

  let costos;
  try {
    const res = await fetch(`api/costos-extra.php?proyecto_id=${TIMBRA_PROYECTO_ID}`, { cache: 'no-store' });
    if (!res.ok) throw new Error();
    costos = await res.json();
  } catch (e) {
    lista.innerHTML = '<div class="hint">No se pudieron cargar los costos extra.</div>';
    return;
  }

  const suma = costos.reduce((acc, c) => acc + Number(c.monto), 0);
  document.getElementById('costos-extra-suma').textContent = `$${suma.toFixed(2)}`;
  document.getElementById('costos-total').textContent = `$${(Number(TIMBRA_COSTO_BASE) + suma).toFixed(2)}`;

  if (!costos.length) {
    lista.innerHTML = '<div class="hint">Sin costos extra todavía.</div>';
    return;
  }

  lista.innerHTML = `
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Descripción</th><th>Monto</th><th>Agregado por</th><th>Fecha</th></tr></thead>
      <tbody>
        ${costos.map(c => `
          <tr>
            <td>${escapeHtml(c.descripcion)}</td>
            <td class="mono">$${Number(c.monto).toFixed(2)}</td>
            <td>${escapeHtml(c.creado_por_nombre)}</td>
            <td class="hint mb-0">${c.created_at.slice(0, 10)}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

function wireFormCostoExtra() {
  const form = document.getElementById('form-costo-extra');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const payload = Object.fromEntries(new FormData(form).entries());
    try {
      const res = await fetch('api/costos-extra.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Costo extra agregado', data.message);
        form.reset();
        bootstrap.Modal.getInstance(document.getElementById('modalCostoExtra')).hide();
        cargarCostosExtra();
      } else {
        showToast('bad', 'No se pudo agregar el costo extra', data.message);
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
      showToast('bad', 'Error de conexión', 'No se pudo agregar el costo extra.');
    }
  });
}

/* ---- Abonos (solo proyectos externos) ---- */

async function cargarAbonos() {
  const lista = document.getElementById('lista-abonos');
  if (!lista) return; // proyecto interno: esta sección ni se renderiza

  let data;
  try {
    const res = await fetch(`api/proyecto-abonos.php?proyecto_id=${TIMBRA_PROYECTO_ID}`, { cache: 'no-store' });
    if (!res.ok) throw new Error();
    data = await res.json();
  } catch (e) {
    lista.innerHTML = '<div class="hint">No se pudieron cargar los abonos.</div>';
    return;
  }

  document.getElementById('abonos-total-proyecto').textContent = `$${Number(data.total_proyecto).toFixed(2)}`;
  document.getElementById('abonos-total-abonado').textContent = `$${Number(data.total_abonado).toFixed(2)}`;
  const saldoEl = document.getElementById('abonos-saldo');
  saldoEl.textContent = `$${Number(data.saldo).toFixed(2)}`;
  saldoEl.style.color = data.saldo <= 0 ? 'var(--brand-dark)' : 'var(--danger)';

  const pct = data.total_proyecto > 0 ? Math.min(100, Math.round((data.total_abonado / data.total_proyecto) * 100)) : 0;
  const barra = document.getElementById('abonos-barra');
  barra.style.width = `${pct}%`;
  barra.textContent = `${pct}%`;

  const puedeGestionar = !!document.getElementById('form-abono');

  if (!data.abonos.length) {
    lista.innerHTML = '<div class="hint">Sin abonos registrados todavía.</div>';
    return;
  }

  lista.innerHTML = `
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Fecha</th><th>Monto</th><th>Método</th><th>Registró</th><th>Notas</th>${puedeGestionar ? '<th></th>' : ''}</tr></thead>
      <tbody>
        ${data.abonos.map(a => `
          <tr>
            <td class="mono">${a.fecha}</td>
            <td class="mono">$${Number(a.monto).toFixed(2)}</td>
            <td>${a.metodo_pago ? escapeHtml(a.metodo_pago) : '<span class="hint mb-0">—</span>'}</td>
            <td>${escapeHtml(a.registrado_por_nombre)}</td>
            <td class="hint mb-0">${a.notas ? escapeHtml(a.notas) : '—'}</td>
            ${puedeGestionar ? `<td class="text-end"><button class="btn btn-sm btn-outline-secondary btn-eliminar-abono" data-id="${a.id}" title="Eliminar"><i class="bi bi-trash"></i></button></td>` : ''}
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;

  lista.querySelectorAll('.btn-eliminar-abono').forEach(btn => {
    btn.addEventListener('click', () => eliminarAbono(btn.getAttribute('data-id')));
  });
}

async function eliminarAbono(id) {
  if (!confirm('¿Eliminar este abono? Esta acción no se puede deshacer.')) return;
  try {
    const res = await fetch('api/proyecto-abonos.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'eliminar', id })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', 'Abono eliminado', data.message);
      cargarAbonos();
    } else {
      showToast('bad', 'No se pudo eliminar', data.message);
    }
  } catch (e) {
    showToast('bad', 'Error de conexión', 'No se pudo eliminar el abono.');
  }
}

function wireFormAbono() {
  const form = document.getElementById('form-abono');
  if (!form) return;

  const hoy = new Date().toISOString().slice(0, 10);
  const inputFecha = document.getElementById('ab_fecha');
  if (inputFecha && !inputFecha.value) inputFecha.value = hoy;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const payload = Object.fromEntries(new FormData(form).entries());
    try {
      const res = await fetch('api/proyecto-abonos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'crear', ...payload })
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Abono registrado', data.message);
        form.reset();
        if (inputFecha) inputFecha.value = hoy;
        bootstrap.Modal.getInstance(document.getElementById('modalAbono')).hide();
        cargarAbonos();
      } else {
        showToast('bad', 'No se pudo registrar el abono', data.message);
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
      showToast('bad', 'Error de conexión', 'No se pudo registrar el abono.');
    }
  });
}

function iniciarVistaDetalle() {
  cargarEtapasYTareas();
  cargarCostosExtra();
  wireFormCostoExtra();
  cargarAbonos();
  wireFormAbono();

  const formEtapa = document.getElementById('form-etapa');
  const formTarea = document.getElementById('form-tarea');
  if (!formEtapa || !formTarea) return; // sin permiso para gestionar este proyecto

  cargarUsuariosAsignables();
  document.getElementById('modalTarea').addEventListener('show.bs.modal', () => {
    cargarUsuariosAsignables();
    sincronizarSelectEtapaTarea();
  });
  document.getElementById('btn-nueva-etapa')?.addEventListener('click', resetearModalEtapa);

  formEtapa.addEventListener('submit', async (e) => {
    e.preventDefault();
    formEtapa.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    formEtapa.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    const esEdicion = document.getElementById('e_accion').value === 'editar';
    const payload = Object.fromEntries(new FormData(formEtapa).entries());
    try {
      const res = await fetch('api/etapas.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', esEdicion ? 'Etapa actualizada' : 'Etapa creada', data.message);
        bootstrap.Modal.getInstance(document.getElementById('modalEtapa')).hide();
        if (!esEdicion) etapaPreseleccionada = data.id;
        await cargarEtapasYTareas();
      } else {
        showToast('bad', esEdicion ? 'No se pudo guardar la etapa' : 'No se pudo crear la etapa', data.message);
        Object.entries(data.errors || {}).forEach(([campo, msg]) => {
          const input = formEtapa.querySelector(`[name="${campo}"]`);
          if (input) {
            input.classList.add('is-invalid');
            const err = input.closest('.mb-3, .mb-1')?.querySelector('.field-error');
            if (err) err.textContent = msg;
          }
        });
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo guardar la etapa.');
    }
  });

  formTarea.addEventListener('submit', async (e) => {
    e.preventDefault();
    formTarea.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    formTarea.querySelectorAll('.field-error').forEach(el => el.textContent = '');

    if (!etapasCache.length) {
      showToast('bad', 'Crea una etapa primero', 'Una tarea siempre pertenece a una etapa del proyecto.');
      return;
    }

    const esEdicion = document.getElementById('t_accion').value === 'editar';
    const payload = Object.fromEntries(new FormData(formTarea).entries());

    try {
      const res = await fetch('api/tareas.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', esEdicion ? 'Tarea actualizada' : 'Tarea creada', data.message);
        bootstrap.Modal.getInstance(document.getElementById('modalTarea')).hide();
        cargarEtapasYTareas();
      } else {
        showToast('bad', esEdicion ? 'No se pudo guardar la tarea' : 'No se pudo crear la tarea', data.message);
        Object.entries(data.errors || {}).forEach(([campo, msg]) => {
          const input = formTarea.querySelector(`[name="${campo}"]`);
          if (input) {
            input.classList.add('is-invalid');
            const err = input.closest('.mb-3')?.querySelector('.field-error');
            if (err) err.textContent = msg;
          }
        });
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo guardar la tarea.');
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  if (TIMBRA_MODO === 'detalle') {
    iniciarVistaDetalle();
  } else {
    iniciarVistaLista();
  }
});
