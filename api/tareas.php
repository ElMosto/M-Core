<?php
/**
 * GET  api/tareas.php?proyecto_id=1        -> tareas de ESE proyecto, agrupables por etapa en el front
 * POST api/tareas.php  accion=crear                  { etapa_id, titulo, descripcion, asignado_a, prioridad, fecha_inicio, fecha_fin }
 * POST api/tareas.php  accion=solicitar_cambio_estado { id, estado }   -> lo pide la persona asignada
 * POST api/tareas.php  accion=aprobar_cambio_estado   { id }           -> lo aprueba admin / lider dueño
 * POST api/tareas.php  accion=rechazar_cambio_estado  { id }           -> lo rechaza admin / lider dueño
 * POST api/tareas.php  accion=cambiar_estado          { id, estado }   -> cambio directo, SOLO admin / lider dueño
 *
 * Flujo de estado:
 *   La persona asignada NUNCA cambia el estado directamente — solo puede
 *   PROPONER uno nuevo (queda en `estado_propuesto` hasta que se apruebe).
 *   El admin o el líder dueño del proyecto pueden aprobar (aplica el
 *   cambio), rechazar (descarta la propuesta) o cambiar el estado
 *   directamente en cualquier momento, sin pasar por aprobación.
 *
 * Permisos:
 *   - Ver las tareas de un proyecto: mismas reglas que timbra_puede_ver_proyecto()
 *     (admin: cualquiera; lider_proyecto: solo el suyo; contador/colaborador: solo si
 *     tiene tareas ahí — y en ese caso SOLO ve sus propias tareas, nunca las de otros).
 *   - Crear tareas: admin (en cualquier proyecto) o lider_proyecto (solo en los que él creó).
 *     SIEMPRE dentro de una etapa (etapa_id obligatorio) y SIEMPRE con alguien asignado
 *     (asignado_a obligatorio) — sin eso, el backend rechaza la creación.
 *   - Asignar una tarea: únicamente a usuarios activos con rol colaborador, contador o
 *     lider_proyecto (el propio líder puede asignarse tareas a sí mismo).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('tareas');
$pdo = timbra_db();

const ESTADOS_VALIDOS = ['pendiente', 'en_progreso', 'completada'];
const PRIORIDADES_VALIDAS = ['baja', 'media', 'alta'];

function timbra_obtener_proyecto(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM proyectos WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function timbra_obtener_etapa(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM etapas WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Tarea + datos de su proyecto, para checar dueño en un solo query. */
function timbra_obtener_tarea_con_proyecto(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('
        SELECT t.*, p.creado_por AS proyecto_creado_por
        FROM tareas t JOIN proyectos p ON p.id = t.proyecto_id
        WHERE t.id = ?
    ');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** admin, o el lider_proyecto dueño del proyecto de esa tarea. */
function timbra_es_aprobador(array $usuario, array $tarea): bool
{
    if ($usuario['rol'] === 'admin') {
        return true;
    }
    return $usuario['rol'] === 'lider_proyecto'
        && (int) $tarea['proyecto_creado_por'] === (int) $usuario['id'];
}

/**
 * Valida fecha_inicio/fecha_fin del body (ambas obligatorias, fin no
 * puede ser anterior a inicio). Agrega los errores encontrados a $errores
 * (por referencia) y regresa [fecha_inicio, fecha_fin] ya limpias.
 */
function timbra_validar_fechas_tarea(array $body, array &$errores): array
{
    $fechaInicio = trim((string) ($body['fecha_inicio'] ?? ''));
    $fechaFin = trim((string) ($body['fecha_fin'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) {
        $errores['fecha_inicio'] = 'Selecciona la fecha de inicio.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
        $errores['fecha_fin'] = 'Selecciona la fecha final.';
    }
    if (!isset($errores['fecha_inicio']) && !isset($errores['fecha_fin']) && $fechaFin < $fechaInicio) {
        $errores['fecha_fin'] = 'La fecha final no puede ser anterior a la de inicio.';
    }

    return [$fechaInicio, $fechaFin];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $proyectoId = (int) ($_GET['proyecto_id'] ?? 0);
    if ($proyectoId <= 0) {
        timbra_json(['success' => false, 'message' => 'Falta proyecto_id.'], 422);
    }

    $proyecto = timbra_obtener_proyecto($pdo, $proyectoId);
    if (!$proyecto) {
        timbra_json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
    }
    if (!timbra_puede_ver_proyecto($pdo, $usuario, $proyecto)) {
        timbra_json(['success' => false, 'message' => 'No tienes acceso a este proyecto.'], 403);
    }

    $sql = '
        SELECT t.id, t.proyecto_id, t.etapa_id, t.titulo, t.descripcion,
               t.asignado_a, u.nombre AS asignado_nombre, u.rol AS asignado_rol,
               t.prioridad, t.estado, t.fecha_inicio, t.fecha_fin, t.created_at,
               t.estado_propuesto, t.propuesto_por, up.nombre AS propuesto_por_nombre, t.propuesto_en
        FROM tareas t
        LEFT JOIN usuarios u ON u.id = t.asignado_a
        LEFT JOIN usuarios up ON up.id = t.propuesto_por
        WHERE t.proyecto_id = :pid AND t.eliminado = 0
    ';
    $params = ['pid' => $proyectoId];

    // contador/colaborador solo ven sus propias tareas, incluso dentro de
    // un proyecto al que sí tienen acceso.
    if (in_array($usuario['rol'], ['contador', 'colaborador'], true)) {
        $sql .= ' AND t.asignado_a = :uid';
        $params['uid'] = $usuario['id'];
    }

    $sql .= " ORDER BY CASE t.prioridad WHEN 'alta' THEN 0 WHEN 'media' THEN 1 ELSE 2 END, t.fecha_fin IS NULL, t.fecha_fin ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    timbra_json($stmt->fetchAll(PDO::FETCH_ASSOC));
}

$body = timbra_body_json();
$accion = $body['accion'] ?? '';

if ($accion === 'crear') {
    if (!in_array($usuario['rol'], ['admin', 'lider_proyecto'], true)) {
        timbra_json(['success' => false, 'message' => 'Solo un líder de proyecto o admin puede crear tareas.'], 403);
    }

    $etapaId = (int) ($body['etapa_id'] ?? 0);
    $etapa = timbra_obtener_etapa($pdo, $etapaId);
    if (!$etapa) {
        timbra_json(['success' => false, 'message' => 'Selecciona una etapa válida.', 'errors' => ['etapa_id' => 'Etapa no encontrada.']], 422);
    }

    $proyecto = timbra_obtener_proyecto($pdo, (int) $etapa['proyecto_id']);
    if (!$proyecto) {
        timbra_json(['success' => false, 'message' => 'El proyecto de esta etapa ya no existe.'], 422);
    }
    if ($usuario['rol'] === 'lider_proyecto' && (int) $proyecto['creado_por'] !== (int) $usuario['id']) {
        timbra_json(['success' => false, 'message' => 'Solo puedes crear tareas en los proyectos que tú creaste.'], 403);
    }

    $titulo = trim((string) ($body['titulo'] ?? ''));
    $descripcion = trim((string) ($body['descripcion'] ?? ''));
    $prioridad = in_array($body['prioridad'] ?? '', PRIORIDADES_VALIDAS, true) ? $body['prioridad'] : 'media';

    $errores = [];
    if ($titulo === '') {
        $errores['titulo'] = 'El título es obligatorio.';
    }
    [$fechaInicio, $fechaFin] = timbra_validar_fechas_tarea($body, $errores);

    // Obligatorio: sin alguien asignado, no se crea la tarea.
    $asignadoA = !empty($body['asignado_a']) ? (int) $body['asignado_a'] : null;
    if ($asignadoA === null) {
        $errores['asignado_a'] = 'Toda tarea necesita alguien responsable — elige a quién se le asigna.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? AND activo = 1');
        $stmt->execute([$asignadoA]);
        $existeAsignado = $stmt->fetchColumn();
        if (!$existeAsignado) {
            $errores['asignado_a'] = 'Selecciona un usuario activo.';
        }
    }

    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos de la tarea.', 'errors' => $errores], 422);
    }

    $pdo->prepare('
        INSERT INTO tareas (proyecto_id, etapa_id, titulo, descripcion, asignado_a, creado_por, prioridad, fecha_inicio, fecha_fin)
        VALUES (:proyecto_id, :etapa_id, :titulo, :descripcion, :asignado_a, :creado_por, :prioridad, :fecha_inicio, :fecha_fin)
    ')->execute([
        'proyecto_id' => $proyecto['id'], 'etapa_id' => $etapaId,
        'titulo' => $titulo, 'descripcion' => $descripcion,
        'asignado_a' => $asignadoA, 'creado_por' => $usuario['id'],
        'prioridad' => $prioridad, 'fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin,
    ]);

    timbra_json(['success' => true, 'message' => 'Tarea creada.']);
}

/* ---- Propone un nuevo estado: SOLO la persona asignada ---- */
if ($accion === 'solicitar_cambio_estado') {
    $id = (int) ($body['id'] ?? 0);
    $estado = (string) ($body['estado'] ?? '');

    if (!in_array($estado, ESTADOS_VALIDOS, true)) {
        timbra_json(['success' => false, 'message' => 'Estado no válido.'], 422);
    }

    $tarea = timbra_obtener_tarea_con_proyecto($pdo, $id);
    if (!$tarea) {
        timbra_json(['success' => false, 'message' => 'Tarea no encontrada.'], 404);
    }
    if ((int) $tarea['asignado_a'] !== (int) $usuario['id']) {
        timbra_json(['success' => false, 'message' => 'Solo la persona asignada puede proponer un cambio de estado en esta tarea.'], 403);
    }
    if ($estado === $tarea['estado']) {
        timbra_json(['success' => false, 'message' => 'La tarea ya está en ese estado.'], 422);
    }

    $pdo->prepare('
        UPDATE tareas SET estado_propuesto = ?, propuesto_por = ?, propuesto_en = datetime("now") WHERE id = ?
    ')->execute([$estado, $usuario['id'], $id]);

    timbra_json(['success' => true, 'message' => 'Cambio propuesto — queda pendiente de aprobación.']);
}

/* ---- Aprueba / rechaza la propuesta: SOLO admin o lider dueño ---- */
if ($accion === 'aprobar_cambio_estado' || $accion === 'rechazar_cambio_estado') {
    $id = (int) ($body['id'] ?? 0);
    $tarea = timbra_obtener_tarea_con_proyecto($pdo, $id);

    if (!$tarea) {
        timbra_json(['success' => false, 'message' => 'Tarea no encontrada.'], 404);
    }
    if (!timbra_es_aprobador($usuario, $tarea)) {
        timbra_json(['success' => false, 'message' => 'Solo el líder dueño del proyecto o un admin puede aprobar cambios de estado.'], 403);
    }
    if (empty($tarea['estado_propuesto'])) {
        timbra_json(['success' => false, 'message' => 'Esta tarea no tiene ningún cambio pendiente.'], 422);
    }

    if ($accion === 'aprobar_cambio_estado') {
        $pdo->prepare('
            UPDATE tareas
            SET estado = estado_propuesto, estado_propuesto = NULL, propuesto_por = NULL, propuesto_en = NULL,
                updated_at = datetime("now")
            WHERE id = ?
        ')->execute([$id]);
        timbra_json(['success' => true, 'message' => 'Cambio de estado aprobado.']);
    }

    $pdo->prepare('
        UPDATE tareas SET estado_propuesto = NULL, propuesto_por = NULL, propuesto_en = NULL WHERE id = ?
    ')->execute([$id]);
    timbra_json(['success' => true, 'message' => 'Cambio de estado rechazado.']);
}

/* ---- Cambio directo, sin pasar por aprobación: SOLO admin o lider dueño ---- */
if ($accion === 'cambiar_estado') {
    $id = (int) ($body['id'] ?? 0);
    $estado = (string) ($body['estado'] ?? '');

    if (!in_array($estado, ESTADOS_VALIDOS, true)) {
        timbra_json(['success' => false, 'message' => 'Estado no válido.'], 422);
    }

    $tarea = timbra_obtener_tarea_con_proyecto($pdo, $id);
    if (!$tarea) {
        timbra_json(['success' => false, 'message' => 'Tarea no encontrada.'], 404);
    }
    if (!timbra_es_aprobador($usuario, $tarea)) {
        timbra_json(['success' => false, 'message' => 'No tienes permiso para cambiar el estado de esta tarea directamente.'], 403);
    }

    $pdo->prepare('
        UPDATE tareas
        SET estado = ?, estado_propuesto = NULL, propuesto_por = NULL, propuesto_en = NULL, updated_at = datetime("now")
        WHERE id = ?
    ')->execute([$estado, $id]);

    timbra_json(['success' => true]);
}

/* ---- Editar datos de la tarea: SOLO admin o lider dueño ---- */
if ($accion === 'editar') {
    $id = (int) ($body['id'] ?? 0);
    $tarea = timbra_obtener_tarea_con_proyecto($pdo, $id);
    if (!$tarea) {
        timbra_json(['success' => false, 'message' => 'Tarea no encontrada.'], 404);
    }
    if (!timbra_es_aprobador($usuario, $tarea)) {
        timbra_json(['success' => false, 'message' => 'Solo el líder dueño del proyecto o un admin puede editar esta tarea.'], 403);
    }

    $titulo = trim((string) ($body['titulo'] ?? ''));
    $descripcion = trim((string) ($body['descripcion'] ?? ''));
    $prioridad = in_array($body['prioridad'] ?? '', PRIORIDADES_VALIDAS, true) ? $body['prioridad'] : 'media';

    $errores = [];
    if ($titulo === '') {
        $errores['titulo'] = 'El título es obligatorio.';
    }
    [$fechaInicio, $fechaFin] = timbra_validar_fechas_tarea($body, $errores);

    $asignadoA = !empty($body['asignado_a']) ? (int) $body['asignado_a'] : null;
    if ($asignadoA === null) {
        $errores['asignado_a'] = 'Toda tarea necesita alguien responsable — elige a quién se le asigna.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE id = ? AND activo = 1');
        $stmt->execute([$asignadoA]);
        $existeAsignado = $stmt->fetchColumn();
        if (!$existeAsignado) {
            $errores['asignado_a'] = 'Selecciona un usuario activo.';
        }
    }

    // Mover de etapa es opcional: si mandan etapa_id, debe pertenecer al mismo proyecto.
    $etapaId = (int) $tarea['etapa_id'];
    if (!empty($body['etapa_id'])) {
        $etapaNueva = timbra_obtener_etapa($pdo, (int) $body['etapa_id']);
        if (!$etapaNueva || (int) $etapaNueva['proyecto_id'] !== (int) $tarea['proyecto_id']) {
            $errores['etapa_id'] = 'Etapa no válida para este proyecto.';
        } else {
            $etapaId = (int) $etapaNueva['id'];
        }
    }

    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos de la tarea.', 'errors' => $errores], 422);
    }

    $pdo->prepare('
        UPDATE tareas SET titulo = ?, descripcion = ?, asignado_a = ?, prioridad = ?, fecha_inicio = ?, fecha_fin = ?, etapa_id = ?,
               updated_at = datetime("now")
        WHERE id = ?
    ')->execute([$titulo, $descripcion, $asignadoA, $prioridad, $fechaInicio, $fechaFin, $etapaId, $id]);

    timbra_json(['success' => true, 'message' => 'Tarea actualizada.']);
}

/* ---- Eliminar (borrado suave): SOLO admin o lider dueño ---- */
if ($accion === 'eliminar') {
    $id = (int) ($body['id'] ?? 0);
    $tarea = timbra_obtener_tarea_con_proyecto($pdo, $id);
    if (!$tarea) {
        timbra_json(['success' => false, 'message' => 'Tarea no encontrada.'], 404);
    }
    if (!timbra_es_aprobador($usuario, $tarea)) {
        timbra_json(['success' => false, 'message' => 'Solo el líder dueño del proyecto o un admin puede eliminar esta tarea.'], 403);
    }

    $pdo->prepare('UPDATE tareas SET eliminado = 1 WHERE id = ?')->execute([$id]);
    timbra_json(['success' => true, 'message' => 'Tarea eliminada.']);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
