<?php
/**
 * GET  api/inventario-solicitudes.php               -> mis solicitudes (cualquier rol)
 * GET  api/inventario-solicitudes.php?todas=1         -> TODAS las solicitudes (solo admin)
 * GET  api/inventario-solicitudes.php?pendientes=1     -> solo las pendientes de aprobar (solo admin)
 *
 * POST api/inventario-solicitudes.php  accion=solicitar        (cualquier rol)  { item_id, cantidad, notas }
 * POST api/inventario-solicitudes.php  accion=aprobar           (solo admin)     { id }
 * POST api/inventario-solicitudes.php  accion=rechazar          (solo admin)     { id }
 * POST api/inventario-solicitudes.php  accion=marcar_devuelto   (solo admin)     { id }
 *
 * Control de existencias: al aprobar se descuenta cantidad_disponible del
 * artículo (validando que alcance); al marcar como devuelto se regresa esa
 * cantidad al disponible. Rechazar no toca existencias porque nunca se
 * habían descontado.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('inventario');
$pdo = timbra_db();

const SELECT_SOLICITUD = "
    SELECT s.id, s.item_id, i.nombre AS item_nombre,
           s.solicitado_por, us.nombre AS solicitado_por_nombre,
           s.cantidad, s.estado, s.fecha_solicitud,
           s.aprobado_por, ua.nombre AS aprobado_por_nombre, s.fecha_aprobacion,
           s.devuelto, s.fecha_devolucion, s.notas
    FROM inventario_solicitudes s
    JOIN inventario_items i ON i.id = s.item_id
    JOIN usuarios us ON us.id = s.solicitado_por
    LEFT JOIN usuarios ua ON ua.id = s.aprobado_por
";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['todas']) || !empty($_GET['pendientes'])) {
        if ($usuario['rol'] !== 'admin') {
            timbra_json(['success' => false, 'message' => 'Solo un administrador puede ver todas las solicitudes.'], 403);
        }
        $sql = SELECT_SOLICITUD;
        if (!empty($_GET['pendientes'])) {
            $sql .= " WHERE s.estado = 'pendiente'";
        }
        $sql .= ' ORDER BY s.fecha_solicitud DESC';
        timbra_json($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
    }

    $stmt = $pdo->prepare(SELECT_SOLICITUD . ' WHERE s.solicitado_por = ? ORDER BY s.fecha_solicitud DESC');
    $stmt->execute([$usuario['id']]);
    timbra_json($stmt->fetchAll(PDO::FETCH_ASSOC));
}

$body = timbra_body_json();
$accion = $body['accion'] ?? '';

if ($accion === 'solicitar') {
    $itemId = (int) ($body['item_id'] ?? 0);
    $cantidad = is_numeric($body['cantidad'] ?? null) ? (int) $body['cantidad'] : 0;
    $notas = trim((string) ($body['notas'] ?? ''));

    $stmt = $pdo->prepare('SELECT * FROM inventario_items WHERE id = ? AND activo = 1');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    $errores = [];
    if (!$item) {
        $errores['item_id'] = 'Selecciona un artículo válido.';
    }
    if ($cantidad < 1) {
        $errores['cantidad'] = 'La cantidad debe ser al menos 1.';
    }
    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos de la solicitud.', 'errors' => $errores], 422);
    }

    $pdo->prepare('
        INSERT INTO inventario_solicitudes (item_id, solicitado_por, cantidad, notas)
        VALUES (?, ?, ?, ?)
    ')->execute([$itemId, $usuario['id'], $cantidad, $notas ?: null]);

    timbra_json(['success' => true, 'message' => 'Solicitud enviada — queda pendiente de aprobación.']);
}

if (!in_array($accion, ['aprobar', 'rechazar', 'marcar_devuelto'], true)) {
    timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
}

if ($usuario['rol'] !== 'admin') {
    timbra_json(['success' => false, 'message' => 'Solo un administrador puede hacer esto.'], 403);
}

$id = (int) ($body['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM inventario_solicitudes WHERE id = ?');
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    timbra_json(['success' => false, 'message' => 'Solicitud no encontrada.'], 404);
}

if ($accion === 'aprobar') {
    if ($solicitud['estado'] !== 'pendiente') {
        timbra_json(['success' => false, 'message' => 'Esta solicitud ya fue resuelta.'], 422);
    }
    $itemStmt = $pdo->prepare('SELECT * FROM inventario_items WHERE id = ?');
    $itemStmt->execute([$solicitud['item_id']]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item || (int) $item['cantidad_disponible'] < (int) $solicitud['cantidad']) {
        timbra_json(['success' => false, 'message' => 'No hay suficiente disponible de este artículo para aprobar.'], 422);
    }

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE inventario_items SET cantidad_disponible = cantidad_disponible - ? WHERE id = ?')
        ->execute([(int) $solicitud['cantidad'], $item['id']]);
    $pdo->prepare('
        UPDATE inventario_solicitudes SET estado = "aprobado", aprobado_por = ?, fecha_aprobacion = datetime("now")
        WHERE id = ?
    ')->execute([$usuario['id'], $id]);
    $pdo->commit();

    timbra_json(['success' => true, 'message' => 'Solicitud aprobada.']);
}

if ($accion === 'rechazar') {
    if ($solicitud['estado'] !== 'pendiente') {
        timbra_json(['success' => false, 'message' => 'Esta solicitud ya fue resuelta.'], 422);
    }
    $pdo->prepare('
        UPDATE inventario_solicitudes SET estado = "rechazado", aprobado_por = ?, fecha_aprobacion = datetime("now")
        WHERE id = ?
    ')->execute([$usuario['id'], $id]);

    timbra_json(['success' => true, 'message' => 'Solicitud rechazada.']);
}

if ($accion === 'marcar_devuelto') {
    if ($solicitud['estado'] !== 'aprobado') {
        timbra_json(['success' => false, 'message' => 'Solo se puede marcar como devuelto un préstamo ya aprobado.'], 422);
    }
    if ((int) $solicitud['devuelto'] === 1) {
        timbra_json(['success' => false, 'message' => 'Este préstamo ya estaba marcado como devuelto.'], 422);
    }

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE inventario_items SET cantidad_disponible = cantidad_disponible + ? WHERE id = ?')
        ->execute([(int) $solicitud['cantidad'], $solicitud['item_id']]);
    $pdo->prepare('
        UPDATE inventario_solicitudes SET devuelto = 1, fecha_devolucion = datetime("now") WHERE id = ?
    ')->execute([$id]);
    $pdo->commit();

    timbra_json(['success' => true, 'message' => 'Préstamo marcado como devuelto.']);
}
