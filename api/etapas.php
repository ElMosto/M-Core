<?php
/**
 * GET  api/etapas.php?proyecto_id=1   -> [{ id, proyecto_id, nombre, orden, total_tareas, tareas_completadas }, ...]
 * POST api/etapas.php  accion=crear    (admin / lider_proyecto dueño)  { proyecto_id, nombre, orden }
 * POST api/etapas.php  accion=editar   (admin / lider_proyecto dueño)  { id, nombre, orden }
 * POST api/etapas.php  accion=eliminar (admin / lider_proyecto dueño)  { id }
 *
 * Eliminar es un borrado suave (columna `eliminado`); se rechaza si la
 * etapa todavía tiene tareas activas, para no perder tareas "huérfanas"
 * por accidente.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('tareas');
$pdo = timbra_db();

function timbra_obtener_proyecto_simple(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM proyectos WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function timbra_obtener_etapa_simple(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM etapas WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function timbra_es_dueno_de_etapa(PDO $pdo, array $usuario, array $etapa): bool
{
    if ($usuario['rol'] === 'admin') {
        return true;
    }
    $proyecto = timbra_obtener_proyecto_simple($pdo, (int) $etapa['proyecto_id']);
    return $proyecto && $usuario['rol'] === 'lider_proyecto' && (int) $proyecto['creado_por'] === (int) $usuario['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $proyectoId = (int) ($_GET['proyecto_id'] ?? 0);
    $proyecto = timbra_obtener_proyecto_simple($pdo, $proyectoId);
    if (!$proyecto) {
        timbra_json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
    }
    if (!timbra_puede_ver_proyecto($pdo, $usuario, $proyecto)) {
        timbra_json(['success' => false, 'message' => 'No tienes acceso a este proyecto.'], 403);
    }

    $stmt = $pdo->prepare("
        SELECT e.id, e.proyecto_id, e.nombre, e.orden,
               (SELECT COUNT(*) FROM tareas t WHERE t.etapa_id = e.id AND t.eliminado = 0) AS total_tareas,
               (SELECT COUNT(*) FROM tareas t WHERE t.etapa_id = e.id AND t.eliminado = 0 AND t.estado = 'completada') AS tareas_completadas
        FROM etapas e
        WHERE e.proyecto_id = :pid AND e.eliminado = 0
        ORDER BY e.orden ASC, e.id ASC
    ");
    $stmt->execute(['pid' => $proyectoId]);
    timbra_json($stmt->fetchAll(PDO::FETCH_ASSOC));
}

$body = timbra_body_json();
$accion = $body['accion'] ?? 'crear';

if ($accion === 'editar') {
    $id = (int) ($body['id'] ?? 0);
    $etapa = timbra_obtener_etapa_simple($pdo, $id);
    if (!$etapa) {
        timbra_json(['success' => false, 'message' => 'Etapa no encontrada.'], 404);
    }
    if (!timbra_es_dueno_de_etapa($pdo, $usuario, $etapa)) {
        timbra_json(['success' => false, 'message' => 'Solo el líder dueño del proyecto o un admin puede editar esta etapa.'], 403);
    }

    $nombre = trim((string) ($body['nombre'] ?? ''));
    if ($nombre === '') {
        timbra_json(['success' => false, 'message' => 'La etapa necesita un nombre.', 'errors' => ['nombre' => 'Obligatorio.']], 422);
    }
    $orden = is_numeric($body['orden'] ?? null) ? (int) $body['orden'] : $etapa['orden'];

    $pdo->prepare('UPDATE etapas SET nombre = ?, orden = ? WHERE id = ?')->execute([$nombre, $orden, $id]);
    timbra_json(['success' => true, 'message' => 'Etapa actualizada.']);
}

if ($accion === 'eliminar') {
    $id = (int) ($body['id'] ?? 0);
    $etapa = timbra_obtener_etapa_simple($pdo, $id);
    if (!$etapa) {
        timbra_json(['success' => false, 'message' => 'Etapa no encontrada.'], 404);
    }
    if (!timbra_es_dueno_de_etapa($pdo, $usuario, $etapa)) {
        timbra_json(['success' => false, 'message' => 'Solo el líder dueño del proyecto o un admin puede eliminar esta etapa.'], 403);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tareas WHERE etapa_id = ? AND eliminado = 0');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        timbra_json(['success' => false, 'message' => 'Esta etapa todavía tiene tareas — elimínalas o muévelas antes de borrar la etapa.'], 422);
    }

    $pdo->prepare('UPDATE etapas SET eliminado = 1 WHERE id = ?')->execute([$id]);
    timbra_json(['success' => true, 'message' => 'Etapa eliminada.']);
}

/* ---- crear (accion=crear o sin accion, por compatibilidad) ---- */
$proyectoId = (int) ($body['proyecto_id'] ?? 0);
$proyecto = timbra_obtener_proyecto_simple($pdo, $proyectoId);

if (!$proyecto) {
    timbra_json(['success' => false, 'message' => 'Selecciona un proyecto válido.'], 422);
}

$esAdmin = $usuario['rol'] === 'admin';
$esLiderDueno = $usuario['rol'] === 'lider_proyecto' && (int) $proyecto['creado_por'] === (int) $usuario['id'];
if (!$esAdmin && !$esLiderDueno) {
    timbra_json(['success' => false, 'message' => 'Solo el líder dueño del proyecto o un admin puede crear etapas.'], 403);
}

$nombre = trim((string) ($body['nombre'] ?? ''));
if ($nombre === '') {
    timbra_json(['success' => false, 'message' => 'La etapa necesita un nombre.', 'errors' => ['nombre' => 'Obligatorio.']], 422);
}

$orden = $body['orden'] ?? null;
if ($orden === null || $orden === '') {
    $siguiente = $pdo->prepare('SELECT COALESCE(MAX(orden), 0) + 1 FROM etapas WHERE proyecto_id = ?');
    $siguiente->execute([$proyectoId]);
    $orden = (int) $siguiente->fetchColumn();
}

$pdo->prepare('INSERT INTO etapas (proyecto_id, nombre, orden) VALUES (?, ?, ?)')
    ->execute([$proyectoId, $nombre, (int) $orden]);

timbra_json(['success' => true, 'message' => 'Etapa creada.', 'id' => (int) $pdo->lastInsertId()]);
