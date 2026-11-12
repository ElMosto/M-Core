<?php
/**
 * GET  api/ventas.php               -> lista de todas las ventas (visibles para todos)
 * GET  api/ventas.php?id=5           -> detalle de una venta con sus líneas
 * POST api/ventas.php  (cualquier rol)
 *      { cliente_id?, notas?, items: [{ producto_id, cantidad }, ...] } -> registra una venta
 * POST api/ventas.php  { accion: "eliminar", id: 5 }  (solo admin)
 *      -> borra la venta y sus líneas (borrado real, es para corregir un
 *         registro equivocado, no para archivar historial)
 * POST api/ventas.php  { accion: "editar", id: 5, cliente_id?, notas? }  (solo admin)
 *      -> corrige cliente/notas de una venta ya registrada (no toca las
 *         líneas ni el total — para eso, elimina y vuelve a registrar)
 *
 * El precio de cada línea SIEMPRE se toma del catálogo en el momento de la
 * venta (nunca del cliente) para que nadie pueda manipular el total desde
 * el navegador; se guarda como "foto" en venta_items por si el precio del
 * catálogo cambia después.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('ventas');
$pdo = timbra_db();

const SELECT_VENTA = "
    SELECT v.id, v.cliente_id, cl.nombre AS cliente_nombre,
           v.vendido_por, u.nombre AS vendido_por_nombre,
           v.total, v.notas, v.created_at, v.negocio_id,
           n.nombre AS negocio_nombre, n.logo_path AS negocio_logo,
           (SELECT COUNT(*) FROM venta_items vi WHERE vi.venta_id = v.id) AS total_lineas
    FROM ventas v
    JOIN usuarios u ON u.id = v.vendido_por
    LEFT JOIN clientes cl ON cl.id = v.cliente_id
    LEFT JOIN negocios n ON n.id = v.negocio_id
";

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare(SELECT_VENTA . ' WHERE v.id = ?');
    $stmt->execute([$id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$venta) {
        timbra_json(['success' => false, 'message' => 'Venta no encontrada.'], 404);
    }

    $items = $pdo->prepare('SELECT nombre_item, tipo_item, precio_unitario, cantidad, subtotal FROM venta_items WHERE venta_id = ?');
    $items->execute([$id]);
    $venta['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
    timbra_json($venta);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    timbra_json($pdo->query(SELECT_VENTA . ' ORDER BY v.created_at DESC')->fetchAll(PDO::FETCH_ASSOC));
}

$body = timbra_body_json();

if (($body['accion'] ?? '') === 'eliminar') {
    if ($usuario['rol'] !== 'admin') {
        timbra_json(['success' => false, 'message' => 'Solo un administrador puede eliminar una venta.'], 403);
    }

    $id = (int) ($body['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id FROM ventas WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) {
        timbra_json(['success' => false, 'message' => 'Venta no encontrada.'], 404);
    }

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM venta_items WHERE venta_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM ventas WHERE id = ?')->execute([$id]);
    $pdo->commit();

    timbra_json(['success' => true, 'message' => 'Venta eliminada.']);
}

if (($body['accion'] ?? '') === 'editar') {
    if ($usuario['rol'] !== 'admin') {
        timbra_json(['success' => false, 'message' => 'Solo un administrador puede editar una venta.'], 403);
    }

    $id = (int) ($body['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id FROM ventas WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) {
        timbra_json(['success' => false, 'message' => 'Venta no encontrada.'], 404);
    }

    // Edición acotada a cliente y notas — corrige datos de la venta sin
    // reconstruir las líneas ni el total (eso ya se resolvió, y con
    // precios "fotografiados", al momento de registrarla). Si necesitas
    // cambiar los artículos, elimina la venta y regístrala de nuevo.
    $clienteIdRaw = $body['cliente_id'] ?? null;
    $negocioIdRaw = $body['negocio_id'] ?? null;
    $notas = trim((string) ($body['notas'] ?? ''));

    $clienteId = null;
    if (!empty($clienteIdRaw)) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM clientes WHERE id = ? AND activo = 1');
        $stmt->execute([(int) $clienteIdRaw]);
        if (!$stmt->fetchColumn()) {
            timbra_json(['success' => false, 'message' => 'El cliente seleccionado no existe.', 'errors' => ['cliente_id' => 'Cliente no válido.']], 422);
        }
        $clienteId = (int) $clienteIdRaw;
    }

    $negocioId = null;
    if (!empty($negocioIdRaw)) {
        $stmt = $pdo->prepare('SELECT id FROM negocios WHERE id = ? AND activo = 1');
        $stmt->execute([(int) $negocioIdRaw]);
        $negocioId = $stmt->fetchColumn() ?: null;
        if (!$negocioId) {
            timbra_json(['success' => false, 'message' => 'Negocio no válido.'], 422);
        }
    }

    if ($negocioId !== null) {
        $pdo->prepare('UPDATE ventas SET cliente_id = ?, notas = ?, negocio_id = ? WHERE id = ?')
            ->execute([$clienteId, $notas ?: null, $negocioId, $id]);
    } else {
        $pdo->prepare('UPDATE ventas SET cliente_id = ?, notas = ? WHERE id = ?')
            ->execute([$clienteId, $notas ?: null, $id]);
    }

    timbra_json(['success' => true, 'message' => 'Venta actualizada.']);
}

$clienteIdRaw = $body['cliente_id'] ?? null;
$negocioIdRaw = $body['negocio_id'] ?? null;
$notas = trim((string) ($body['notas'] ?? ''));
$items = is_array($body['items'] ?? null) ? $body['items'] : [];

$errores = [];
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
$negocioId = null;
if (!empty($negocioIdRaw)) {
    $stmt = $pdo->prepare('SELECT id FROM negocios WHERE id = ? AND activo = 1');
    $stmt->execute([(int) $negocioIdRaw]);
    if (!$stmt->fetchColumn()) {
        $errores['negocio_id'] = 'El negocio seleccionado no existe o está inactivo.';
    } else {
        $negocioId = (int) $negocioIdRaw;
    }
} else {
    // negocio por defecto
    $negocioId = (int) $pdo->query('SELECT id FROM negocios WHERE activo = 1 ORDER BY id LIMIT 1')->fetchColumn();
}
if (!$items) {
    $errores['items'] = 'Agrega al menos un producto o servicio a la venta.';
}

if ($errores) {
    timbra_json(['success' => false, 'message' => 'Revisa los datos de la venta.', 'errors' => $errores], 422);
}

// Resuelve cada línea contra el catálogo real (precio y nombre "de verdad",
// nunca lo que mande el navegador) y arma la lista final a insertar.
$lineasResueltas = [];
$total = 0.0;
foreach ($items as $linea) {
    $productoId = (int) ($linea['producto_id'] ?? 0);
    $cantidad = is_numeric($linea['cantidad'] ?? null) ? (int) $linea['cantidad'] : 0;

    if ($cantidad < 1) {
        timbra_json(['success' => false, 'message' => 'Cada línea necesita una cantidad de al menos 1.'], 422);
    }

    $stmt = $pdo->prepare('SELECT * FROM productos_servicios WHERE id = ? AND activo = 1');
    $stmt->execute([$productoId]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$producto) {
        timbra_json(['success' => false, 'message' => 'Uno de los productos/servicios ya no está disponible en el catálogo.'], 422);
    }

    $subtotal = round((float) $producto['precio'] * $cantidad, 2);
    $total += $subtotal;
    $lineasResueltas[] = [
        'producto_id'     => $producto['id'],
        'nombre_item'     => $producto['nombre'],
        'tipo_item'       => $producto['tipo'],
        'precio_unitario' => (float) $producto['precio'],
        'cantidad'        => $cantidad,
        'subtotal'        => $subtotal,
    ];
}

$pdo->beginTransaction();
$pdo->prepare('INSERT INTO ventas (cliente_id, vendido_por, total, notas, negocio_id) VALUES (?, ?, ?, ?, ?)')
    ->execute([$clienteId, $usuario['id'], round($total, 2), $notas ?: null, $negocioId]);
$ventaId = (int) $pdo->lastInsertId();

$insertLinea = $pdo->prepare('
    INSERT INTO venta_items (venta_id, producto_id, nombre_item, tipo_item, precio_unitario, cantidad, subtotal)
    VALUES (?, ?, ?, ?, ?, ?, ?)
');
foreach ($lineasResueltas as $l) {
    $insertLinea->execute([
        $ventaId, $l['producto_id'], $l['nombre_item'], $l['tipo_item'],
        $l['precio_unitario'], $l['cantidad'], $l['subtotal'],
    ]);
}
$pdo->commit();

timbra_json(['success' => true, 'message' => 'Venta registrada.', 'id' => $ventaId, 'total' => round($total, 2)]);
