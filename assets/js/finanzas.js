/* ==========================================================================
   Finanzas (solo admin)
   ========================================================================== */

function escapeHtmlFin(s) {
  const div = document.createElement('div');
  div.textContent = s == null ? '' : s;
  return div.innerHTML;
}

function money(n) {
  return `$${Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

async function cargarNegociosFinanzas() {
  const sel = document.getElementById('sel-negocio');
  try {
    const res = await fetch('api/negocios.php', { cache: 'no-store' });
    const negocios = await res.json();
    sel.innerHTML = '<option value="">Todos los negocios</option>' + negocios.map(n => `<option value="${n.id}">${escapeHtmlFin(n.nombre)}</option>`).join('');
  } catch (e) {}
}

async function cargarFinanzas() {
  const periodo = document.getElementById('sel-periodo').value;
  const negocio = document.getElementById('sel-negocio').value;
  let data;
  try {
    const res = await fetch(`api/finanzas.php?periodo=${periodo}${negocio ? '&negocio_id=' + negocio : ''}`, { cache: 'no-store' });
    if (!res.ok) throw new Error();
    data = await res.json();
  } catch (e) {
    showToast('bad', 'No se pudo cargar Finanzas', 'Intenta de nuevo en unos segundos.');
    return;
  }

  pintarPorNegocio(data.por_negocio || []);
  pintarKpis(data.kpis, data.periodo);
  pintarTendencia(data.ingresos_por_mes);
  pintarTopClientes(data.top_clientes);
  pintarProyectos(data.proyectos);
  pintarVentas(data.ventas);
}

function pintarPorNegocio(lista) {
  const tbody = document.getElementById('tabla-por-negocio-body');
  tbody.innerHTML = lista.length
    ? lista.map(n => `
      <tr>
        <td>${escapeHtmlFin(n.negocio_nombre)}</td>
        <td class="text-end mono">${money(n.ingresos_proyectos)}</td>
        <td class="text-end mono">${money(n.ingresos_ventas)}</td>
        <td class="text-end mono"><strong>${money(n.ingresos_totales)}</strong></td>
        <td class="text-end mono" style="color:var(--brand-dark)">${money(n.cobrado_total)}</td>
        <td class="text-end mono" style="color:${n.pendiente_total > 0 ? 'var(--danger)' : 'var(--brand-dark)'}">${money(n.pendiente_total)}</td>
      </tr>
    `).join('')
    : '<tr><td colspan="6" class="hint">Sin negocios.</td></tr>';
}

function pintarKpis(k, periodo) {
  document.getElementById('kpi-ingresos-totales').textContent = money(k.ingresos_totales);
  document.getElementById('kpi-periodo-label').textContent = periodo.label;
  document.getElementById('kpi-ingresos-proyectos').textContent = money(k.ingresos_proyectos);
  document.getElementById('kpi-num-proyectos').textContent = `${k.num_proyectos} proyecto(s)`;
  document.getElementById('kpi-ingresos-ventas').textContent = money(k.ingresos_ventas);
  document.getElementById('kpi-num-ventas').textContent = `${k.num_ventas} venta(s)`;
  document.getElementById('kpi-ticket-promedio').textContent = money(k.ticket_promedio_venta);
  document.getElementById('kpi-cobrado').textContent = money(k.cobrado_total);
  document.getElementById('kpi-pendiente').textContent = money(k.pendiente_total);
}

function pintarTendencia(meses) {
  const cont = document.getElementById('tendencia-mensual');
  const maxTotal = Math.max(1, ...meses.map(m => m.total));
  const NOMBRES_MES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

  cont.innerHTML = meses.map(m => {
    const [anio, mes] = m.mes.split('-');
    const label = `${NOMBRES_MES[parseInt(mes, 10) - 1]} ${anio.slice(2)}`;
    const pctProyectos = (m.proyectos / maxTotal) * 100;
    const pctVentas = (m.ventas / maxTotal) * 100;
    return `
      <div class="tendencia-fila">
        <div class="tendencia-mes">${label}</div>
        <div class="tendencia-barra">
          <div class="seg-proyectos" style="width:${pctProyectos}%"></div>
          <div class="seg-ventas" style="width:${pctVentas}%"></div>
        </div>
        <div class="tendencia-total">${money(m.total)}</div>
      </div>
    `;
  }).join('');
}

function pintarTopClientes(clientes) {
  const tbody = document.getElementById('tabla-top-clientes-body');
  tbody.innerHTML = clientes.length
    ? clientes.map(c => `<tr><td>${escapeHtmlFin(c.cliente_nombre)}</td><td class="text-end mono">${money(c.total)}</td></tr>`).join('')
    : '<tr><td colspan="2" class="hint">Sin ingresos en este periodo.</td></tr>';
}

function pintarProyectos(proyectos) {
  const tbody = document.getElementById('tabla-fin-proyectos-body');
  tbody.innerHTML = proyectos.length
    ? proyectos.map(p => `
      <tr>
        <td><a href="tareas.php?id=${p.id}" class="text-reset">${escapeHtmlFin(p.nombre)}</a></td>
        <td>${p.cliente_nombre ? escapeHtmlFin(p.cliente_nombre) : '<span class="hint mb-0">—</span>'}</td>
        <td class="hint mb-0">${(p.created_at || '').slice(0, 10)}</td>
        <td class="text-end mono">${money(p.costo)}</td>
        <td class="text-end mono">${money(p.extras)}</td>
        <td class="text-end mono"><strong>${money(p.total)}</strong></td>
        <td class="text-end mono" style="color:var(--brand-dark)">${money(p.abonado)}</td>
        <td class="text-end mono" style="color:${p.saldo > 0 ? 'var(--danger)' : 'var(--brand-dark)'}">${money(p.saldo)}</td>
      </tr>
    `).join('')
    : '<tr><td colspan="8" class="hint">Sin proyectos externos en este periodo.</td></tr>';
}

function pintarVentas(ventas) {
  const tbody = document.getElementById('tabla-fin-ventas-body');
  tbody.innerHTML = ventas.length
    ? ventas.map(v => `
      <tr>
        <td class="hint mb-0">${(v.created_at || '').replace('T', ' ').slice(0, 16)}</td>
        <td>${v.cliente_nombre ? escapeHtmlFin(v.cliente_nombre) : '<span class="hint mb-0">Sin cliente</span>'}</td>
        <td>${escapeHtmlFin(v.vendido_por_nombre)}</td>
        <td class="text-end mono"><strong>${money(v.total)}</strong></td>
        <td>
          <button class="btn btn-sm ${v.cobrado == 1 ? 'btn-timbra' : 'btn-outline-secondary'} btn-toggle-cobrado-venta" data-id="${v.id}">
            ${v.cobrado == 1 ? 'Cobrado' : 'Marcar cobrado'}
          </button>
        </td>
      </tr>
    `).join('')
    : '<tr><td colspan="5" class="hint">Sin ventas en este periodo.</td></tr>';

  tbody.querySelectorAll('.btn-toggle-cobrado-venta').forEach(btn => {
    btn.addEventListener('click', () => toggleCobrado('marcar_cobrado_venta', btn.getAttribute('data-id')));
  });
}

async function toggleCobrado(accion, id) {
  try {
    const res = await fetch('api/finanzas.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion, id })
    });
    const data = await res.json();
    if (!data.success) { showToast('bad', 'No se pudo actualizar', data.message || ''); return; }
    cargarFinanzas();
  } catch (e) {
    showToast('bad', 'Error de conexión', 'Intenta de nuevo.');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  cargarNegociosFinanzas().then(cargarFinanzas);
  document.getElementById('sel-periodo').addEventListener('change', cargarFinanzas);
  document.getElementById('sel-negocio').addEventListener('change', cargarFinanzas);
});
