<?php
/**
 * GET api/kpis.php
 * -> { total_ventas, ingresos_ventas, proyectos_activos, inventario_disponible, leads_nuevos }
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

timbra_requerir_modulo_api('dashboard');

$pdo = timbra_db();

$totalVentas = (int) $pdo->query('SELECT COUNT(*) FROM ventas')->fetchColumn();
$ingresosVentas = (float) $pdo->query('SELECT COALESCE(SUM(total),0) FROM ventas')->fetchColumn();
$proyectosActivos = (int) $pdo->query('SELECT COUNT(*) FROM proyectos WHERE archivado = 0')->fetchColumn();
$inventarioDisp = (int) $pdo->query('SELECT COALESCE(SUM(cantidad_disponible),0) FROM inventario_items WHERE activo = 1')->fetchColumn();
$leadsNuevos = (int) $pdo->query("SELECT COUNT(*) FROM leads WHERE estado = 'nuevo'")->fetchColumn();

timbra_json([
    'total_ventas'          => $totalVentas,
    'ingresos_ventas'       => round($ingresosVentas, 2),
    'proyectos_activos'     => $proyectosActivos,
    'inventario_disponible' => $inventarioDisp,
    'leads_nuevos'          => $leadsNuevos,
]);
