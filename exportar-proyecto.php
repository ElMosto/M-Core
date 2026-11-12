<?php
/**
 * GET exportar-proyecto.php?id=5
 * Genera un .xlsx con el diagrama de Gantt del proyecto (una fila por
 * etapa/tarea, columnas de encabezado + una columna por día del rango) y
 * lo manda como descarga directa.
 *
 * Acceso: solo admin, o el lider_proyecto dueño de ESE proyecto — el mismo
 * botón "Exportar proyecto" solo se muestra a esos roles en tareas.php, y
 * aquí se vuelve a validar en el servidor.
 */

require_once __DIR__ . '/api/lib/auth.php';
require_once __DIR__ . '/api/lib/XlsxWriter.php';

$usuarioActual = timbra_requerir_modulo('tareas');

$proyectoId = (int) ($_GET['id'] ?? 0);
$pdo = timbra_db();

$stmt = $pdo->prepare('
    SELECT p.*, cl.nombre AS cliente_nombre, u.nombre AS creado_por_nombre
    FROM proyectos p
    LEFT JOIN clientes cl ON cl.id = p.cliente_id
    JOIN usuarios u ON u.id = p.creado_por
    WHERE p.id = ?
');
$stmt->execute([$proyectoId]);
$proyecto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proyecto) {
    header('Location: tareas.php');
    exit;
}

$esAdmin = $usuarioActual['rol'] === 'admin';
$esLiderDueno = $usuarioActual['rol'] === 'lider_proyecto' && (int) $proyecto['creado_por'] === (int) $usuarioActual['id'];
if (!$esAdmin && !$esLiderDueno) {
    header('Location: tareas.php?id=' . $proyectoId);
    exit;
}

/* ---------------- Datos ---------------- */

$etapas = $pdo->prepare('SELECT * FROM etapas WHERE proyecto_id = ? AND eliminado = 0 ORDER BY orden ASC, id ASC');
$etapas->execute([$proyectoId]);
$etapas = $etapas->fetchAll(PDO::FETCH_ASSOC);

$tareasStmt = $pdo->prepare('
    SELECT t.*, u.nombre AS asignado_nombre
    FROM tareas t LEFT JOIN usuarios u ON u.id = t.asignado_a
    WHERE t.proyecto_id = ? AND t.eliminado = 0
    ORDER BY t.etapa_id, t.prioridad = "alta" DESC, t.fecha_fin IS NULL, t.fecha_fin ASC
');
$tareasStmt->execute([$proyectoId]);
$tareas = $tareasStmt->fetchAll(PDO::FETCH_ASSOC);

$tareasPorEtapa = [];
foreach ($tareas as $t) {
    $tareasPorEtapa[(int) $t['etapa_id']][] = $t;
}

/* ---------------- Rango de fechas del Gantt ---------------- */

$candidatas = [];
if ($proyecto['fecha_inicio']) $candidatas[] = $proyecto['fecha_inicio'];
if ($proyecto['fecha_fin_tentativa']) $candidatas[] = $proyecto['fecha_fin_tentativa'];
foreach ($tareas as $t) {
    if ($t['fecha_inicio']) $candidatas[] = $t['fecha_inicio'];
    if ($t['fecha_fin']) $candidatas[] = $t['fecha_fin'];
}

if ($candidatas) {
    sort($candidatas);
    $fechaMin = new DateTime($candidatas[0]);
    $fechaMax = new DateTime(end($candidatas));
} else {
    $fechaMin = new DateTime('today');
    $fechaMax = (clone $fechaMin)->modify('+13 days');
}
if ($fechaMax < $fechaMin) {
    $fechaMax = clone $fechaMin;
}

$LIMITE_DIAS = 180;
$totalDias = (int) $fechaMin->diff($fechaMax)->days + 1;
$rangoTruncado = false;
if ($totalDias > $LIMITE_DIAS) {
    $fechaMax = (clone $fechaMin)->modify('+' . ($LIMITE_DIAS - 1) . ' days');
    $totalDias = $LIMITE_DIAS;
    $rangoTruncado = true;
}

$MESES = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/** Índice de columna de fecha (1-based dentro del rango) para una fecha dada. */
function timbra_col_de_fecha(DateTime $fecha, DateTime $inicio): int
{
    return (int) $inicio->diff($fecha)->days + 1;
}

/* ---------------- Construcción del Excel ---------------- */

const COL_FIJAS = 6; // Etapa/Tarea, Asignado, Prioridad, Estado, Inicio, Fin
$xlsx = new TimbraXlsx();

$xlsx->setAnchoColumna(1, 42);
$xlsx->setAnchoColumna(2, 20);
$xlsx->setAnchoColumna(3, 10);
$xlsx->setAnchoColumna(4, 12);
$xlsx->setAnchoColumna(5, 11);
$xlsx->setAnchoColumna(6, 11);
for ($d = 0; $d < $totalDias; $d++) {
    $xlsx->setAnchoColumna(COL_FIJAS + 1 + $d, 3);
}

$fila = 1;
$xlsx->setCell($fila, 1, 'Proyecto: ' . $proyecto['nombre'], TimbraXlsx::S_TITULO);
$fila++;

$sub1 = 'Tipo: ' . ($proyecto['tipo'] === 'externo' ? 'Externo' : 'Interno');
if ($proyecto['tipo'] === 'externo') {
    $sub1 .= ' · Cliente: ' . ($proyecto['cliente_nombre'] ?? '—');
    $sub1 .= ' · Costo base: $' . number_format((float) $proyecto['costo'], 2);
}
$xlsx->setCell($fila, 1, $sub1, TimbraXlsx::S_SUBTITULO);
$fila++;

$sub2 = 'Fechas tentativas: ' . ($proyecto['fecha_inicio'] ?: '¿?') . ' → ' . ($proyecto['fecha_fin_tentativa'] ?: '¿?')
    . ' · Generado el ' . date('Y-m-d H:i') . ' por ' . $usuarioActual['nombre'];
if ($rangoTruncado) {
    $sub2 .= ' · Rango del Gantt truncado a ' . $LIMITE_DIAS . ' días';
}
$xlsx->setCell($fila, 1, $sub2, TimbraXlsx::S_SUBTITULO);
$fila += 2; // deja una fila en blanco

$filaEncabezado = $fila;
$xlsx->setCell($fila, 1, 'Etapa / Tarea', TimbraXlsx::S_ENCABEZADO);
$xlsx->setCell($fila, 2, 'Asignado', TimbraXlsx::S_ENCABEZADO);
$xlsx->setCell($fila, 3, 'Prioridad', TimbraXlsx::S_ENCABEZADO);
$xlsx->setCell($fila, 4, 'Estado', TimbraXlsx::S_ENCABEZADO);
$xlsx->setCell($fila, 5, 'Inicio', TimbraXlsx::S_ENCABEZADO);
$xlsx->setCell($fila, 6, 'Fin', TimbraXlsx::S_ENCABEZADO);

$cursor = clone $fechaMin;
for ($d = 0; $d < $totalDias; $d++) {
    $etiqueta = $cursor->format('d') . '-' . $MESES[(int) $cursor->format('n')];
    $xlsx->setCell($fila, COL_FIJAS + 1 + $d, $etiqueta, TimbraXlsx::S_ENCABEZADO);
    $cursor->modify('+1 day');
}
$fila++;

$ESTADO_LABEL = ['pendiente' => 'Pendiente', 'en_progreso' => 'En progreso', 'completada' => 'Completada'];
$ESTADO_ESTILO = [
    'pendiente'   => TimbraXlsx::S_BARRA_PENDIENTE,
    'en_progreso' => TimbraXlsx::S_BARRA_PROGRESO,
    'completada'  => TimbraXlsx::S_BARRA_COMPLETADA,
];

foreach ($etapas as $etapa) {
    $xlsx->setCell($fila, 1, $etapa['nombre'], TimbraXlsx::S_ETAPA);
    for ($c = 2; $c <= COL_FIJAS + $totalDias; $c++) {
        $xlsx->setCell($fila, $c, '', TimbraXlsx::S_ETAPA);
    }
    $fila++;

    foreach (($tareasPorEtapa[(int) $etapa['id']] ?? []) as $t) {
        $xlsx->setCell($fila, 1, '  ' . $t['titulo'], TimbraXlsx::S_NORMAL);
        $xlsx->setCell($fila, 2, $t['asignado_nombre'] ?: 'Sin asignar', TimbraXlsx::S_NORMAL);
        $xlsx->setCell($fila, 3, ucfirst($t['prioridad']), TimbraXlsx::S_NORMAL);
        $xlsx->setCell($fila, 4, $ESTADO_LABEL[$t['estado']] ?? $t['estado'], TimbraXlsx::S_NORMAL);

        // Antes de tener fecha_inicio/fecha_fin propias, esto se aproximaba
        // con created_at → fecha_limite. Tareas capturadas desde ese cambio
        // ya traen ambas fechas reales; una tarea vieja migrada (sin
        // fecha_inicio) cae al día de creación como respaldo razonable.
        $inicioTarea = $t['fecha_inicio'] ? new DateTime($t['fecha_inicio']) : new DateTime(substr($t['created_at'], 0, 10));
        $finTarea = $t['fecha_fin'] ? new DateTime($t['fecha_fin']) : clone $inicioTarea;
        if ($finTarea < $inicioTarea) {
            $finTarea = clone $inicioTarea;
        }
        // recorta al rango visible del Gantt
        $inicioVisible = max($inicioTarea, $fechaMin);
        $finVisible = min($finTarea, $fechaMax);

        $xlsx->setCell($fila, 5, $inicioTarea->format('Y-m-d'), TimbraXlsx::S_NORMAL);
        $xlsx->setCell($fila, 6, $t['fecha_fin'] ?: 'Sin fecha final', TimbraXlsx::S_NORMAL);

        $estilo = $ESTADO_ESTILO[$t['estado']] ?? TimbraXlsx::S_BARRA_PENDIENTE;

        // celdas de la cuadrícula vacías (para que se vea el grid completo)
        for ($c = COL_FIJAS + 1; $c <= COL_FIJAS + $totalDias; $c++) {
            $xlsx->setCell($fila, $c, '', TimbraXlsx::S_CELDA_GRID);
        }
        // barra del Gantt sobre el rango real de la tarea
        if ($inicioVisible <= $finVisible) {
            $colIni = COL_FIJAS + timbra_col_de_fecha($inicioVisible, $fechaMin);
            $colFin = COL_FIJAS + timbra_col_de_fecha($finVisible, $fechaMin);
            for ($c = $colIni; $c <= $colFin; $c++) {
                $xlsx->setCell($fila, $c, '', $estilo);
            }
        }

        $fila++;
    }
}

$xlsx->congelarPanel(COL_FIJAS, $filaEncabezado, TimbraXlsx::colLetra(COL_FIJAS + 1) . ($filaEncabezado + 1));

$nombreArchivo = 'gantt-' . preg_replace('/[^a-z0-9]+/i', '-', $proyecto['nombre']) . '-' . date('Ymd') . '.xlsx';
$rutaTemporal = tempnam(sys_get_temp_dir(), 'timbra_gantt_');
$xlsx->guardar($rutaTemporal, 'Gantt');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Content-Length: ' . filesize($rutaTemporal));
readfile($rutaTemporal);
unlink($rutaTemporal);
exit;
