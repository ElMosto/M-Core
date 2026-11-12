<?php
/**
 * GET  api/usuarios.php                 -> [{ id, nombre, email, rol, activo, created_at }, ...]
 * POST api/usuarios.php  accion=crear         { nombre, email, password, rol }
 * POST api/usuarios.php  accion=editar        { id, nombre, email, password? }  (password opcional: reset)
 * POST api/usuarios.php  accion=toggle_activo { id }
 * POST api/usuarios.php  accion=cambiar_rol   { id, rol }
 * Todo este endpoint es exclusivo del rol admin.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuarioActual = timbra_requerir_admin(true);
$pdo = timbra_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $usuarios = $pdo->query('SELECT id, nombre, email, rol, activo, created_at FROM usuarios ORDER BY created_at ASC')
        ->fetchAll(PDO::FETCH_ASSOC);
    timbra_json($usuarios);
}

$body = timbra_body_json();
$accion = $body['accion'] ?? '';

if ($accion === 'crear') {
    $nombre = trim((string) ($body['nombre'] ?? ''));
    $email = strtolower(trim((string) ($body['email'] ?? '')));
    $password = (string) ($body['password'] ?? '');
    $rol = (string) ($body['rol'] ?? '');

    $errores = [];
    if ($nombre === '') $errores['nombre'] = 'El nombre es obligatorio.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores['email'] = 'Correo no válido.';
    if (strlen($password) < 10) $errores['password'] = 'Usa al menos 10 caracteres.';
    if (!timbra_clave_rol_existe($pdo, $rol)) $errores['rol'] = 'Selecciona un rol válido.';

    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos del usuario.', 'errors' => $errores], 422);
    }

    $existe = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
    $existe->execute([$email]);
    if ((int) $existe->fetchColumn() > 0) {
        timbra_json(['success' => false, 'message' => 'Ya existe un usuario con ese correo.',
            'errors' => ['email' => 'Correo ya registrado.']], 422);
    }

    $pdo->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)')
        ->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT), $rol]);

    timbra_json(['success' => true, 'message' => 'Usuario creado correctamente.']);
}

if ($accion === 'toggle_activo') {
    $id = (int) ($body['id'] ?? 0);
    if ($id === (int) $usuarioActual['id']) {
        timbra_json(['success' => false, 'message' => 'No puedes desactivar tu propia cuenta.'], 422);
    }
    $pdo->prepare('UPDATE usuarios SET activo = 1 - activo WHERE id = ?')->execute([$id]);
    timbra_json(['success' => true]);
}

if ($accion === 'cambiar_rol') {
    $id = (int) ($body['id'] ?? 0);
    $rol = (string) ($body['rol'] ?? '');
    if (!timbra_clave_rol_existe($pdo, $rol)) {
        timbra_json(['success' => false, 'message' => 'Rol no válido.'], 422);
    }
    if ($id === (int) $usuarioActual['id'] && $rol !== 'admin') {
        timbra_json(['success' => false, 'message' => 'No puedes quitarte el rol de admin a ti mismo.'], 422);
    }
    $pdo->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')->execute([$rol, $id]);
    timbra_json(['success' => true]);
}

if ($accion === 'editar') {
    $id = (int) ($body['id'] ?? 0);
    $nombre = trim((string) ($body['nombre'] ?? ''));
    $email = strtolower(trim((string) ($body['email'] ?? '')));
    $passwordNueva = (string) ($body['password'] ?? ''); // opcional: solo si el admin quiere resetearla

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) {
        timbra_json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
    }

    $errores = [];
    if ($nombre === '') $errores['nombre'] = 'El nombre es obligatorio.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores['email'] = 'Correo no válido.';
    if ($passwordNueva !== '' && strlen($passwordNueva) < 10) $errores['password'] = 'Usa al menos 10 caracteres, o déjalo en blanco para no cambiarla.';

    if (!$errores) {
        $existe = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ? AND id != ?');
        $existe->execute([$email, $id]);
        if ((int) $existe->fetchColumn() > 0) {
            $errores['email'] = 'Ya hay otro usuario con ese correo.';
        }
    }

    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos del usuario.', 'errors' => $errores], 422);
    }

    if ($passwordNueva !== '') {
        $pdo->prepare('UPDATE usuarios SET nombre = ?, email = ?, password_hash = ? WHERE id = ?')
            ->execute([$nombre, $email, password_hash($passwordNueva, PASSWORD_DEFAULT), $id]);
    } else {
        $pdo->prepare('UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?')
            ->execute([$nombre, $email, $id]);
    }

    timbra_json(['success' => true, 'message' => 'Usuario actualizado.']);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
