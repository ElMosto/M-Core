<?php
/**
 * GET  api/costos-extra.php?proyecto_id=1   -> [{ id, descripcion, monto, creado_por_nombre, created_at }, ...]
 * POST api/costos-extra.php  (admin / lider_proyecto dueño del proyecto)
 *      { proyecto_id, descripcion, monto } -> agrega un costo extra
 *
 * Solo aplica a proyectos externos: los cambios o cosas extra que pide un
 * cliente durante el proyecto se registran aquí, separados del costo base,
 * para no perder el rastro de qué se cobró originalmente y qué se agregó
 * después.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('tareas');
$pdo = timbra_db();

function timbra_obtener_proyecto_costos(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM proyectos WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $proyectoId = (int) ($_GET['proyecto_id'] ?? 0);
    $proyecto = timbra_obtener_proyecto_costos($pdo, $proyectoId);
    if (!$proyecto) {
        timbra_json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
    }
    if (!timbra_puede_ver_proyecto($pdo, $usuario, $proyecto)) {
        timbra_json(['success' => false, 'message' => 'No tienes acceso a este proyecto.'], 403);
    }

    $stmt = $pdo->prepare('
        SELECT ce.id, ce.descripcion, ce.monto, ce.created_at, u.nombre AS creado_por_nombre
        FROM costos_extra ce JOIN usuarios u ON u.id = ce.creado_por
        WHERE ce.proyecto_id = ?
        ORDER BY ce.created_at DESC
    ');
    $stmt->execute([$proyectoId]);
    timbra_json($stmt->fetchAll(PDO::FETCH_ASSOC));
}

$body = timbra_body_json();
$proyectoId = (int) ($body['proyecto_id'] ?? 0);
$proyecto = timbra_obtener_proyecto_costos($pdo, $proyectoId);

if (!$proyecto) {
    timbra_json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
}

$esAdmin = $usuario['rol'] === 'admin';
$esLiderDueno = $usuario['rol'] === 'lider_proyecto' && (int) $proyecto['creado_por'] === (int) $usuario['id'];
if (!$esAdmin && !$esLiderDueno) {
    timbra_json(['success' => false, 'message' => 'Solo el líder dueño del proyecto o un admin puede agregar costos extra.'], 403);
}
if ($proyecto['tipo'] !== 'externo') {
    timbra_json(['success' => false, 'message' => 'Solo los proyectos externos pueden llevar costos extra.'], 422);
}

$descripcion = trim((string) ($body['descripcion'] ?? ''));
$montoRaw = $body['monto'] ?? null;

$errores = [];
if ($descripcion === '') {
    $errores['descripcion'] = 'Describe de qué se trata este costo extra.';
}
if ($montoRaw === null || $montoRaw === '' || !is_numeric($montoRaw) || (float) $montoRaw <= 0) {
    $errores['monto'] = 'Captura un monto numérico mayor a cero.';
}
if ($errores) {
    timbra_json(['success' => false, 'message' => 'Revisa los datos del costo extra.', 'errors' => $errores], 422);
}

$pdo->prepare('
    INSERT INTO costos_extra (proyecto_id, descripcion, monto, creado_por)
    VALUES (?, ?, ?, ?)
')->execute([$proyectoId, $descripcion, (float) $montoRaw, $usuario['id']]);

timbra_json(['success' => true, 'message' => 'Costo extra agregado.']);
