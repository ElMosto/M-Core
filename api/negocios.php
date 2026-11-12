<?php
/**
 * GET  api/negocios.php                 -> [{ id, nombre, logo_path, activo, created_at }, ...]
 * GET  api/negocios.php?todos=1          -> incluye inactivos (solo admin)
 * POST api/negocios.php  accion=crear     (admin) { nombre }
 * POST api/negocios.php  accion=editar    (admin) { id, nombre }
 * POST api/negocios.php  accion=toggle_activo (admin) { id }
 * POST api/negocios.php  accion=subir_logo (admin, multipart) { negocio_id, logo }
 * POST api/negocios.php  accion=quitar_logo (admin) { id }
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

// Lectura disponible para cualquier usuario autenticado (selector en ventas/proyectos).
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $usuario = timbra_usuario_actual();
    if (!$usuario) {
        timbra_json(['success' => false, 'message' => 'Debes iniciar sesión.'], 401);
    }
    $soloActivos = empty($_GET['todos']) || $usuario['rol'] !== 'admin';
    $sql = 'SELECT id, nombre, logo_path, activo, created_at FROM negocios ' . ($soloActivos ? 'WHERE activo = 1 ' : '') . 'ORDER BY nombre ASC';
    timbra_json(timbra_db()->query($sql)->fetchAll(PDO::FETCH_ASSOC));
}

$usuarioActual = timbra_requerir_admin(true);
$pdo = timbra_db();

$body = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'subir_logo') {
    $accion = 'subir_logo';
    $body = $_POST;
} else {
    $body = timbra_body_json();
    $accion = $body['accion'] ?? '';
}

if ($accion === 'crear') {
    $nombre = trim((string) ($body['nombre'] ?? ''));
    if ($nombre === '') {
        timbra_json(['success' => false, 'message' => 'El nombre es obligatorio.'], 422);
    }
    $pdo->prepare('INSERT INTO negocios (nombre, creado_por) VALUES (?, ?)')->execute([$nombre, $usuarioActual['id']]);
    timbra_json(['success' => true, 'message' => 'Negocio creado.', 'id' => (int) $pdo->lastInsertId()]);
}

if ($accion === 'editar') {
    $id = (int) ($body['id'] ?? 0);
    $nombre = trim((string) ($body['nombre'] ?? ''));
    if ($id <= 0 || $nombre === '') {
        timbra_json(['success' => false, 'message' => 'Datos inválidos.'], 422);
    }
    $pdo->prepare('UPDATE negocios SET nombre = ? WHERE id = ?')->execute([$nombre, $id]);
    timbra_json(['success' => true, 'message' => 'Negocio actualizado.']);
}

if ($accion === 'toggle_activo') {
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        timbra_json(['success' => false, 'message' => 'ID inválido.'], 422);
    }
    $pdo->prepare('UPDATE negocios SET activo = 1 - activo WHERE id = ?')->execute([$id]);
    timbra_json(['success' => true]);
}

if ($accion === 'subir_logo') {
    $negocioId = (int) ($body['negocio_id'] ?? 0);
    $archivo = $_FILES['logo'] ?? null;
    if ($negocioId <= 0) {
        timbra_json(['success' => false, 'message' => 'Negocio inválido.'], 422);
    }
    if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
        timbra_json(['success' => false, 'message' => 'Sube un archivo de imagen.'], 422);
    }
    if ($archivo['size'] > TIMBRA_LOGO_MAX_BYTES) {
        timbra_json(['success' => false, 'message' => 'El logo es demasiado pesado (máx. 1 MB).'], 422);
    }
    $mime = @mime_content_type($archivo['tmp_name']) ?: '';
    $ext = TIMBRA_AVATAR_MIME_PERMITIDOS[$mime] ?? null;
    if (!$ext) {
        timbra_json(['success' => false, 'message' => 'Formato no permitido (JPG, PNG, WEBP).'], 422);
    }
    $dir = TIMBRA_ROOT . '/assets/negocios';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $anterior = $pdo->prepare('SELECT logo_path FROM negocios WHERE id = ?');
    $anterior->execute([$negocioId]);
    $prev = $anterior->fetchColumn();
    if ($prev && is_file(TIMBRA_ROOT . '/' . $prev)) {
        @unlink(TIMBRA_ROOT . '/' . $prev);
    }
    $nombreArchivo = 'negocio-' . $negocioId . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $rutaDestino = $dir . '/' . $nombreArchivo;
    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        timbra_json(['success' => false, 'message' => 'No se pudo guardar el logo.'], 500);
    }
    chmod($rutaDestino, 0644);
    $rutaPublica = 'assets/negocios/' . $nombreArchivo;
    $pdo->prepare('UPDATE negocios SET logo_path = ? WHERE id = ?')->execute([$rutaPublica, $negocioId]);
    timbra_json(['success' => true, 'message' => 'Logo actualizado.', 'logo_path' => $rutaPublica]);
}

if ($accion === 'quitar_logo') {
    $id = (int) ($body['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT logo_path FROM negocios WHERE id = ?');
    $stmt->execute([$id]);
    $prev = $stmt->fetchColumn();
    if ($prev && is_file(TIMBRA_ROOT . '/' . $prev)) {
        @unlink(TIMBRA_ROOT . '/' . $prev);
    }
    $pdo->prepare('UPDATE negocios SET logo_path = NULL WHERE id = ?')->execute([$id]);
    timbra_json(['success' => true, 'message' => 'Logo quitado.']);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
