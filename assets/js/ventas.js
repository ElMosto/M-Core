/* ==========================================================================
   Ventas — registrar venta con líneas de productos/servicios, ver historial
   ========================================================================== */

function escapeHtmlV(s) {
  const div = document.createElement('div');
  div.textContent = s == null ? '' : s;
  return div.innerHTML;
}

let productosCache = [];

async function cargarProductosParaVenta() {
  try {
    const res = await fetch('api/productos-servicios.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    productosCache = await res.json();
  } catch (e) {
    productosCache = [];
  }
}

async function cargarClientesParaVenta() {
  const select = document.getElementById('v_cliente');
  try {
    const res = await fetch('api/clientes.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    const clientes = await res.json();
    select.innerHTML = '<option value="">Sin cliente registrado</option>' +
      clientes.map(c => `<option value="${c.id}">${escapeHtmlV(c.nombre)}</option>`).join('');
  } catch (e) {
    select.innerHTML = '<option value="">Sin cliente registrado</option>';
  }
}

async function cargarNegociosParaVenta(selectId) {
  const select = document.getElementById(selectId);
  try {
    const res = await fetch('api/negocios.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    const negocios = await res.json();
    select.innerHTML = negocios.map(n => `<option value="${n.id}" ${n.id === (negocios[0]?.id) ? 'selected' : ''}>${escapeHtmlV(n.nombre)}</option>`).join('');
  } catch (e) {
    select.innerHTML = '';
  }
}

function opcionesProductoHtml(seleccionado) {
  if (!productosCache.length) {
    return '<option value="" disabled selected>No hay productos/servicios activos</option>';
  }
  return '<option value="" disabled ' + (seleccionado ? '' : 'selected') + '>Selecciona…</option>' +
    productosCache.map(p => `
      <option value="${p.id}" data-precio="${p.precio}" ${p.id === seleccionado ? 'selected' : ''}>
        ${escapeHtmlV(p.nombre)} — $${Number(p.precio).toFixed(2)} (${p.tipo === 'servicio' ? 'servicio' : 'producto'})
      </option>
    `).join('');
}

function agregarLineaVenta() {
  const tpl = document.getElementById('tpl-linea-venta');
  const nodo = tpl.content.cloneNode(true);
  const fila = nodo.querySelector('.linea-venta');
  const selectProducto = nodo.querySelector('.select-producto');
  selectProducto.innerHTML = opcionesProductoHtml(null);

  const recalcular = () => recomputarLinea(fila);
  selectProducto.addEventListener('change', recalcular);
  nodo.querySelector('.input-cantidad').addEventListener('input', recalcular);
  nodo.querySelector('.btn-quitar-linea').addEventListener('click', () => {
    fila.remove();
    recomputarTotalVenta();
  });

  document.getElementById('lineas-venta').appendChild(nodo);
}

function recomputarLinea(fila) {
  const select = fila.querySelector('.select-producto');
  const opcion = select.options[select.selectedIndex];
  const precio = opcion ? Number(opcion.getAttribute('data-precio') || 0) : 0;
  const cantidad = Math.max(1, parseInt(fila.querySelector('.input-cantidad').value, 10) || 1);
  const subtotal = precio * cantidad;
  fila.querySelector('.subtotal-linea').value = `$${subtotal.toFixed(2)}`;
  recomputarTotalVenta();
}

function recomputarTotalVenta() {
  let total = 0;
  document.querySelectorAll('#lineas-venta .linea-venta').forEach(fila => {
    const select = fila.querySelector('.select-producto');
    const opcion = select.options[select.selectedIndex];
    const precio = opcion ? Number(opcion.getAttribute('data-precio') || 0) : 0;
    const cantidad = Math.max(1, parseInt(fila.querySelector('.input-cantidad').value, 10) || 1);
    total += precio * cantidad;
  });
  document.getElementById('v_total_display').textContent = `$${total.toFixed(2)}`;
}

function resetearFormVenta() {
  document.getElementById('form-venta').reset();
  document.getElementById('lineas-venta').innerHTML = '';
  document.getElementById('err-items').textContent = '';
  agregarLineaVenta();
  recomputarTotalVenta();
}

/* ==================== Historial de ventas ==================== */

let ventasCache = [];

async function cargarVentas() {
  const tbody = document.getElementById('tabla-ventas-body');
  let ventas;
  try {
    const res = await fetch('api/ventas.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    ventas = await res.json();
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="6" class="hint">No se pudieron cargar las ventas.</td></tr>';
    return;
  }

  ventasCache = ventas;

  tbody.innerHTML = ventas.length
    ? ventas.map(v => `
      <tr>
        <td class="hint mb-0">${(v.created_at || '').replace('T', ' ').slice(0, 16)}</td>
        <td>${v.cliente_nombre ? escapeHtmlV(v.cliente_nombre) : '<span class="hint mb-0">Sin cliente</span>'}${v.negocio_nombre ? `<div class="hint mb-0" style="font-size:.75rem"><i class="bi bi-shop me-1"></i>${escapeHtmlV(v.negocio_nombre)}</div>` : ''}</td>
        <td>${escapeHtmlV(v.vendido_por_nombre)}</td>
        <td class="mono">${v.total_lineas}</td>
        <td class="mono">$${Number(v.total).toFixed(2)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-timbra btn-ver-venta" data-id="${v.id}">Ver</button>
          <a class="btn btn-sm btn-outline-secondary" href="ticket-venta.php?id=${v.id}" target="_blank" title="Imprimir ticket"><i class="bi bi-printer"></i></a>
          ${TIMBRA_ES_ADMIN_VENTAS ? `<button class="btn btn-sm btn-outline-secondary btn-editar-venta" data-id="${v.id}" title="Editar venta"><i class="bi bi-pencil"></i></button>` : ''}
          ${TIMBRA_ES_ADMIN_VENTAS ? `<button class="btn btn-sm btn-outline-secondary btn-eliminar-venta" data-id="${v.id}" title="Eliminar venta"><i class="bi bi-trash"></i></button>` : ''}
        </td>
      </tr>
    `).join('')
    : '<tr><td colspan="6" class="hint">Todavía no hay ventas registradas.</td></tr>';

  tbody.querySelectorAll('.btn-ver-venta').forEach(btn => {
    btn.addEventListener('click', () => verDetalleVenta(btn.getAttribute('data-id')));
  });
  tbody.querySelectorAll('.btn-editar-venta').forEach(btn => {
    btn.addEventListener('click', () => abrirModalEditarVenta(parseInt(btn.getAttribute('data-id'), 10)));
  });
  tbody.querySelectorAll('.btn-eliminar-venta').forEach(btn => {
    btn.addEventListener('click', () => eliminarVenta(btn.getAttribute('data-id')));
  });
}

async function abrirModalEditarVenta(id) {
  const v = ventasCache.find(x => Number(x.id) === id);
  if (!v) return;

  document.getElementById('ev_id').value = v.id;
  document.getElementById('ev_notas').value = v.notas || '';

  const select = document.getElementById('ev_cliente');
  select.innerHTML = '<option value="">Cargando…</option>';
  const selectN = document.getElementById('ev_negocio');
  selectN.innerHTML = '<option value="">Cargando…</option>';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarVenta')).show();

  try {
    const res = await fetch('api/clientes.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    const clientes = await res.json();
    select.innerHTML = '<option value="">Sin cliente registrado</option>' +
      clientes.map(c => `<option value="${c.id}" ${Number(v.cliente_id) === c.id ? 'selected' : ''}>${escapeHtmlV(c.nombre)}</option>`).join('');
  } catch (e) {
    select.innerHTML = '<option value="">Sin cliente registrado</option>';
  }
  try {
    const res = await fetch('api/negocios.php', { cache: 'no-store' });
    if (!res.ok) throw new Error();
    const negocios = await res.json();
    selectN.innerHTML = negocios.map(n => `<option value="${n.id}" ${Number(v.negocio_id) === n.id ? 'selected' : ''}>${escapeHtmlV(n.nombre)}</option>`).join('');
  } catch (e) {
    selectN.innerHTML = '';
  }
}

async function eliminarVenta(id) {
  if (!confirm('¿Eliminar esta venta? Esta acción no se puede deshacer.')) return false;
  try {
    const res = await fetch('api/ventas.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'eliminar', id })
    });
    const data = await res.json();
    if (data.success) {
      showToast('ok', 'Venta eliminada', data.message);
      cargarVentas();
      return true;
    }
    showToast('bad', 'No se pudo eliminar', data.message);
    return false;
  } catch (e) {
    showToast('bad', 'Error de conexión', 'No se pudo eliminar la venta.');
    return false;
  }
}

async function verDetalleVenta(id) {
  const body = document.getElementById('detalle-venta-body');
  body.innerHTML = '<div class="hint">Cargando…</div>';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleVenta')).show();

  try {
    const res = await fetch(`api/ventas.php?id=${id}`, { cache: 'no-store' });
    if (!res.ok) throw new Error();
    const v = await res.json();

    body.innerHTML = `
      <div class="mb-2 hint">${(v.created_at || '').replace('T', ' ').slice(0, 16)} · Vendió: ${escapeHtmlV(v.vendido_por_nombre)}</div>
      <div class="mb-3">${v.cliente_nombre ? `<i class="bi bi-person-vcard me-1"></i>${escapeHtmlV(v.cliente_nombre)}` : '<span class="hint mb-0">Sin cliente registrado</span>'}</div>
      <table class="table table-sm">
        <thead><tr><th>Artículo</th><th>Cant.</th><th>P. unitario</th><th>Subtotal</th></tr></thead>
        <tbody>
          ${v.items.map(it => `
            <tr>
              <td>${escapeHtmlV(it.nombre_item)}</td>
              <td class="mono">${it.cantidad}</td>
              <td class="mono">$${Number(it.precio_unitario).toFixed(2)}</td>
              <td class="mono">$${Number(it.subtotal).toFixed(2)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
      <div class="text-end font-display" style="font-size:1.2rem">Total: $${Number(v.total).toFixed(2)}</div>
      ${v.notas ? `<div class="hint mt-2">Notas: ${escapeHtmlV(v.notas)}</div>` : ''}
      <div class="text-end mt-3">
        <a class="btn btn-outline-timbra btn-sm" href="ticket-venta.php?id=${v.id}" target="_blank">
          <i class="bi bi-printer me-1"></i> Imprimir ticket
        </a>
        ${TIMBRA_ES_ADMIN_VENTAS ? `
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-eliminar-venta-detalle">
            <i class="bi bi-trash me-1"></i> Eliminar venta
          </button>
        ` : ''}
      </div>
    `;

    if (TIMBRA_ES_ADMIN_VENTAS) {
      document.getElementById('btn-eliminar-venta-detalle').addEventListener('click', async () => {
        const eliminada = await eliminarVenta(v.id);
        if (eliminada) bootstrap.Modal.getInstance(document.getElementById('modalDetalleVenta'))?.hide();
      });
    }
  } catch (e) {
    body.innerHTML = '<div class="hint">No se pudo cargar el detalle de esta venta.</div>';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  cargarVentas();
  cargarProductosParaVenta();
  cargarClientesParaVenta();
  cargarNegociosParaVenta('v_negocio');
  cargarNegociosParaVenta('ev_negocio');
  agregarLineaVenta();

  document.getElementById('modalVenta').addEventListener('show.bs.modal', async () => {
    await Promise.all([cargarProductosParaVenta(), cargarClientesParaVenta(), cargarNegociosParaVenta('v_negocio')]);
    resetearFormVenta();
  });

  document.getElementById('btn-agregar-linea').addEventListener('click', agregarLineaVenta);

  document.getElementById('form-venta').addEventListener('submit', async (e) => {
    e.preventDefault();
    document.getElementById('err-items').textContent = '';

    const items = [];
    document.querySelectorAll('#lineas-venta .linea-venta').forEach(fila => {
      const productoId = fila.querySelector('.select-producto').value;
      const cantidad = parseInt(fila.querySelector('.input-cantidad').value, 10) || 0;
      if (productoId && cantidad > 0) {
        items.push({ producto_id: parseInt(productoId, 10), cantidad });
      }
    });

    if (!items.length) {
      document.getElementById('err-items').textContent = 'Agrega al menos un producto o servicio con cantidad válida.';
      return;
    }

    const payload = {
      negocio_id: document.getElementById('v_negocio').value || null,
      cliente_id: document.getElementById('v_cliente').value || null,
      notas: document.getElementById('v_notas').value,
      items,
    };

    try {
      const res = await fetch('api/ventas.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Venta registrada', `Total: $${Number(data.total).toFixed(2)}`);
        bootstrap.Modal.getInstance(document.getElementById('modalVenta')).hide();
        cargarVentas();
      } else {
        showToast('bad', 'No se pudo registrar la venta', data.message);
        if (data.errors && data.errors.items) {
          document.getElementById('err-items').textContent = data.errors.items;
        }
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo registrar la venta.');
    }
  });

  document.getElementById('form-editar-venta')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
      accion: 'editar',
      id: document.getElementById('ev_id').value,
      cliente_id: document.getElementById('ev_cliente').value || null,
      negocio_id: document.getElementById('ev_negocio').value || null,
      notas: document.getElementById('ev_notas').value,
    };

    try {
      const res = await fetch('api/ventas.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        showToast('ok', 'Venta actualizada', data.message);
        bootstrap.Modal.getInstance(document.getElementById('modalEditarVenta')).hide();
        cargarVentas();
      } else {
        showToast('bad', 'No se pudo guardar', data.message);
      }
    } catch (err) {
      showToast('bad', 'Error de conexión', 'No se pudo guardar los cambios.');
    }
  });
});
