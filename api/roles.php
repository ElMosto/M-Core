<?php
/**
 * GET  api/roles.php                         -> [{ id, clave, nombre, es_sistema, num_usuarios, modulos: [...] }, ...]
 * POST api/roles.php  accion=crear             { clave, nombre, modulos: [...] }
 * POST api/roles.php  accion=editar_permisos    { id, modulos: [...] }
 * POST api/roles.php  accion=renombrar          { id, nombre }
 * POST api/roles.php  accion=eliminar           { id }
 *
 * Exclusivo de admin (timbra_requerir_admin(), igual que usuarios.php — no
 * pasa por el sistema de módulos asignables, así ningún rol puede
 * concederse a sí mismo acceso aquí).
 *
 * `clave` es inmutable una vez creado el rol (los usuarios ya guardan esa
 * clave en su columna `rol`; cambiarla los dejaría huérfanos). Lo único
 * editable después de crear un rol es su nombre y sus módulos.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuarioActual = timbra_requerir_admin(true);
$pdo = timbra_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $roles = $pdo->query('SELECT id, clave, nombre, es_sistema FROM roles ORDER BY es_sistema DESC, nombre ASC')
        ->fetchAll(PDO::FETCH_ASSOC);

    $stmtModulos = $pdo->prepare('SELECT modulo FROM rol_modulos WHERE rol_id = ?');
    $stmtUsuarios = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE rol = ?');

    foreach ($roles as &$r) {
        $stmtModulos->execute([$r['id']]);
        $r['modulos'] = $stmtModulos->fetchAll(PDO::FETCH_COLUMN);
        $stmtUsuarios->execute([$r['clave']]);
        $r['num_usuarios'] = (int) $stmtUsuarios->fetchColumn();
    }
    unset($r);

    timbra_json($roles);
}

$body = timbra_body_json();
$accion = $body['accion'] ?? '';

/** Filtra una lista de módulos contra el catálogo de módulos asignables. */
function timbra_filtrar_modulos_validos(array $modulos): array
{
    return array_values(array_intersect($modulos, array_keys(TIMBRA_MODULOS_ASIGNABLES)));
}

if ($accion === 'crear') {
    $clave = strtolower(trim((string) ($body['clave'] ?? '')));
    $nombre = trim((string) ($body['nombre'] ?? ''));
    $modulos = timbra_filtrar_modulos_validos(is_array($body['modulos'] ?? null) ? $body['modulos'] : []);

    $errores = [];
    if (!preg_match('/^[a-z][a-z0-9_]{2,30}$/', $clave)) {
        $errores['clave'] = 'Usa minúsculas, números y guion bajo, empezando con una letra (3-31 caracteres).';
    } elseif (timbra_clave_rol_existe($pdo, $clave)) {
        $errores['clave'] = 'Ya existe un rol con esa clave.';
    }
    if ($nombre === '') {
        $errores['nombre'] = 'El rol necesita un nombre.';
    }
    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos del rol.', 'errors' => $errores], 422);
    }

    $pdo->prepare('INSERT INTO roles (clave, nombre, es_sistema) VALUES (?, ?, 0)')->execute([$clave, $nombre]);
    $rolId = (int) $pdo->lastInsertId();

    $insertModulo = $pdo->prepare('INSERT INTO rol_modulos (rol_id, modulo) VALUES (?, ?)');
    foreach ($modulos as $modulo) {
        $insertModulo->execute([$rolId, $modulo]);
    }

    timbra_json(['success' => true, 'message' => 'Rol creado.', 'id' => $rolId]);
}

if ($accion === 'editar_permisos') {
    $id = (int) ($body['id'] ?? 0);
    $modulos = timbra_filtrar_modulos_validos(is_array($body['modulos'] ?? null) ? $body['modulos'] : []);

    $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ?');
    $stmt->execute([$id]);
    $rol = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$rol) {
        timbra_json(['success' => false, 'message' => 'Rol no encontrado.'], 404);
    }
    if ($rol['clave'] === 'admin') {
        timbra_json(['success' => false, 'message' => 'El rol admin siempre tiene acceso a todo — no tiene permisos que editar.'], 422);
    }

    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM rol_modulos WHERE rol_id = ?')->execute([$id]);
    $insertModulo = $pdo->prepare('INSERT INTO rol_modulos (rol_id, modulo) VALUES (?, ?)');
    foreach ($modulos as $modulo) {
        $insertModulo->execute([$id, $modulo]);
    }
    $pdo->commit();

    timbra_json(['success' => true, 'message' => 'Permisos actualizados.']);
}

if ($accion === 'renombrar') {
    $id = (int) ($body['id'] ?? 0);
    $nombre = trim((string) ($body['nombre'] ?? ''));
    if ($nombre === '') {
        timbra_json(['success' => false, 'message' => 'El nombre no puede quedar vacío.', 'errors' => ['nombre' => 'Obligatorio.']], 422);
    }
    $pdo->prepare('UPDATE roles SET nombre = ? WHERE id = ?')->execute([$nombre, $id]);
    timbra_json(['success' => true, 'message' => 'Rol renombrado.']);
}

if ($accion === 'eliminar') {
    $id = (int) ($body['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ?');
    $stmt->execute([$id]);
    $rol = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rol) {
        timbra_json(['success' => false, 'message' => 'Rol no encontrado.'], 404);
    }
    if ($rol['es_sistema']) {
        timbra_json(['success' => false, 'message' => 'Este rol es parte del sistema y no se puede eliminar (sí puedes editar sus permisos).'], 422);
    }
    $stmtUsuarios = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE rol = ?');
    $stmtUsuarios->execute([$rol['clave']]);
    if ((int) $stmtUsuarios->fetchColumn() > 0) {
        timbra_json(['success' => false, 'message' => 'Hay usuarios con este rol — cámbiales el rol primero desde Usuarios.'], 422);
    }

    $pdo->prepare('DELETE FROM rol_modulos WHERE rol_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM roles WHERE id = ?')->execute([$id]);

    timbra_json(['success' => true, 'message' => 'Rol eliminado.']);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
