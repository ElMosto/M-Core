<?php
/**
 * GET  api/clientes.php                -> [{ id, nombre, contacto_nombre, email, telefono, notas, total_proyectos }, ...]
 * POST api/clientes.php  (admin / lider_proyecto)
 *      { nombre, contacto_nombre, email, telefono, notas } -> registra un cliente
 *
 * Quien puede crear proyectos externos (admin / lider_proyecto) es quien
 * necesita ver y registrar clientes — por eso comparte esos mismos roles.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('clientes');
$pdo = timbra_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $clientes = $pdo->query("
        SELECT c.id, c.nombre, c.contacto_nombre, c.email, c.telefono, c.notas, c.activo,
               (SELECT COUNT(*) FROM proyectos p WHERE p.cliente_id = c.id) AS total_proyectos
        FROM clientes c
        WHERE c.activo = 1
        ORDER BY c.nombre ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    timbra_json($clientes);
}

$body = timbra_body_json();
$nombre = trim((string) ($body['nombre'] ?? ''));
$contacto = trim((string) ($body['contacto_nombre'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$telefono = trim((string) ($body['telefono'] ?? ''));
$notas = trim((string) ($body['notas'] ?? ''));

$errores = [];
if ($nombre === '') {
    $errores['nombre'] = 'El cliente necesita un nombre o razón social.';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores['email'] = 'Correo no válido.';
}
if ($errores) {
    timbra_json(['success' => false, 'message' => 'Revisa los datos del cliente.', 'errors' => $errores], 422);
}

$pdo->prepare('
    INSERT INTO clientes (nombre, contacto_nombre, email, telefono, notas, creado_por)
    VALUES (:nombre, :contacto, :email, :telefono, :notas, :creado_por)
')->execute([
    'nombre' => $nombre, 'contacto' => $contacto ?: null, 'email' => $email ?: null,
    'telefono' => $telefono ?: null, 'notas' => $notas ?: null, 'creado_por' => $usuario['id'],
]);

timbra_json(['success' => true, 'message' => 'Cliente registrado.', 'id' => (int) $pdo->lastInsertId()]);
