<?php
/**
 * GET  api/citas.php                              -> mis citas (donde soy participante), sin cancelar
 * GET  api/citas.php?id=5                          -> detalle de una cita (con sus participantes)
 * GET  api/citas.php?invitaciones_pendientes=1      -> citas donde me invitaron y no he respondido
 *
 * POST api/citas.php  accion=crear     { titulo, descripcion, cliente_id, fecha, hora_inicio, hora_fin, invitados: [usuario_id,...] }
 * POST api/citas.php  accion=editar    { id, titulo, descripcion, cliente_id, fecha, hora_inicio, hora_fin }
 * POST api/citas.php  accion=cancelar  { id }
 * POST api/citas.php  accion=responder_invitacion  { cita_id, respuesta: 'aceptado'|'rechazado' }
 *
 * Quien crea una cita queda automáticamente como participante "aceptado".
 * Cualquiera que invites queda "invitado" hasta que él mismo responda —
 * nadie más puede aceptar/rechazar en su nombre, ni siquiera quien la creó.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('agenda');
$pdo = timbra_db();

const SELECT_CITA = "
    SELECT c.id, c.titulo, c.descripcion, c.fecha, c.hora_inicio, c.hora_fin, c.cancelada,
           c.cliente_id, cl.nombre AS cliente_nombre,
           c.creado_por, u.nombre AS creado_por_nombre
    FROM citas c
    JOIN usuarios u ON u.id = c.creado_por
    LEFT JOIN clientes cl ON cl.id = c.cliente_id
";

function timbra_participantes_de_cita(PDO $pdo, int $citaId): array
{
    $stmt = $pdo->prepare('
        SELECT cp.usuario_id, u.nombre, cp.estado
        FROM cita_participantes cp JOIN usuarios u ON u.id = cp.usuario_id
        WHERE cp.cita_id = ?
        ORDER BY u.nombre ASC
    ');
    $stmt->execute([$citaId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['usuarios_invitables'])) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.nombre, r.nombre AS rol_nombre
        FROM usuarios u
        LEFT JOIN roles r ON r.clave = u.rol
        WHERE u.activo = 1 AND u.id != ?
        ORDER BY u.nombre ASC
    ");
    $stmt->execute([$usuario['id']]);
    timbra_json($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare(SELECT_CITA . ' WHERE c.id = :id');
    $stmt->execute(['id' => $id]);
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cita) {
        timbra_json(['success' => false, 'message' => 'Cita no encontrada.'], 404);
    }

    $participantes = timbra_participantes_de_cita($pdo, $id);
    $esParticipante = $usuario['rol'] === 'admin' || in_array($usuario['id'], array_column($participantes, 'usuario_id'), true);
    if (!$esParticipante) {
        timbra_json(['success' => false, 'message' => 'No tienes acceso a esta cita.'], 403);
    }

    $cita['participantes'] = $participantes;
    timbra_json($cita);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['invitaciones_pendientes'])) {
    $stmt = $pdo->prepare(SELECT_CITA . "
        JOIN cita_participantes cp ON cp.cita_id = c.id
        WHERE cp.usuario_id = :uid AND cp.estado = 'invitado' AND c.cancelada = 0
        ORDER BY c.fecha ASC, c.hora_inicio ASC
    ");
    $stmt->execute(['uid' => $usuario['id']]);
    timbra_json($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = SELECT_CITA . "
        JOIN cita_participantes cp ON cp.cita_id = c.id
        WHERE cp.usuario_id = :uid AND c.cancelada = 0
    ";
    $params = ['uid' => $usuario['id']];
    if (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
        $sql .= ' AND c.fecha BETWEEN :desde AND :hasta';
        $params['desde'] = $_GET['desde'];
        $params['hasta'] = $_GET['hasta'];
    }
    $sql .= ' ORDER BY c.fecha ASC, c.hora_inicio ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // le agrega mi_estado (aceptado/invitado/rechazado) a cada una sin otro roundtrip
    $stmtMiEstado = $pdo->prepare('SELECT estado FROM cita_participantes WHERE cita_id = ? AND usuario_id = ?');
    foreach ($citas as &$c) {
        $stmtMiEstado->execute([$c['id'], $usuario['id']]);
        $c['mi_estado'] = $stmtMiEstado->fetchColumn();
    }
    unset($c);

    timbra_json($citas);
}

$body = timbra_body_json();
$accion = $body['accion'] ?? '';

if ($accion === 'crear') {
    $titulo = trim((string) ($body['titulo'] ?? ''));
    $descripcion = trim((string) ($body['descripcion'] ?? ''));
    $fecha = trim((string) ($body['fecha'] ?? ''));
    $horaInicio = trim((string) ($body['hora_inicio'] ?? ''));
    $horaFin = trim((string) ($body['hora_fin'] ?? '')) ?: null;
    $clienteIdRaw = $body['cliente_id'] ?? null;
    $invitados = is_array($body['invitados'] ?? null) ? array_map('intval', $body['invitados']) : [];

    $errores = [];
    if ($titulo === '') $errores['titulo'] = 'El título es obligatorio.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $errores['fecha'] = 'Selecciona una fecha válida.';
    if (!preg_match('/^\d{2}:\d{2}$/', $horaInicio)) $errores['hora_inicio'] = 'Selecciona una hora válida.';
    if ($horaFin && $horaFin < $horaInicio) $errores['hora_fin'] = 'La hora de fin no puede ser antes que la de inicio.';

    $clienteId = null;
    if (!empty($clienteIdRaw)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE id = ? AND activo = 1');
        $stmt->execute([(int) $clienteIdRaw]);
        if (!$stmt->fetchColumn()) {
            $errores['cliente_id'] = 'El cliente seleccionado no existe.';
        } else {
            $clienteId = (int) $clienteIdRaw;
        }
    }

    // Los invitados deben ser usuarios activos reales (y no uno mismo, ya
    // que el creador queda agregado aparte como "aceptado").
    $invitados = array_values(array_unique(array_filter($invitados, fn($id) => $id !== (int) $usuario['id'])));
    if ($invitados) {
        $marcadores = implode(',', array_fill(0, count($invitados), '?'));
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE activo = 1 AND id IN ({$marcadores})");
        $stmt->execute($invitados);
        $invitados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos de la cita.', 'errors' => $errores], 422);
    }

    $pdo->beginTransaction();
    $pdo->prepare('
        INSERT INTO citas (titulo, descripcion, cliente_id, fecha, hora_inicio, hora_fin, creado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ')->execute([$titulo, $descripcion, $clienteId, $fecha, $horaInicio, $horaFin, $usuario['id']]);
    $citaId = (int) $pdo->lastInsertId();

    $insertParticipante = $pdo->prepare('INSERT INTO cita_participantes (cita_id, usuario_id, estado) VALUES (?, ?, ?)');
    $insertParticipante->execute([$citaId, $usuario['id'], 'aceptado']);
    foreach ($invitados as $invitadoId) {
        $insertParticipante->execute([$citaId, $invitadoId, 'invitado']);
    }
    $pdo->commit();

    timbra_json(['success' => true, 'message' => 'Cita registrada.', 'id' => $citaId]);
}

if ($accion === 'editar' || $accion === 'cancelar') {
    $id = (int) ($body['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM citas WHERE id = ?');
    $stmt->execute([$id]);
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cita) {
        timbra_json(['success' => false, 'message' => 'Cita no encontrada.'], 404);
    }
    if ($usuario['rol'] !== 'admin' && (int) $cita['creado_por'] !== (int) $usuario['id']) {
        timbra_json(['success' => false, 'message' => 'Solo quien creó la cita (o un admin) puede modificarla.'], 403);
    }

    if ($accion === 'cancelar') {
        $pdo->prepare('UPDATE citas SET cancelada = 1 WHERE id = ?')->execute([$id]);
        timbra_json(['success' => true, 'message' => 'Cita cancelada.']);
    }

    $titulo = trim((string) ($body['titulo'] ?? ''));
    $descripcion = trim((string) ($body['descripcion'] ?? ''));
    $fecha = trim((string) ($body['fecha'] ?? ''));
    $horaInicio = trim((string) ($body['hora_inicio'] ?? ''));
    $horaFin = trim((string) ($body['hora_fin'] ?? '')) ?: null;
    $clienteIdRaw = $body['cliente_id'] ?? null;

    $errores = [];
    if ($titulo === '') $errores['titulo'] = 'El título es obligatorio.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $errores['fecha'] = 'Selecciona una fecha válida.';
    if (!preg_match('/^\d{2}:\d{2}$/', $horaInicio)) $errores['hora_inicio'] = 'Selecciona una hora válida.';
    if ($horaFin && $horaFin < $horaInicio) $errores['hora_fin'] = 'La hora de fin no puede ser antes que la de inicio.';

    $clienteId = null;
    if (!empty($clienteIdRaw)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE id = ? AND activo = 1');
        $stmt->execute([(int) $clienteIdRaw]);
        if ($stmt->fetchColumn()) {
            $clienteId = (int) $clienteIdRaw;
        }
    }

    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos de la cita.', 'errors' => $errores], 422);
    }

    $pdo->prepare('
        UPDATE citas SET titulo = ?, descripcion = ?, cliente_id = ?, fecha = ?, hora_inicio = ?, hora_fin = ?
        WHERE id = ?
    ')->execute([$titulo, $descripcion, $clienteId, $fecha, $horaInicio, $horaFin, $id]);

    timbra_json(['success' => true, 'message' => 'Cita actualizada.']);
}

if ($accion === 'responder_invitacion') {
    $citaId = (int) ($body['cita_id'] ?? 0);
    $respuesta = (string) ($body['respuesta'] ?? '');

    if (!in_array($respuesta, ['aceptado', 'rechazado'], true)) {
        timbra_json(['success' => false, 'message' => 'Respuesta no válida.'], 422);
    }

    $stmt = $pdo->prepare('SELECT * FROM cita_participantes WHERE cita_id = ? AND usuario_id = ?');
    $stmt->execute([$citaId, $usuario['id']]);
    if (!$stmt->fetch()) {
        timbra_json(['success' => false, 'message' => 'No fuiste invitado a esta cita.'], 403);
    }

    $pdo->prepare('UPDATE cita_participantes SET estado = ? WHERE cita_id = ? AND usuario_id = ?')
        ->execute([$respuesta, $citaId, $usuario['id']]);

    timbra_json(['success' => true, 'message' => $respuesta === 'aceptado' ? 'Invitación aceptada.' : 'Invitación rechazada.']);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
