<?php
/**
 * GET api/agenda.php?desde=YYYY-MM-DD&hasta=YYYY-MM-DD
 * -> { citas: [...], tareas: [...] }
 *
 * Visibilidad (ninguna se decide en el front, todo se filtra aquí):
 *   - admin: todas las citas y todas las tareas pendientes del rango.
 *   - lider_proyecto: sus propias citas (donde participa), + sus propias
 *     tareas asignadas, + las tareas de su equipo dentro de los proyectos
 *     que él mismo creó.
 *   - cualquier otro rol: sus propias citas (donde participa) + sus
 *     propias tareas asignadas.
 * "Tareas pendientes" = estado distinto de completada, con fecha_fin
 * dentro del rango pedido.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('agenda');
$pdo = timbra_db();

$desde = (string) ($_GET['desde'] ?? '');
$hasta = (string) ($_GET['hasta'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    timbra_json(['success' => false, 'message' => 'Rango de fechas inválido.'], 422);
}

/* ---------------- Citas: siempre "donde soy participante" ---------------- */
if ($usuario['rol'] === 'admin') {
    $stmt = $pdo->prepare("
        SELECT c.id, c.titulo, c.fecha, c.hora_inicio, c.hora_fin,
               cl.nombre AS cliente_nombre, u.nombre AS creado_por_nombre
        FROM citas c
        JOIN usuarios u ON u.id = c.creado_por
        LEFT JOIN clientes cl ON cl.id = c.cliente_id
        WHERE c.cancelada = 0 AND c.fecha BETWEEN :desde AND :hasta
        ORDER BY c.fecha ASC, c.hora_inicio ASC
    ");
    $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
} else {
    $stmt = $pdo->prepare("
        SELECT c.id, c.titulo, c.fecha, c.hora_inicio, c.hora_fin,
               cl.nombre AS cliente_nombre, u.nombre AS creado_por_nombre,
               cp.estado AS mi_estado
        FROM citas c
        JOIN usuarios u ON u.id = c.creado_por
        JOIN cita_participantes cp ON cp.cita_id = c.id AND cp.usuario_id = :uid
        LEFT JOIN clientes cl ON cl.id = c.cliente_id
        WHERE c.cancelada = 0 AND c.fecha BETWEEN :desde AND :hasta
        ORDER BY c.fecha ASC, c.hora_inicio ASC
    ");
    $stmt->execute(['uid' => $usuario['id'], 'desde' => $desde, 'hasta' => $hasta]);
}
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------------- Tareas pendientes, según el rol ---------------- */
if ($usuario['rol'] === 'admin') {
    $sqlTareas = "
        SELECT t.id, t.titulo, t.prioridad, t.estado, t.fecha_fin, t.proyecto_id,
               p.nombre AS proyecto_nombre, u.nombre AS asignado_nombre
        FROM tareas t
        JOIN proyectos p ON p.id = t.proyecto_id
        LEFT JOIN usuarios u ON u.id = t.asignado_a
        WHERE t.eliminado = 0 AND t.estado != 'completada'
              AND t.fecha_fin BETWEEN :desde AND :hasta
        ORDER BY t.fecha_fin ASC
    ";
    $stmt = $pdo->prepare($sqlTareas);
    $stmt->execute(['desde' => $desde, 'hasta' => $hasta]);
} elseif ($usuario['rol'] === 'lider_proyecto') {
    // sus propias tareas asignadas + las de su equipo dentro de SUS proyectos
    $sqlTareas = "
        SELECT t.id, t.titulo, t.prioridad, t.estado, t.fecha_fin, t.proyecto_id,
               p.nombre AS proyecto_nombre, u.nombre AS asignado_nombre
        FROM tareas t
        JOIN proyectos p ON p.id = t.proyecto_id
        LEFT JOIN usuarios u ON u.id = t.asignado_a
        WHERE t.eliminado = 0 AND t.estado != 'completada'
              AND t.fecha_fin BETWEEN :desde AND :hasta
              AND (t.asignado_a = :uid OR p.creado_por = :uid2)
        ORDER BY t.fecha_fin ASC
    ";
    $stmt = $pdo->prepare($sqlTareas);
    $stmt->execute(['desde' => $desde, 'hasta' => $hasta, 'uid' => $usuario['id'], 'uid2' => $usuario['id']]);
} else {
    $sqlTareas = "
        SELECT t.id, t.titulo, t.prioridad, t.estado, t.fecha_fin, t.proyecto_id,
               p.nombre AS proyecto_nombre, u.nombre AS asignado_nombre
        FROM tareas t
        JOIN proyectos p ON p.id = t.proyecto_id
        LEFT JOIN usuarios u ON u.id = t.asignado_a
        WHERE t.eliminado = 0 AND t.estado != 'completada'
              AND t.fecha_fin BETWEEN :desde AND :hasta AND t.asignado_a = :uid
        ORDER BY t.fecha_fin ASC
    ";
    $stmt = $pdo->prepare($sqlTareas);
    $stmt->execute(['desde' => $desde, 'hasta' => $hasta, 'uid' => $usuario['id']]);
}
$tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

timbra_json(['citas' => $citas, 'tareas' => $tareas]);
