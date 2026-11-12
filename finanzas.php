<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_modulo('finanzas');

$pageTitle    = 'Finanzas';
$activeNav    = 'finanzas';
$headerTitle  = 'Finanzas';
$headerSub    = 'Ganancias generadas por proyectos externos y por ventas';
$extraScripts = ['assets/js/finanzas.js?v=3'];
include __DIR__ . '/includes/layout-top.php';
?>

<div class="d-flex justify-content-end mb-3 gap-2">
  <select class="form-select form-select-sm" id="sel-negocio" style="width:auto">
    <option value="">Todos los negocios</option>
  </select>
  <select class="form-select form-select-sm" id="sel-periodo" style="width:auto">
    <option value="mes_actual" selected>Este mes</option>
    <option value="mes_pasado">Mes pasado</option>
    <option value="anio_actual">Este año</option>
    <option value="todo">Todo el historial</option>
  </select>
</div>

<section class="kpi-grid mb-4" id="kpi-grid-finanzas">
  <div class="kpi success-kpi">
    <svg class="seal-mark" viewBox="0 0 76 76" fill="none"><circle cx="38" cy="38" r="34" stroke="#0F6E5C" stroke-width="4"/></svg>
    <div class="kpi-label">Ingresos totales</div>
    <div class="kpi-value success" id="kpi-ingresos-totales">—</div>
    <div class="kpi-foot" id="kpi-periodo-label">cargando…</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Por proyectos</div>
    <div class="kpi-value" id="kpi-ingresos-proyectos">—</div>
    <div class="kpi-foot" id="kpi-num-proyectos"></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Por ventas</div>
    <div class="kpi-value" id="kpi-ingresos-ventas">—</div>
    <div class="kpi-foot" id="kpi-num-ventas"></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Ticket promedio de venta</div>
    <div class="kpi-value" id="kpi-ticket-promedio">—</div>
    <div class="kpi-foot">por venta registrada</div>
  </div>
  <div class="kpi success-kpi">
    <div class="kpi-label">Cobrado</div>
    <div class="kpi-value success" id="kpi-cobrado">—</div>
    <div class="kpi-foot">ya recibido</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Pendiente de cobro</div>
    <div class="kpi-value danger" id="kpi-pendiente">—</div>
    <div class="kpi-foot">facturado, sin cobrar todavía</div>
  </div>
</section>

<div class="card-timbra mb-4">
  <div class="card-header-timbra">
    <h2><i class="bi bi-bar-chart-line me-1"></i> Tendencia — últimos 6 meses</h2>
    <div class="d-flex align-items-center gap-3">
      <span class="hint mb-0"><span class="finanzas-legend-dot proyectos"></span> Proyectos</span>
      <span class="hint mb-0"><span class="finanzas-legend-dot ventas"></span> Ventas</span>
    </div>
  </div>
  <div class="card-body-timbra" id="tendencia-mensual">
    <div class="hint">Cargando…</div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <div class="card-timbra h-100">
      <div class="card-header-timbra">
        <h2><i class="bi bi-person-vcard me-1"></i> Top clientes</h2>
      </div>
      <div class="card-body-timbra">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Cliente</th><th class="text-end">Ingresos</th></tr></thead>
            <tbody id="tabla-top-clientes-body">
              <tr><td colspan="2" class="hint">Cargando…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card-timbra h-100">
      <div class="card-header-timbra">
        <h2><i class="bi bi-info-circle me-1"></i> Cómo se calcula</h2>
      </div>
      <div class="card-body-timbra">
        <p class="hint mb-2">Los ingresos que ves aquí combinan dos fuentes:</p>
        <ul class="hint mb-0 ps-3">
          <li class="mb-1"><strong>Proyectos:</strong> costo base + costos extra de cada proyecto <span class="mono">externo</span> no archivado.</li>
          <li class="mb-1"><strong>Ventas:</strong> el total de cada venta registrada en el módulo de Ventas.</li>
          <li>El "Cobrado" de un <strong>proyecto</strong> se calcula solo, sumando sus abonos reales (los registras dentro de cada proyecto, en su sección Abonos).</li>
          <li>El "Cobrado" de una <strong>venta</strong> sigue siendo manual — márcalo en la tabla de abajo cuando el dinero ya haya entrado.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="card-timbra mb-4">
  <div class="card-header-timbra">
    <h2><i class="bi bi-shop me-1"></i> Desglose por negocio</h2>
  </div>
  <div class="card-body-timbra">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Negocio</th><th class="text-end">Proyectos</th><th class="text-end">Ventas</th><th class="text-end">Total</th><th class="text-end">Cobrado</th><th class="text-end">Pendiente</th></tr></thead>
        <tbody id="tabla-por-negocio-body">
          <tr><td colspan="6" class="hint">Cargando…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card-timbra mb-4">
  <div class="card-header-timbra">
    <h2><i class="bi bi-kanban me-1"></i> Proyectos externos en el periodo</h2>
  </div>
  <div class="card-body-timbra">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr><th>Proyecto</th><th>Cliente</th><th>Fecha</th><th class="text-end">Costo base</th><th class="text-end">Extras</th><th class="text-end">Total</th><th class="text-end">Abonado</th><th class="text-end">Saldo</th></tr>
        </thead>
        <tbody id="tabla-fin-proyectos-body">
          <tr><td colspan="8" class="hint">Cargando…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card-timbra mb-4">
  <div class="card-header-timbra">
    <h2><i class="bi bi-cart3 me-1"></i> Ventas en el periodo</h2>
  </div>
  <div class="card-body-timbra">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr><th>Fecha</th><th>Cliente</th><th>Vendió</th><th class="text-end">Total</th><th>Cobrado</th></tr>
        </thead>
        <tbody id="tabla-fin-ventas-body">
          <tr><td colspan="5" class="hint">Cargando…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
