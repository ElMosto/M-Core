<?php
/**
 * GET api/usuarios-asignables.php
 * -> [{ id, nombre, rol }, ...]  (solo activos, cuyo rol tenga acceso al módulo Tareas)
 *
 * Para el selector "Asignar a" al crear una tarea. Los administradores no
 * aparecen en la lista: las tareas se asignan a quien las ejecuta, no a
 * quien administra el sistema. Quién puede ser asignado ya no es una lista
 * fija de 3 roles — se deriva de qué roles tienen el módulo "tareas"
 * habilitado en la pantalla de Roles.
 * Accesible para quien puede crear/asignar tareas (admin y líder de proyecto).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_usuario_actual();
if (!$usuario || !in_array($usuario['rol'], ['admin', 'lider_proyecto'], true)) {
    timbra_json(['success' => false, 'message' => 'No autorizado.'], 403);
}

$pdo = timbra_db();
$rolesAsignables = timbra_roles_con_modulo($pdo, 'tareas');

if (!$rolesAsignables) {
    timbra_json([]);
}

$marcadores = implode(',', array_fill(0, count($rolesAsignables), '?'));
$stmt = $pdo->prepare("
    SELECT u.id, u.nombre, u.rol, r.nombre AS rol_nombre
    FROM usuarios u
    LEFT JOIN roles r ON r.clave = u.rol
    WHERE u.activo = 1 AND u.rol IN ({$marcadores})
    ORDER BY u.nombre ASC
");
$stmt->execute($rolesAsignables);

timbra_json($stmt->fetchAll(PDO::FETCH_ASSOC));
