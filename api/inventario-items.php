<?php
/**
 * GET  api/inventario-items.php               -> [{ id, nombre, descripcion, cantidad_total, cantidad_disponible, activo }, ...]
 * POST api/inventario-items.php  accion=crear   (admin)  { nombre, descripcion, cantidad_total }
 * POST api/inventario-items.php  accion=editar  (admin)  { id, nombre, descripcion, cantidad_total }
 * POST api/inventario-items.php  accion=baja    (admin)  { id }
 *
 * El catálogo (GET) lo puede ver cualquiera con acceso al módulo — todos
 * necesitan verlo para poder solicitar artículos. Alta/edición/baja son
 * exclusivas de admin.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('inventario');
$pdo = timbra_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $soloActivos = empty($_GET['todos']) || $usuario['rol'] !== 'admin'; // solo admin puede ver dados de baja
    $sql = "
        SELECT id, nombre, descripcion, cantidad_total, cantidad_disponible, activo
        FROM inventario_items
        " . ($soloActivos ? 'WHERE activo = 1' : '') . "
        ORDER BY nombre ASC
    ";
    timbra_json($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
}

if ($usuario['rol'] !== 'admin') {
    timbra_json(['success' => false, 'message' => 'Solo un administrador puede administrar el inventario.'], 403);
}

$body = timbra_body_json();
$accion = $body['accion'] ?? '';

if ($accion === 'crear') {
    $nombre = trim((string) ($body['nombre'] ?? ''));
    $descripcion = trim((string) ($body['descripcion'] ?? ''));
    $cantidadTotal = $body['cantidad_total'] ?? null;

    $errores = [];
    if ($nombre === '') {
        $errores['nombre'] = 'El artículo necesita un nombre.';
    }
    if (!is_numeric($cantidadTotal) || (int) $cantidadTotal < 0) {
        $errores['cantidad_total'] = 'Captura una cantidad válida (0 o más).';
    }
    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos del artículo.', 'errors' => $errores], 422);
    }

    $pdo->prepare('
        INSERT INTO inventario_items (nombre, descripcion, cantidad_total, cantidad_disponible, creado_por)
        VALUES (?, ?, ?, ?, ?)
    ')->execute([$nombre, $descripcion, (int) $cantidadTotal, (int) $cantidadTotal, $usuario['id']]);

    timbra_json(['success' => true, 'message' => 'Artículo dado de alta.', 'id' => (int) $pdo->lastInsertId()]);
}

if ($accion === 'editar') {
    $id = (int) ($body['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM inventario_items WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        timbra_json(['success' => false, 'message' => 'Artículo no encontrado.'], 404);
    }

    $nombre = trim((string) ($body['nombre'] ?? ''));
    $descripcion = trim((string) ($body['descripcion'] ?? ''));
    $cantidadTotalNueva = $body['cantidad_total'] ?? null;

    $errores = [];
    if ($nombre === '') {
        $errores['nombre'] = 'El artículo necesita un nombre.';
    }
    if (!is_numeric($cantidadTotalNueva) || (int) $cantidadTotalNueva < 0) {
        $errores['cantidad_total'] = 'Captura una cantidad válida (0 o más).';
    } else {
        // Cuántas unidades están prestadas ahora mismo (no pueden "desaparecer" al reducir el total).
        $prestadas = (int) $item['cantidad_total'] - (int) $item['cantidad_disponible'];
        $disponibleNueva = (int) $cantidadTotalNueva - $prestadas;
        if ($disponibleNueva < 0) {
            $errores['cantidad_total'] = "No puedes bajar de {$prestadas} — es lo que hay prestado ahora mismo sin devolver.";
        }
    }
    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos del artículo.', 'errors' => $errores], 422);
    }

    $prestadas = (int) $item['cantidad_total'] - (int) $item['cantidad_disponible'];
    $disponibleNueva = (int) $cantidadTotalNueva - $prestadas;

    $pdo->prepare('
        UPDATE inventario_items SET nombre = ?, descripcion = ?, cantidad_total = ?, cantidad_disponible = ?
        WHERE id = ?
    ')->execute([$nombre, $descripcion, (int) $cantidadTotalNueva, $disponibleNueva, $id]);

    timbra_json(['success' => true, 'message' => 'Artículo actualizado.']);
}

if ($accion === 'baja') {
    $id = (int) ($body['id'] ?? 0);
    $pdo->prepare('UPDATE inventario_items SET activo = 0 WHERE id = ?')->execute([$id]);
    timbra_json(['success' => true, 'message' => 'Artículo dado de baja.']);
}

if ($accion === 'reactivar') {
    $id = (int) ($body['id'] ?? 0);
    $pdo->prepare('UPDATE inventario_items SET activo = 1 WHERE id = ?')->execute([$id]);
    timbra_json(['success' => true, 'message' => 'Artículo reactivado.']);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
