<?php
/**
 * GET  api/productos-servicios.php            -> catálogo activo (lo necesita cualquiera con acceso a Ventas)
 * GET  api/productos-servicios.php?todos=1     -> incluye los dados de baja (uso interno de la pantalla admin)
 * POST api/productos-servicios.php  (admin)
 *      accion=crear   { nombre, descripcion, tipo, precio }
 *      accion=editar  { id, nombre, descripcion, tipo, precio }
 *      accion=baja      { id }
 *      accion=reactivar { id }
 *
 * El catálogo se lee a través del módulo "ventas" (abierto a todos), no de
 * "productos_servicios" (exclusivo de admin) — quien registra una venta
 * necesita ver los precios aunque no pueda administrarlos.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('ventas');
$pdo = timbra_db();

const TIPOS_PRODUCTO_SERVICIO = ['producto', 'servicio'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $soloActivos = empty($_GET['todos']);
    $sql = 'SELECT id, nombre, descripcion, tipo, precio, activo FROM productos_servicios'
        . ($soloActivos ? ' WHERE activo = 1' : '')
        . ' ORDER BY nombre ASC';
    timbra_json($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
}

if ($usuario['rol'] !== 'admin') {
    timbra_json(['success' => false, 'message' => 'Solo un administrador puede administrar el catálogo.'], 403);
}

$body = timbra_body_json();
$accion = $body['accion'] ?? '';

if ($accion === 'crear' || $accion === 'editar') {
    $nombre = trim((string) ($body['nombre'] ?? ''));
    $descripcion = trim((string) ($body['descripcion'] ?? ''));
    $tipo = (string) ($body['tipo'] ?? '');
    $precioRaw = $body['precio'] ?? null;

    $errores = [];
    if ($nombre === '') {
        $errores['nombre'] = 'El nombre es obligatorio.';
    }
    if (!in_array($tipo, TIPOS_PRODUCTO_SERVICIO, true)) {
        $errores['tipo'] = 'Selecciona si es producto o servicio.';
    }
    if (!is_numeric($precioRaw) || (float) $precioRaw < 0) {
        $errores['precio'] = 'Captura un precio válido (0 o más).';
    }
    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos.', 'errors' => $errores], 422);
    }

    if ($accion === 'crear') {
        $pdo->prepare('
            INSERT INTO productos_servicios (nombre, descripcion, tipo, precio, creado_por)
            VALUES (?, ?, ?, ?, ?)
        ')->execute([$nombre, $descripcion, $tipo, (float) $precioRaw, $usuario['id']]);
        timbra_json(['success' => true, 'message' => 'Registrado en el catálogo.', 'id' => (int) $pdo->lastInsertId()]);
    }

    $id = (int) ($body['id'] ?? 0);
    $pdo->prepare('UPDATE productos_servicios SET nombre = ?, descripcion = ?, tipo = ?, precio = ? WHERE id = ?')
        ->execute([$nombre, $descripcion, $tipo, (float) $precioRaw, $id]);
    timbra_json(['success' => true, 'message' => 'Actualizado.']);
}

if ($accion === 'baja') {
    $pdo->prepare('UPDATE productos_servicios SET activo = 0 WHERE id = ?')->execute([(int) ($body['id'] ?? 0)]);
    timbra_json(['success' => true, 'message' => 'Dado de baja del catálogo.']);
}

if ($accion === 'reactivar') {
    $pdo->prepare('UPDATE productos_servicios SET activo = 1 WHERE id = ?')->execute([(int) ($body['id'] ?? 0)]);
    timbra_json(['success' => true, 'message' => 'Reactivado.']);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
