<?php
/**
 * GET  api/finanzas.php?periodo=mes_actual|mes_pasado|anio_actual|todo
 * -> { periodo, kpis, ingresos_por_mes, top_clientes, proyectos, ventas }
 *
 * POST api/finanzas.php
 *      { accion: "marcar_cobrado_proyecto", id }  -> legado, ya no lo usa la interfaz
 *      { accion: "marcar_cobrado_venta", id }      -> alterna cobrado en una venta
 *
 * Todo el módulo es exclusivo de admin (TIMBRA_MODULOS['finanzas'] = []).
 * Ingresos = proyectos externos (costo base + costos extra) + ventas.
 * Lo "cobrado" de un proyecto ya NO es un interruptor manual — se calcula
 * sumando sus abonos reales (api/proyecto-abonos.php, sección Abonos
 * dentro de cada proyecto). Lo cobrado de una venta sigue siendo el
 * interruptor manual (las ventas no tienen abonos parciales todavía).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('finanzas');
$pdo = timbra_db();

/** Calcula [desde, hasta, label] en formato 'YYYY-MM-DD' según el preset. */
function timbra_rango_periodo(string $preset): array
{
    $hoy = new DateTime('today');
    switch ($preset) {
        case 'mes_pasado':
            $desde = (new DateTime('first day of last month'))->format('Y-m-d');
            $hasta = (new DateTime('last day of last month'))->format('Y-m-d');
            return [$desde, $hasta, 'Mes pasado'];
        case 'anio_actual':
            return [$hoy->format('Y') . '-01-01', $hoy->format('Y') . '-12-31', 'Este año'];
        case 'todo':
            return [null, null, 'Todo el historial'];
        case 'mes_actual':
        default:
            $desde = (new DateTime('first day of this month'))->format('Y-m-d');
            $hasta = (new DateTime('last day of this month'))->format('Y-m-d');
            return [$desde, $hasta, 'Este mes'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $preset = (string) ($_GET['periodo'] ?? 'mes_actual');
    $negocioFiltro = isset($_GET['negocio_id']) && $_GET['negocio_id'] !== '' ? (int) $_GET['negocio_id'] : null;
    [$desde, $hasta, $label] = timbra_rango_periodo($preset);

    $params = [];
    if ($desde && $hasta) {
        $params = ['desde' => $desde, 'hasta' => $hasta];
    }
    // Cada consulta tiene su propio alias, y ambas se unen con tablas que
    // también tienen su propia columna created_at (clientes, usuarios) —
    // por eso el filtro se arma por separado con el alias explícito en
    // cada caso, en vez de una sola cadena "created_at" ambigua.
    $filtroFechaProyectos = ($desde && $hasta) ? ' AND date(p.created_at) BETWEEN :desde AND :hasta' : '';
    $filtroFechaVentas = ($desde && $hasta) ? ' AND date(v.created_at) BETWEEN :desde AND :hasta' : '';
    $filtroNegocioProyectos = $negocioFiltro ? ' AND p.negocio_id = :negocio_id' : '';
    $filtroNegocioVentas = $negocioFiltro ? ' AND v.negocio_id = :negocio_id' : '';

    /* ---- Proyectos externos en el periodo ---- */
    // COALESCE(p.archivado, 0) = 0 en vez de p.archivado = 0: si por
    // cualquier razón la columna llegara a tener NULL en vez de 0/1 (no
    // debería, pero es gratis cubrirlo), un proyecto activo nunca se cae
    // del reporte por accidente, y uno archivado nunca se cuela por NULL.
    $sqlProyectos = "
        SELECT p.id, p.nombre, p.costo, p.cobrado, p.created_at,
               cl.nombre AS cliente_nombre,
               COALESCE((SELECT SUM(ce.monto) FROM costos_extra ce WHERE ce.proyecto_id = p.id), 0) AS extras,
               COALESCE((SELECT SUM(ab.monto) FROM proyecto_abonos ab WHERE ab.proyecto_id = p.id), 0) AS abonado
        FROM proyectos p
        LEFT JOIN clientes cl ON cl.id = p.cliente_id
        WHERE p.tipo = 'externo' AND COALESCE(p.archivado, 0) = 0 {$filtroFechaProyectos}{$filtroNegocioProyectos}
        ORDER BY p.created_at DESC
    ";
    $paramsProy = $params;
    if ($negocioFiltro) {
        $paramsProy['negocio_id'] = $negocioFiltro;
    }
    $stmt = $pdo->prepare($sqlProyectos);
    $stmt->execute($paramsProy);
    $proyectos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($proyectos as &$p) {
        $p['total'] = round((float) $p['costo'] + (float) $p['extras'], 2);
        $p['abonado'] = round(min((float) $p['abonado'], $p['total']), 2); // no cuenta de más si hubo un abono de sobra
        $p['saldo'] = round($p['total'] - $p['abonado'], 2);
    }
    unset($p);

    /* ---- Ventas en el periodo ---- */
    $sqlVentas = "
        SELECT v.id, v.total, v.cobrado, v.created_at,
               cl.nombre AS cliente_nombre, u.nombre AS vendido_por_nombre
        FROM ventas v
        LEFT JOIN clientes cl ON cl.id = v.cliente_id
        JOIN usuarios u ON u.id = v.vendido_por
        WHERE 1 = 1 {$filtroFechaVentas}{$filtroNegocioVentas}
        ORDER BY v.created_at DESC
    ";
    $paramsVent = $params;
    if ($negocioFiltro) {
        $paramsVent['negocio_id'] = $negocioFiltro;
    }
    $stmt = $pdo->prepare($sqlVentas);
    $stmt->execute($paramsVent);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ---- KPIs ---- */
    $ingresosProyectos = array_sum(array_column($proyectos, 'total'));
    $ingresosVentas = array_sum(array_map('floatval', array_column($ventas, 'total')));
    // "Cobrado" de un proyecto ya no es un sí/no manual — se calcula sumando
    // sus abonos reales (ver módulo de Abonos dentro de cada proyecto).
    $cobradoProyectos = array_sum(array_column($proyectos, 'abonado'));
    $cobradoVentas = array_sum(array_map(fn($v) => $v['cobrado'] ? (float) $v['total'] : 0, $ventas));
    $totalIngresos = round($ingresosProyectos + $ingresosVentas, 2);
    $totalCobrado = round($cobradoProyectos + $cobradoVentas, 2);

    $kpis = [
        'ingresos_proyectos' => round($ingresosProyectos, 2),
        'ingresos_ventas'    => round($ingresosVentas, 2),
        'ingresos_totales'   => $totalIngresos,
        'cobrado_total'      => $totalCobrado,
        'pendiente_total'    => round($totalIngresos - $totalCobrado, 2),
        'num_proyectos'      => count($proyectos),
        'num_ventas'         => count($ventas),
        'ticket_promedio_venta' => $ventas ? round($ingresosVentas / count($ventas), 2) : 0,
    ];

    /* ---- Tendencia: últimos 6 meses (independiente del filtro de periodo) ---- */
    $porMes = []; // 'YYYY-MM' => ['proyectos'=>x, 'ventas'=>y]
    $primerDiaMesActual = new DateTime('first day of this month');
    for ($i = 5; $i >= 0; $i--) {
        $clave = (clone $primerDiaMesActual)->modify("-{$i} months")->format('Y-m');
        $porMes[$clave] = ['mes' => $clave, 'proyectos' => 0.0, 'ventas' => 0.0];
    }
    $seisMesesAtras = (clone $primerDiaMesActual)->modify('-5 months')->format('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT p.created_at,
               p.costo + COALESCE((SELECT SUM(ce.monto) FROM costos_extra ce WHERE ce.proyecto_id = p.id), 0) AS total
        FROM proyectos p
        WHERE p.tipo = 'externo' AND COALESCE(p.archivado, 0) = 0 AND date(p.created_at) >= ?
    ");
    $stmt->execute([$seisMesesAtras]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $clave = substr($row['created_at'], 0, 7);
        if (isset($porMes[$clave])) {
            $porMes[$clave]['proyectos'] += (float) $row['total'];
        }
    }

    $stmt = $pdo->prepare('SELECT created_at, total FROM ventas WHERE date(created_at) >= ?');
    $stmt->execute([$seisMesesAtras]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $clave = substr($row['created_at'], 0, 7);
        if (isset($porMes[$clave])) {
            $porMes[$clave]['ventas'] += (float) $row['total'];
        }
    }
    $ingresosPorMes = array_values($porMes);
    foreach ($ingresosPorMes as &$m) {
        $m['proyectos'] = round($m['proyectos'], 2);
        $m['ventas'] = round($m['ventas'], 2);
        $m['total'] = round($m['proyectos'] + $m['ventas'], 2);
    }
    unset($m);

    /* ---- Top clientes (dentro del periodo filtrado) ---- */
    $porCliente = []; // nombre => total
    foreach ($proyectos as $p) {
        $nombre = $p['cliente_nombre'] ?? 'Sin cliente';
        $porCliente[$nombre] = ($porCliente[$nombre] ?? 0) + $p['total'];
    }
    foreach ($ventas as $v) {
        $nombre = $v['cliente_nombre'] ?? 'Sin cliente';
        $porCliente[$nombre] = ($porCliente[$nombre] ?? 0) + (float) $v['total'];
    }
    arsort($porCliente);
    $topClientes = [];
    foreach (array_slice($porCliente, 0, 8, true) as $nombre => $total) {
        $topClientes[] = ['cliente_nombre' => $nombre, 'total' => round($total, 2)];
    }

    // Desglose por negocio (respeta el filtro de fecha, no el de negocio).
    $porNegocio = [];
    $negocios = timbra_db()->query('SELECT id, nombre, logo_path FROM negocios WHERE activo = 1 ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($negocios as $n) {
        $porNegocio[$n['id']] = [
            'negocio_id' => (int) $n['id'],
            'negocio_nombre' => $n['nombre'],
            'negocio_logo' => $n['logo_path'],
            'ingresos_proyectos' => 0.0,
            'ingresos_ventas' => 0.0,
            'cobrado_proyectos' => 0.0,
            'cobrado_ventas' => 0.0,
            'num_proyectos' => 0,
            'num_ventas' => 0,
        ];
    }
    // Proyectos por negocio (mismas reglas de periodo).
    $sqlPN = "SELECT p.negocio_id, p.costo, COALESCE((SELECT SUM(ce.monto) FROM costos_extra ce WHERE ce.proyecto_id = p.id),0) AS extras,
              COALESCE((SELECT SUM(ab.monto) FROM proyecto_abonos ab WHERE ab.proyecto_id = p.id),0) AS abonado
              FROM proyectos p WHERE p.tipo='externo' AND COALESCE(p.archivado,0)=0" . (($desde && $hasta) ? ' AND date(p.created_at) BETWEEN :desde AND :hasta' : '');
    $st = timbra_db()->prepare($sqlPN);
    $st->execute($params);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $nid = (int) ($r['negocio_id'] ?? 0);
        if (!isset($porNegocio[$nid])) continue;
        $total = (float) $r['costo'] + (float) $r['extras'];
        $porNegocio[$nid]['ingresos_proyectos'] += $total;
        $porNegocio[$nid]['cobrado_proyectos'] += min((float) $r['abonado'], $total);
        $porNegocio[$nid]['num_proyectos']++;
    }
    // Ventas por negocio.
    $sqlVN = "SELECT v.negocio_id, v.total, v.cobrado FROM ventas v WHERE 1=1" . (($desde && $hasta) ? ' AND date(v.created_at) BETWEEN :desde AND :hasta' : '');
    $st = timbra_db()->prepare($sqlVN);
    $st->execute($params);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $nid = (int) ($r['negocio_id'] ?? 0);
        if (!isset($porNegocio[$nid])) continue;
        $porNegocio[$nid]['ingresos_ventas'] += (float) $r['total'];
        if ($r['cobrado']) {
            $porNegocio[$nid]['cobrado_ventas'] += (float) $r['total'];
        }
        $porNegocio[$nid]['num_ventas']++;
    }
    foreach ($porNegocio as &$pn) {
        $pn['ingresos_totales'] = round($pn['ingresos_proyectos'] + $pn['ingresos_ventas'], 2);
        $pn['cobrado_total'] = round($pn['cobrado_proyectos'] + $pn['cobrado_ventas'], 2);
        $pn['pendiente_total'] = round($pn['ingresos_totales'] - $pn['cobrado_total'], 2);
        $pn['ingresos_proyectos'] = round($pn['ingresos_proyectos'], 2);
        $pn['ingresos_ventas'] = round($pn['ingresos_ventas'], 2);
    }
    unset($pn);

    timbra_json([
        'periodo'          => ['preset' => $preset, 'desde' => $desde, 'hasta' => $hasta, 'label' => $label],
        'kpis'             => $kpis,
        'ingresos_por_mes' => $ingresosPorMes,
        'top_clientes'     => $topClientes,
        'proyectos'        => $proyectos,
        'ventas'           => $ventas,
        'por_negocio'      => array_values($porNegocio),
        'negocio_filtro'   => $negocioFiltro,
    ]);
}

$body = timbra_body_json();
$accion = $body['accion'] ?? '';
$id = (int) ($body['id'] ?? 0);

if ($accion === 'marcar_cobrado_proyecto') {
    $pdo->prepare('UPDATE proyectos SET cobrado = 1 - cobrado WHERE id = ?')->execute([$id]);
    timbra_json(['success' => true]);
}

if ($accion === 'marcar_cobrado_venta') {
    $pdo->prepare('UPDATE ventas SET cobrado = 1 - cobrado WHERE id = ?')->execute([$id]);
    timbra_json(['success' => true]);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
