<?php
/**
 * GET  api/leads.php                          -> leads visibles (ver reglas abajo)
 * GET  api/leads.php?colaboradores_asignables=1 -> usuarios a los que se puede asignar un lead
 *
 * POST api/leads.php  accion=simular   (admin)                  -> genera 100 leads de prueba por cada fuente activa
 * POST api/leads.php  accion=asignar   (admin / lider_proyecto)   { id, colaborador_id }
 * POST api/leads.php  accion=cambiar_estado                       { id, estado }
 *
 * Visibilidad:
 *   - admin / lider_proyecto: TODOS los leads.
 *   - cualquier otro rol con acceso a este módulo (típicamente colaborador):
 *     SOLO los leads que tiene asignados — el filtro se fuerza en el
 *     servidor, igual que en Tareas.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('leads');
$pdo = timbra_db();

const ESTADOS_LEAD_VALIDOS = ['nuevo', 'asignado', 'contactado', 'convertido', 'descartado'];

function timbra_puede_gestionar_leads(array $usuario): bool
{
    return in_array($usuario['rol'], ['admin', 'lider_proyecto'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['colaboradores_asignables'])) {
    if (!timbra_puede_gestionar_leads($usuario)) {
        timbra_json(['success' => false, 'message' => 'No autorizado.'], 403);
    }
    // Cualquier rol con acceso a Leads, excepto admin y lider_proyecto
    // (ellos asignan, no son destino de la asignación).
    $rolesAsignables = array_values(array_diff(timbra_roles_con_modulo($pdo, 'leads'), ['lider_proyecto']));
    if (!$rolesAsignables) {
        timbra_json([]);
    }
    $marcadores = implode(',', array_fill(0, count($rolesAsignables), '?'));
    $stmt = $pdo->prepare("
        SELECT u.id, u.nombre, r.nombre AS rol_nombre
        FROM usuarios u LEFT JOIN roles r ON r.clave = u.rol
        WHERE u.activo = 1 AND u.rol IN ({$marcadores})
        ORDER BY u.nombre ASC
    ");
    $stmt->execute($rolesAsignables);
    timbra_json($stmt->fetchAll(PDO::FETCH_ASSOC));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "
        SELECT l.id, l.nombre, l.empresa, l.email, l.telefono, l.fuente, l.mensaje, l.estado,
               l.asignado_a, ua.nombre AS asignado_nombre,
               l.asignado_por, up.nombre AS asignado_por_nombre,
               l.fecha_asignacion, l.created_at
        FROM leads l
        LEFT JOIN usuarios ua ON ua.id = l.asignado_a
        LEFT JOIN usuarios up ON up.id = l.asignado_por
    ";
    $params = [];
    if (!timbra_puede_gestionar_leads($usuario)) {
        $sql .= ' WHERE l.asignado_a = :uid';
        $params['uid'] = $usuario['id'];
    }
    $sql .= ' ORDER BY l.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    timbra_json($stmt->fetchAll(PDO::FETCH_ASSOC));
}

$body = timbra_body_json();
$accion = $body['accion'] ?? '';

if ($accion === 'simular') {
    if ($usuario['rol'] !== 'admin') {
        timbra_json(['success' => false, 'message' => 'Solo un administrador puede simular la generación de leads.'], 403);
    }

    $config = $pdo->query('SELECT meta_activo, linkedin_activo FROM leads_config WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    $fuentesActivas = [];
    if (!empty($config['meta_activo'])) $fuentesActivas[] = 'meta';
    if (!empty($config['linkedin_activo'])) $fuentesActivas[] = 'linkedin';

    if (!$fuentesActivas) {
        timbra_json(['success' => false, 'message' => 'Activa al menos una fuente (Meta o LinkedIn) antes de simular.'], 422);
    }

    $NOMBRES = ['Andrea', 'Luis', 'María', 'Carlos', 'Fernanda', 'Jorge', 'Paola', 'Ricardo', 'Daniela', 'Miguel',
        'Valeria', 'Alejandro', 'Sofía', 'Diego', 'Camila', 'Javier', 'Renata', 'Emilio', 'Ximena', 'Héctor'];
    $APELLIDOS = ['García', 'Hernández', 'Martínez', 'López', 'González', 'Pérez', 'Sánchez', 'Ramírez',
        'Torres', 'Flores', 'Rivera', 'Gómez', 'Díaz', 'Cruz', 'Morales', 'Reyes', 'Ortiz', 'Vargas'];
    $EMPRESAS = ['Grupo Bravo', 'Comercial Alfa', 'Constructora del Norte', 'Soluciones Delta', 'Distribuidora Vega',
        'Consultores Ibero', 'Manufacturas Roble', 'Logística Pacífico', 'Textiles Andina', 'Servicios Cima',
        null, null]; // algunos leads sin empresa (persona independiente)
    $MENSAJES = [
        'Quiero más información sobre sus servicios.',
        'Me interesa una cotización.',
        '¿Tienen disponibilidad esta semana?',
        'Vi su anuncio y quiero saber precios.',
        '¿Manejan planes para empresas?',
        null,
    ];

    $insert = $pdo->prepare('
        INSERT INTO leads (nombre, empresa, email, telefono, fuente, mensaje)
        VALUES (?, ?, ?, ?, ?, ?)
    ');

    $totalGenerados = 0;
    $pdo->beginTransaction();
    foreach ($fuentesActivas as $fuente) {
        for ($i = 0; $i < 100; $i++) {
            $nombre = $NOMBRES[array_rand($NOMBRES)] . ' ' . $APELLIDOS[array_rand($APELLIDOS)];
            $empresa = $EMPRESAS[array_rand($EMPRESAS)];
            $slug = strtolower(str_replace(' ', '.', $nombre));
            $email = preg_replace('/[^a-z.]/', '', $slug) . random_int(1, 999) . '@ejemplo.com';
            $telefono = '55' . random_int(10000000, 99999999);
            $mensaje = $MENSAJES[array_rand($MENSAJES)];

            $insert->execute([$nombre, $empresa, $email, $telefono, $fuente, $mensaje]);
            $totalGenerados++;
        }
    }
    $pdo->commit();

    timbra_json([
        'success' => true,
        'message' => "Se generaron {$totalGenerados} leads de prueba (" . implode(' y ', $fuentesActivas) . ").",
        'total' => $totalGenerados,
    ]);
}

if ($accion === 'asignar') {
    if (!timbra_puede_gestionar_leads($usuario)) {
        timbra_json(['success' => false, 'message' => 'Solo un líder de proyecto o admin puede asignar leads.'], 403);
    }

    $id = (int) ($body['id'] ?? 0);
    $colaboradorId = (int) ($body['colaborador_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) {
        timbra_json(['success' => false, 'message' => 'Lead no encontrado.'], 404);
    }

    $rolesAsignables = array_values(array_diff(timbra_roles_con_modulo($pdo, 'leads'), ['lider_proyecto']));
    $stmt = $pdo->prepare('SELECT rol FROM usuarios WHERE id = ? AND activo = 1');
    $stmt->execute([$colaboradorId]);
    $rolColaborador = $stmt->fetchColumn();

    if (!$rolColaborador || !in_array($rolColaborador, $rolesAsignables, true)) {
        timbra_json(['success' => false, 'message' => 'Selecciona a alguien activo con acceso al módulo de Leads.', 'errors' => ['colaborador_id' => 'Colaborador no válido.']], 422);
    }

    $pdo->prepare('
        UPDATE leads SET asignado_a = ?, asignado_por = ?, fecha_asignacion = datetime("now"),
               estado = CASE WHEN estado = "nuevo" THEN "asignado" ELSE estado END
        WHERE id = ?
    ')->execute([$colaboradorId, $usuario['id'], $id]);

    timbra_json(['success' => true, 'message' => 'Lead asignado.']);
}

if ($accion === 'cambiar_estado') {
    $id = (int) ($body['id'] ?? 0);
    $estado = (string) ($body['estado'] ?? '');

    if (!in_array($estado, ESTADOS_LEAD_VALIDOS, true)) {
        timbra_json(['success' => false, 'message' => 'Estado no válido.'], 422);
    }

    $stmt = $pdo->prepare('SELECT * FROM leads WHERE id = ?');
    $stmt->execute([$id]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lead) {
        timbra_json(['success' => false, 'message' => 'Lead no encontrado.'], 404);
    }

    $esDueno = (int) $lead['asignado_a'] === (int) $usuario['id'];
    if (!timbra_puede_gestionar_leads($usuario) && !$esDueno) {
        timbra_json(['success' => false, 'message' => 'Solo puedes actualizar los leads que tienes asignados.'], 403);
    }

    $pdo->prepare('UPDATE leads SET estado = ? WHERE id = ?')->execute([$estado, $id]);
    timbra_json(['success' => true, 'message' => 'Estado actualizado.']);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
