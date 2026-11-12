<?php
/**
 * GET  api/leads-config.php            -> { meta_activo, linkedin_activo }
 * POST api/leads-config.php  { meta_activo, linkedin_activo }
 *
 * Exclusivo de admin (timbra_requerir_admin(), como usuarios.php/roles.php)
 * — decidir qué fuentes de leads están activas es una decisión de
 * administración, no pasa por el checklist de módulos asignables.
 *
 * Esto es la configuración; todavía NO hay integración real con las APIs
 * de Meta/LinkedIn — ver api/leads.php accion=simular para el generador de
 * prueba mientras tanto.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

timbra_requerir_admin(true);
$pdo = timbra_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $config = $pdo->query('SELECT meta_activo, linkedin_activo FROM leads_config WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    timbra_json($config ?: ['meta_activo' => 0, 'linkedin_activo' => 0]);
}

$body = timbra_body_json();
$metaActivo = !empty($body['meta_activo']) ? 1 : 0;
$linkedinActivo = !empty($body['linkedin_activo']) ? 1 : 0;

$pdo->prepare('UPDATE leads_config SET meta_activo = ?, linkedin_activo = ? WHERE id = 1')
    ->execute([$metaActivo, $linkedinActivo]);

timbra_json(['success' => true, 'message' => 'Configuración de fuentes actualizada.']);
