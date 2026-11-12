<?php
/**
 * GET  api/perfil.php                          -> { id, nombre, email, rol, foto_path }
 * POST api/perfil.php  (multipart/form-data)
 *      accion=actualizar_perfil  { nombre, foto? }        -> actualiza nombre y, si se manda, la foto
 * POST api/perfil.php  (JSON)
 *      accion=cambiar_password  { password_actual, password_nueva, password_nueva_confirmar }
 *
 * Cualquier usuario autenticado administra SU PROPIO perfil — no hay
 * parámetro de "a quién" editar, siempre es el dueño de la sesión.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('perfil');
$pdo = timbra_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT id, nombre, email, rol, foto_path FROM usuarios WHERE id = ?');
    $stmt->execute([$usuario['id']]);
    $datos = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$datos) {
        timbra_json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
    }
    timbra_json($datos);
}

// El envío de foto obliga a multipart/form-data, así que "accion" puede
// venir en $_POST (multipart) o en el body JSON (cambio de password).
if (!empty($_POST)) {
    $accion = $_POST['accion'] ?? '';
    $body = $_POST;
} else {
    $body = timbra_body_json();
    $accion = $body['accion'] ?? '';
}

if ($accion === 'actualizar_perfil') {
    $nombre = trim((string) ($body['nombre'] ?? ''));
    if ($nombre === '') {
        timbra_json(['success' => false, 'message' => 'El nombre no puede quedar vacío.', 'errors' => ['nombre' => 'Obligatorio.']], 422);
    }

    $fotoPath = null; // null = no tocar la foto actual

    if (!empty($_FILES['foto']['name'])) {
        $archivo = $_FILES['foto'];
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            timbra_json(['success' => false, 'message' => 'No se pudo subir la foto.', 'errors' => ['foto' => 'Error al subir el archivo.']], 422);
        }
        if ($archivo['size'] > TIMBRA_AVATAR_MAX_BYTES) {
            timbra_json(['success' => false, 'message' => 'La foto es demasiado pesada.', 'errors' => ['foto' => 'Máximo 2 MB.']], 422);
        }

        $mime = @mime_content_type($archivo['tmp_name']) ?: '';
        $ext = TIMBRA_AVATAR_MIME_PERMITIDOS[$mime] ?? null;
        if (!$ext) {
            timbra_json(['success' => false, 'message' => 'Formato de imagen no permitido.', 'errors' => ['foto' => 'Usa JPG, PNG o WEBP.']], 422);
        }

        if (!is_dir(TIMBRA_AVATAR_DIR)) {
            mkdir(TIMBRA_AVATAR_DIR, 0775, true);
        }

        $nombreArchivo = 'user-' . $usuario['id'] . '-' . time() . '.' . $ext;
        $rutaDestino = TIMBRA_AVATAR_DIR . '/' . $nombreArchivo;
        if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            timbra_json(['success' => false, 'message' => 'No se pudo guardar la foto.'], 500);
        }

        // Borra la foto anterior (si existía) para no acumular archivos huérfanos.
        $stmt = $pdo->prepare('SELECT foto_path FROM usuarios WHERE id = ?');
        $stmt->execute([$usuario['id']]);
        $anterior = $stmt->fetchColumn();
        if ($anterior) {
            $rutaAnterior = TIMBRA_ROOT . '/' . ltrim($anterior, '/');
            if (is_file($rutaAnterior)) {
                @unlink($rutaAnterior);
            }
        }

        $fotoPath = 'assets/avatars/' . $nombreArchivo;
    }

    if ($fotoPath !== null) {
        $pdo->prepare('UPDATE usuarios SET nombre = ?, foto_path = ? WHERE id = ?')
            ->execute([$nombre, $fotoPath, $usuario['id']]);
    } else {
        $pdo->prepare('UPDATE usuarios SET nombre = ? WHERE id = ?')
            ->execute([$nombre, $usuario['id']]);
    }

    // Refresca la sesión para que el nombre/foto se vean actualizados de inmediato.
    timbra_iniciar_sesion();
    $_SESSION['usuario']['nombre'] = $nombre;
    $_SESSION['usuario']['foto_path'] = $fotoPath ?? ($_SESSION['usuario']['foto_path'] ?? null);

    timbra_json(['success' => true, 'message' => 'Perfil actualizado.', 'foto_path' => $_SESSION['usuario']['foto_path']]);
}

if ($accion === 'cambiar_password') {
    $actual = (string) ($body['password_actual'] ?? '');
    $nueva = (string) ($body['password_nueva'] ?? '');
    $confirmar = (string) ($body['password_nueva_confirmar'] ?? '');

    $stmt = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
    $stmt->execute([$usuario['id']]);
    $hashActual = $stmt->fetchColumn();

    $errores = [];
    if (!$hashActual || !password_verify($actual, $hashActual)) {
        $errores['password_actual'] = 'La contraseña actual no es correcta.';
    }
    if (strlen($nueva) < 10) {
        $errores['password_nueva'] = 'Usa al menos 8 caracteres.';
    } elseif ($nueva !== $confirmar) {
        $errores['password_nueva_confirmar'] = 'No coincide con la nueva contraseña.';
    }

    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos de la contraseña.', 'errors' => $errores], 422);
    }

    $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($nueva, PASSWORD_DEFAULT), $usuario['id']]);

    timbra_json(['success' => true, 'message' => 'Contraseña actualizada.']);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
