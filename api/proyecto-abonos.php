<?php
/**
 * GET  api/proyecto-abonos.php?proyecto_id=1
 * -> { total_proyecto, total_abonado, saldo, abonos: [...] }
 *
 * POST api/proyecto-abonos.php  accion=crear     (admin / lider_proyecto dueño)
 *      { proyecto_id, monto, fecha, metodo_pago, notas }
 * POST api/proyecto-abonos.php  accion=eliminar  (admin / lider_proyecto dueño)
 *      { id }
 *
 * Solo aplica a proyectos externos (los internos no tienen costo, así que
 * no hay nada que abonar). Mismas reglas de acceso que costos-extra.php:
 * ver = timbra_puede_ver_proyecto(); registrar/eliminar = admin o el
 * lider_proyecto dueño de ESE proyecto.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

$usuario = timbra_requerir_modulo_api('tareas');
$pdo = timbra_db();

function timbra_obtener_proyecto_abonos(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM proyectos WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function timbra_es_dueno_de_proyecto_abonos(array $usuario, array $proyecto): bool
{
    return $usuario['rol'] === 'admin'
        || ($usuario['rol'] === 'lider_proyecto' && (int) $proyecto['creado_por'] === (int) $usuario['id']);
}

function timbra_total_abonado(PDO $pdo, int $proyectoId): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(monto), 0) FROM proyecto_abonos WHERE proyecto_id = ?');
    $stmt->execute([$proyectoId]);
    return (float) $stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $proyectoId = (int) ($_GET['proyecto_id'] ?? 0);
    $proyecto = timbra_obtener_proyecto_abonos($pdo, $proyectoId);
    if (!$proyecto) {
        timbra_json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
    }
    if (!timbra_puede_ver_proyecto($pdo, $usuario, $proyecto)) {
        timbra_json(['success' => false, 'message' => 'No tienes acceso a este proyecto.'], 403);
    }

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(monto), 0) FROM costos_extra WHERE proyecto_id = ?');
    $stmt->execute([$proyectoId]);
    $totalExtra = (float) $stmt->fetchColumn();
    $totalProyecto = (float) $proyecto['costo'] + $totalExtra;
    $totalAbonado = timbra_total_abonado($pdo, $proyectoId);

    $stmt = $pdo->prepare('
        SELECT a.id, a.monto, a.fecha, a.metodo_pago, a.notas, a.created_at, u.nombre AS registrado_por_nombre
        FROM proyecto_abonos a JOIN usuarios u ON u.id = a.registrado_por
        WHERE a.proyecto_id = ?
        ORDER BY a.fecha DESC, a.id DESC
    ');
    $stmt->execute([$proyectoId]);

    timbra_json([
        'total_proyecto' => round($totalProyecto, 2),
        'total_abonado'  => round($totalAbonado, 2),
        'saldo'          => round($totalProyecto - $totalAbonado, 2),
        'abonos'         => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
}

$body = timbra_body_json();
$accion = $body['accion'] ?? '';

if ($accion === 'crear') {
    $proyectoId = (int) ($body['proyecto_id'] ?? 0);
    $proyecto = timbra_obtener_proyecto_abonos($pdo, $proyectoId);
    if (!$proyecto) {
        timbra_json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
    }
    if (!timbra_es_dueno_de_proyecto_abonos($usuario, $proyecto)) {
        timbra_json(['success' => false, 'message' => 'Solo el líder dueño del proyecto o un admin puede registrar abonos.'], 403);
    }
    if ($proyecto['tipo'] !== 'externo') {
        timbra_json(['success' => false, 'message' => 'Solo los proyectos externos pueden llevar abonos.'], 422);
    }

    $monto = $body['monto'] ?? null;
    $fecha = trim((string) ($body['fecha'] ?? ''));
    $metodoPago = trim((string) ($body['metodo_pago'] ?? '')) ?: null;
    $notas = trim((string) ($body['notas'] ?? '')) ?: null;

    $errores = [];
    if (!is_numeric($monto) || (float) $monto <= 0) {
        $errores['monto'] = 'Captura un monto numérico mayor a cero.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $errores['fecha'] = 'Selecciona una fecha válida.';
    }
    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los datos del abono.', 'errors' => $errores], 422);
    }

    $pdo->prepare('
        INSERT INTO proyecto_abonos (proyecto_id, monto, fecha, metodo_pago, notas, registrado_por)
        VALUES (?, ?, ?, ?, ?, ?)
    ')->execute([$proyectoId, (float) $monto, $fecha, $metodoPago, $notas, $usuario['id']]);

    timbra_json(['success' => true, 'message' => 'Abono registrado.']);
}

if ($accion === 'eliminar') {
    $id = (int) ($body['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT a.*, p.creado_por FROM proyecto_abonos a JOIN proyectos p ON p.id = a.proyecto_id WHERE a.id = ?');
    $stmt->execute([$id]);
    $abono = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$abono) {
        timbra_json(['success' => false, 'message' => 'Abono no encontrado.'], 404);
    }
    if (!timbra_es_dueno_de_proyecto_abonos($usuario, ['creado_por' => $abono['creado_por']])) {
        timbra_json(['success' => false, 'message' => 'Solo el líder dueño del proyecto o un admin puede eliminar este abono.'], 403);
    }

    $pdo->prepare('DELETE FROM proyecto_abonos WHERE id = ?')->execute([$id]);
    timbra_json(['success' => true, 'message' => 'Abono eliminado.']);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
