/* ==========================================================================
   Dashboard configurable — widgets por usuario (localStorage), tamaño por cuadrícula
   ========================================================================== */

function money(n) {
  return `$${Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
function escapeHtmlD(s){const d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}

const WIDGETS = [
  { id: 'kpis', nombre: 'KPIs generales', roles: ['admin','contador','lider_proyecto','colaborador'], span: 12 },
  { id: 'ventas_recientes', nombre: 'Ventas recientes', roles: ['admin','contador','lider_proyecto','colaborador'], span: 6 },
  { id: 'proyectos_activos', nombre: 'Proyectos activos', roles: ['admin','lider_proyecto','contador','colaborador'], span: 6 },
  { id: 'mis_tareas', nombre: 'Mis tareas pendientes', roles: ['admin','lider_proyecto','contador','colaborador'], span: 6 },
  { id: 'finanzas_negocio', nombre: 'Finanzas por negocio', roles: ['admin'], span: 12 },
  { id: 'leads_nuevos', nombre: 'Leads nuevos', roles: ['admin','lider_proyecto'], span: 6 },
  { id: 'citas_pendientes', nombre: 'Citas pendientes', roles: ['admin','lider_proyecto','contador','colaborador'], span: 6 },
];

const SPAN_OPCIONES = [3,4,6,8,9,12];

function widgetsDefault(rol) {
  return WIDGETS.filter(w => w.roles.includes(rol)).map(w => ({ id: w.id, span: w.span }));
}

function normalizarGuardados(raw) {
  if (!Array.isArray(raw)) return widgetsDefault(TIMBRA_USER_ROL);
  // Migración: formato viejo era array de strings
  if (raw.length && typeof raw[0] === 'string') {
    return raw.map(id => {
      const w = WIDGETS.find(x => x.id === id);
      return { id, span: w ? w.span : 6 };
    });
  }
  return raw.filter(x => x && x.id).map(x => ({ id: x.id, span: Math.min(12, Math.max(1, parseInt(x.span, 10) || 6)) }));
}

function widgetsGuardados() {
  try {
    const raw = localStorage.getItem(`dashboard-widgets-${TIMBRA_USER_ID}`);
    if (raw) return normalizarGuardados(JSON.parse(raw));
  } catch (e) {}
  return widgetsDefault(TIMBRA_USER_ROL);
}

function guardarWidgets(lista) {
  localStorage.setItem(`dashboard-widgets-${TIMBRA_USER_ID}`, JSON.stringify(lista));
}

async function fetchJson(url) {
  const res = await fetch(url, { cache: 'no-store' });
  if (!res.ok) throw new Error();
  return res.json();
}

async function renderWidgetKpis(container) {
  const d = await fetchJson('api/kpis.php');
  container.innerHTML = `
    <section class="kpi-grid mb-0">
      <div class="kpi success-kpi"><div class="kpi-label">Ventas registradas</div><div class="kpi-value success">${d.total_ventas}</div><div class="kpi-foot">ventas registradas</div></div>
      <div class="kpi"><div class="kpi-label">Ingresos por ventas</div><div class="kpi-value">${money(d.ingresos_ventas)}</div><div class="kpi-foot">total acumulado</div></div>
      <div class="kpi"><div class="kpi-label">Proyectos activos</div><div class="kpi-value">${d.proyectos_activos}</div><div class="kpi-foot">no archivados</div></div>
      <div class="kpi"><div class="kpi-label">Inventario disponible</div><div class="kpi-value">${d.inventario_disponible}</div><div class="kpi-foot">unidades</div></div>
      <div class="kpi"><div class="kpi-label">Leads nuevos</div><div class="kpi-value">${d.leads_nuevos}</div><div class="kpi-foot">sin asignar</div></div>
    </section>`;
}

async function renderWidgetVentas(container) {
  const ventas = await fetchJson('api/ventas.php');
  const ultimas = (ventas || []).slice(0, 5);
  container.innerHTML = `
    <div class="card-timbra h-100">
      <div class="card-header-timbra"><h2><i class="bi bi-cart3 me-1"></i> Ventas recientes</h2></div>
      <div class="card-body-timbra">
        ${ultimas.length ? `<table class="table table-sm mb-0"><tbody>
          ${ultimas.map(v => `<tr><td class="hint mb-0">${(v.created_at||'').slice(0,10)}</td><td>${escapeHtmlD(v.negocio_nombre||'—')}</td><td class="text-end mono">${money(v.total)}</td></tr>`).join('')}
        </tbody></table>` : '<div class="hint">Sin ventas todavía.</div>'}
      </div>
    </div>`;
}

async function renderWidgetProyectos(container) {
  const proyectos = await fetchJson('api/proyectos.php');
  const activos = (proyectos || []).filter(p => !p.archivado).slice(0, 5);
  container.innerHTML = `
    <div class="card-timbra h-100">
      <div class="card-header-timbra"><h2><i class="bi bi-kanban me-1"></i> Proyectos activos</h2></div>
      <div class="card-body-timbra">
        ${activos.length ? `<ul class="list-unstyled mb-0">
          ${activos.map(p => `<li class="d-flex justify-content-between py-1 border-bottom"><span>${escapeHtmlD(p.nombre)}</span><span class="mono">${p.tareas_completadas}/${p.total_tareas}</span></li>`).join('')}
        </ul>` : '<div class="hint">Sin proyectos activos.</div>'}
      </div>
    </div>`;
}

async function renderWidgetTareas(container) {
  const hoy = new Date().toISOString().slice(0,10);
  const fin = new Date(Date.now() + 30*86400000).toISOString().slice(0,10);
  let data = { tareas: [] };
  try { data = await fetchJson(`api/agenda.php?desde=${hoy}&hasta=${fin}`); } catch (e) {}
  const tareas = data.tareas || [];
  container.innerHTML = `
    <div class="card-timbra h-100">
      <div class="card-header-timbra"><h2><i class="bi bi-list-check me-1"></i> Mis tareas pendientes</h2></div>
      <div class="card-body-timbra">
        ${tareas.length ? `<ul class="list-unstyled mb-0">
          ${tareas.slice(0,6).map(t => `<li class="py-1 border-bottom"><span class="badge bg-secondary me-2">${escapeHtmlD(t.estado)}</span>${escapeHtmlD(t.titulo)}</li>`).join('')}
        </ul>` : '<div class="hint">Sin tareas pendientes.</div>'}
      </div>
    </div>`;
}

async function renderWidgetFinanzas(container) {
  const data = await fetchJson('api/finanzas.php?periodo=mes_actual');
  const lista = data.por_negocio || [];
  container.innerHTML = `
    <div class="card-timbra h-100">
      <div class="card-header-timbra"><h2><i class="bi bi-shop me-1"></i> Finanzas por negocio (este mes)</h2></div>
      <div class="card-body-timbra">
        ${lista.length ? `<table class="table table-sm mb-0"><thead><tr><th>Negocio</th><th class="text-end">Total</th><th class="text-end">Cobrado</th><th class="text-end">Pendiente</th></tr></thead><tbody>
          ${lista.map(n => `<tr><td>${escapeHtmlD(n.negocio_nombre)}</td><td class="text-end mono">${money(n.ingresos_totales)}</td><td class="text-end mono">${money(n.cobrado_total)}</td><td class="text-end mono">${money(n.pendiente_total)}</td></tr>`).join('')}
        </tbody></table>` : '<div class="hint">Sin datos.</div>'}
      </div>
    </div>`;
}

async function renderWidgetLeads(container) {
  const leads = await fetchJson('api/leads.php');
  const nuevos = (leads || []).filter(l => l.estado === 'nuevo').slice(0, 5);
  container.innerHTML = `
    <div class="card-timbra h-100">
      <div class="card-header-timbra"><h2><i class="bi bi-magnet me-1"></i> Leads nuevos</h2></div>
      <div class="card-body-timbra">
        ${nuevos.length ? `<ul class="list-unstyled mb-0">
          ${nuevos.map(l => `<li class="py-1 border-bottom">${escapeHtmlD(l.nombre)} <span class="hint mb-0">— ${escapeHtmlD(l.empresa || '')}</span></li>`).join('')}
        </ul>` : '<div class="hint">Sin leads nuevos.</div>'}
      </div>
    </div>`;
}

async function renderWidgetCitas(container) {
  const hoy = new Date().toISOString().slice(0,10);
  const fin = new Date(Date.now() + 30*86400000).toISOString().slice(0,10);
  let data = { citas: [] };
  try { data = await fetchJson(`api/agenda.php?desde=${hoy}&hasta=${fin}`); } catch (e) {}
  const citas = data.citas || [];
  container.innerHTML = `
    <div class="card-timbra h-100">
      <div class="card-header-timbra"><h2><i class="bi bi-calendar3 me-1"></i> Citas pendientes</h2></div>
      <div class="card-body-timbra">
        ${citas.length ? `<ul class="list-unstyled mb-0">
          ${citas.slice(0,6).map(c => `<li class="py-1 border-bottom"><span class="mono me-2">${escapeHtmlD(c.fecha)} ${escapeHtmlD(c.hora_inicio)}</span>${escapeHtmlD(c.titulo)}</li>`).join('')}
        </ul>` : '<div class="hint">Sin citas próximas.</div>'}
      </div>
    </div>`;
}

const RENDERERS = {
  kpis: renderWidgetKpis,
  ventas_recientes: renderWidgetVentas,
  proyectos_activos: renderWidgetProyectos,
  mis_tareas: renderWidgetTareas,
  finanzas_negocio: renderWidgetFinanzas,
  leads_nuevos: renderWidgetLeads,
  citas_pendientes: renderWidgetCitas,
};

async function renderDashboard() {
  const lista = widgetsGuardados();
  const disponibles = WIDGETS.filter(w => w.roles.includes(TIMBRA_USER_ROL)).map(w => w.id);
  const activos = lista.filter(x => disponibles.includes(x.id));
  const container = document.getElementById('dashboard-widgets');
  container.innerHTML = '';
  for (const w of activos) {
    const cell = document.createElement('div');
    cell.className = 'widget-cell';
    cell.style.gridColumn = `span ${w.span}`;
    container.appendChild(cell);
    try {
      await RENDERERS[w.id](cell);
    } catch (e) {
      cell.innerHTML = `<div class="hint">No se pudo cargar el widget ${w.id}.</div>`;
    }
  }
  if (!activos.length) {
    container.innerHTML = '<div class="hint">No hay widgets seleccionados. Usa "Personalizar dashboard".</div>';
  }
}

function pintarConfig() {
  const disponibles = WIDGETS.filter(w => w.roles.includes(TIMBRA_USER_ROL));
  const activos = widgetsGuardados();
  const cont = document.getElementById('lista-widgets-config');
  cont.innerHTML = disponibles.map(w => {
    const cfg = activos.find(x => x.id === w.id) || { id: w.id, span: w.span };
    const opts = SPAN_OPCIONES.map(s => `<option value="${s}" ${cfg.span === s ? 'selected' : ''}>${s} columnas</option>`).join('');
    return `
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" value="${w.id}" id="cfg-${w.id}" ${activos.some(x => x.id === w.id) ? 'checked' : ''}>
        <label class="form-check-label" for="cfg-${w.id}">${escapeHtmlD(w.nombre)}</label>
      </div>
      <select class="form-select form-select-sm" style="width:auto" data-span="${w.id}">${opts}</select>
    </div>`;
  }).join('');
}

document.addEventListener('DOMContentLoaded', () => {
  pintarConfig();
  document.getElementById('btn-guardar-widgets').addEventListener('click', () => {
    const ids = Array.from(document.querySelectorAll('#lista-widgets-config input:checked')).map(i => i.value);
    const lista = ids.map(id => {
      const sel = document.querySelector(`#lista-widgets-config select[data-span="${id}"]`);
      return { id, span: parseInt(sel?.value || 6, 10) };
    });
    guardarWidgets(lista);
    bootstrap.Modal.getInstance(document.getElementById('modalPersonalizar'))?.hide();
    renderDashboard();
  });
  renderDashboard();
});