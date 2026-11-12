<?php
/**
 * GET  api/proyectos.php             -> lista de proyectos visibles para el usuario (ver reglas abajo)
 * GET  api/proyectos.php?id=5         -> detalle de un proyecto (o 403/404 si no es visible para el usuario)
 * POST api/proyectos.php  (admin / lider_proyecto)
 *      { nombre, descripcion, tipo, costo, cliente_id, fecha_inicio, fecha_fin_tentativa } -> crea un proyecto
 *
 * Reglas de tipo/costo/cliente:
 *   - tipo = 'interno' -> NO puede tener costo ni cliente (si los mandan, se ignoran/rechazan).
 *   - tipo = 'externo' -> SÍ requiere costo (numérico > 0) Y un cliente registrado.
 *
 * Visibilidad:
 *   - admin:            todos los proyectos.
 *   - lider_proyecto:   solo los proyectos que él mismo creó.
 *   - contador/colaborador: solo proyectos donde tiene al menos una tarea asignada.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('tareas');
$pdo = timbra_db();

const SELECT_PROYECTO = "
    SELECT p.id, p.nombre, p.descripcion, p.fecha_inicio, p.fecha_fin_tentativa,
           p.tipo, p.costo, p.archivado, p.negocio_id,
           p.cliente_id, cl.nombre AS cliente_nombre,
           n.nombre AS negocio_nombre, n.logo_path AS negocio_logo,
           p.creado_por, u.nombre AS creado_por_nombre,
           (SELECT COUNT(*) FROM tareas t WHERE t.proyecto_id = p.id) AS total_tareas,
           (SELECT COUNT(*) FROM tareas t WHERE t.proyecto_id = p.id AND t.estado = 'completada') AS tareas_completadas,
           (SELECT COUNT(*) FROM etapas e WHERE e.proyecto_id = p.id) AS total_etapas,
           (SELECT COALESCE(SUM(ce.monto), 0) FROM costos_extra ce WHERE ce.proyecto_id = p.id) AS total_costos_extra
    FROM proyectos p
    JOIN usuarios u ON u.id = p.creado_por
    LEFT JOIN clientes cl ON cl.id = p.cliente_id
    LEFT JOIN negocios n ON n.id = p.negocio_id
";

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare(SELECT_PROYECTO . ' WHERE p.id = :id');
    $stmt->execute(['id' => $id]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proyecto) {
        timbra_json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
    }
    if (!timbra_puede_ver_proyecto($pdo, $usuario, $proyecto)) {
        timbra_json(['success' => false, 'message' => 'No tienes acceso a este proyecto.'], 403);
    }
    timbra_json($proyecto);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($usuario['rol'] === 'admin') {
        $sql = SELECT_PROYECTO . ' WHERE p.archivado = 0 ORDER BY p.created_at DESC';
        $params = [];
    } elseif ($usuario['rol'] === 'lider_proyecto') {
        $sql = SELECT_PROYECTO . ' WHERE p.archivado = 0 AND p.creado_por = :uid ORDER BY p.created_at DESC';
        $params = ['uid' => $usuario['id']];
    } else { // contador / colaborador: solo proyectos donde tiene tareas asignadas
        $sql = SELECT_PROYECTO . '
            WHERE p.archivado = 0 AND EXISTS (
                SELECT 1 FROM tareas t WHERE t.proyecto_id = p.id AND t.asignado_a = :uid
            ) ORDER BY p.created_at DESC';
        $params = ['uid' => $usuario['id']];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    timbra_json($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* ---- POST: crear / editar / eliminar ---- */
if (!in_array($usuario['rol'], ['admin', 'lider_proyecto'], true)) {
    timbra_json(['success' => false, 'message' => 'Solo un líder de proyecto o admin puede administrar proyectos.'], 403);
}

$body = timbra_body_json();
$accion = $body['accion'] ?? 'crear'; // por compatibilidad: sin "accion" se asume creación

/** Valida nombre/fechas/tipo/costo/cliente. Devuelve [errores, valores_limpios]. */
function timbra_validar_datos_proyecto(PDO $pdo, array $body): array
{
    $nombre = trim((string) ($body['nombre'] ?? ''));
    $descripcion = trim((string) ($body['descripcion'] ?? ''));
    $fechaInicio = trim((string) ($body['fecha_inicio'] ?? '')) ?: null;
    $fechaFin = trim((string) ($body['fecha_fin_tentativa'] ?? '')) ?: null;
    $tipo = (string) ($body['tipo'] ?? '');
    $costoRaw = $body['costo'] ?? null;
    $clienteIdRaw = $body['cliente_id'] ?? null;
    $negocioIdRaw = $body['negocio_id'] ?? null;

    $errores = [];
    if ($nombre === '') {
        $errores['nombre'] = 'El proyecto necesita un nombre.';
    }
    if ($fechaInicio && $fechaFin && $fechaFin < $fechaInicio) {
        $errores['fecha_fin_tentativa'] = 'La fecha de fin no puede ser anterior a la de inicio.';
    }
    if (!in_array($tipo, ['interno', 'externo'], true)) {
        $errores['tipo'] = 'Selecciona si el proyecto es interno o externo.';
    }

    $costo = null;
    $clienteId = null;

    if ($tipo === 'externo') {
        if ($costoRaw === null || $costoRaw === '' || !is_numeric($costoRaw) || (float) $costoRaw <= 0) {
            $errores['costo'] = 'Los proyectos externos (para un cliente) necesitan un costo mayor a cero.';
        } else {
            $costo = (float) $costoRaw;
        }
        if (empty($clienteIdRaw)) {
            $errores['cliente_id'] = 'Selecciona a qué cliente pertenece este proyecto.';
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE id = ? AND activo = 1');
            $stmt->execute([(int) $clienteIdRaw]);
            if (!$stmt->fetchColumn()) {
                $errores['cliente_id'] = 'El cliente seleccionado no existe.';
            } else {
                $clienteId = (int) $clienteIdRaw;
            }
        }
    } elseif ($tipo === 'interno' && $costoRaw !== null && $costoRaw !== '') {
        $errores['costo'] = 'Un proyecto interno no puede tener costo.';
    }

    // Negocio (opcional; si no se manda, se usa el primero activo).
    $negocioId = null;
    if (!empty($negocioIdRaw)) {
        $stmt = $pdo->prepare('SELECT id FROM negocios WHERE id = ? AND activo = 1');
        $stmt->execute([(int) $negocioIdRaw]);
        $negocioId = $stmt->fetchColumn() ?: null;
        if (!$negocioId) {
            $errores['negocio_id'] = 'El negocio seleccionado no existe o está inactivo.';
        }
    } else {
        $negocioId = (int) $pdo->query('SELECT id FROM negocios WHERE activo = 1 ORDER BY id LIMIT 1')->fetchColumn();
    }

    return [$errores, [
        'nombre' => $nombre, 'descripcion' => $descripcion,
        'fecha_inicio' => $fechaInicio, 'fecha_fin_tentativa' => $fechaFin,
        'tipo' => $tipo, 'costo' => $costo, 'cliente_id' => $clienteId,
        'negocio_id' => $negocioId,
    ]];
}

/** admin, o el lider_proyecto dueño de ESE proyecto. */
function timbra_es_dueno_proyecto(array $usuario, array $proyecto): bool
{
    return $usuario['rol'] === 'admin'
        || ($usuario['rol'] === 'lider_proyecto' && (int) $proyecto['creado_por'] === (int) $usuario['id']);
}

if ($accion === 'eliminar') {
    $id = (int) ($body['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM proyectos WHERE id = ?');
    $stmt->execute([$id]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proyecto) {
        timbra_json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
    }
    if (!timbra_es_dueno_proyecto($usuario, $proyecto)) {
        timbra_json(['success' => false, 'message' => 'Solo el líder dueño de este proyecto o un admin puede eliminarlo.'], 403);
    }

    $pdo->prepare('UPDATE proyectos SET archivado = 1 WHERE id = ?')->execute([$id]);
    timbra_json(['success' => true, 'message' => 'Proyecto eliminado.']);
}

if ($accion === 'editar') {
    $id = (int) ($body['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM proyectos WHERE id = ?');
    $stmt->execute([$id]);
    $proyecto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proyecto) {
        timbra_json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
    }
    if (!timbra_es_dueno_proyecto($usuario, $proyecto)) {
        timbra_json(['success' => false, 'message' => 'Solo el líder dueño de este proyecto o un admin puede editarlo.'], 403);
    }

    [$errores, $datos] = timbra_validar_datos_proyecto($pdo, $body);
    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos del proyecto.', 'errors' => $errores], 422);
    }

    $pdo->prepare('
        UPDATE proyectos SET nombre = :nombre, descripcion = :descripcion,
               fecha_inicio = :fecha_inicio, fecha_fin_tentativa = :fecha_fin_tentativa,
               tipo = :tipo, costo = :costo, cliente_id = :cliente_id, negocio_id = :negocio_id
        WHERE id = :id
    ')->execute($datos + ['id' => $id]);

    timbra_json(['success' => true, 'message' => 'Proyecto actualizado.']);
}

/* ---- crear (accion=crear o sin accion, por compatibilidad) ---- */
[$errores, $datos] = timbra_validar_datos_proyecto($pdo, $body);
if ($errores) {
    timbra_json(['success' => false, 'message' => 'Revisa los datos del proyecto.', 'errors' => $errores], 422);
}

$pdo->prepare('
    INSERT INTO proyectos (nombre, descripcion, fecha_inicio, fecha_fin_tentativa, tipo, costo, cliente_id, negocio_id, creado_por)
    VALUES (:nombre, :descripcion, :fecha_inicio, :fecha_fin_tentativa, :tipo, :costo, :cliente_id, :negocio_id, :creado_por)
')->execute($datos + ['creado_por' => $usuario['id']]);

timbra_json(['success' => true, 'message' => 'Proyecto creado.', 'id' => (int) $pdo->lastInsertId()]);
