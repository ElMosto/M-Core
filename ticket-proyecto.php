<?php
/**
 * ticket-proyecto.php?id=N — ticket imprimible de los cobros de un
 * proyecto externo: costo base + cada costo extra como su propia línea.
 * Mismas reglas de acceso que ver el proyecto (timbra_puede_ver_proyecto).
 */

require_once __DIR__ . '/api/lib/auth.php';
require_once __DIR__ . '/api/lib/security-headers.php';
$usuarioActual = timbra_requerir_modulo('tareas');

$id = (int) ($_GET['id'] ?? 0);
$pdo = timbra_db();

$stmt = $pdo->prepare('
    SELECT p.*, cl.nombre AS cliente_nombre, u.nombre AS creado_por_nombre
    FROM proyectos p
    JOIN usuarios u ON u.id = p.creado_por
    LEFT JOIN clientes cl ON cl.id = p.cliente_id
    WHERE p.id = ?
');
$stmt->execute([$id]);
$proyecto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proyecto || !timbra_puede_ver_proyecto($pdo, $usuarioActual, $proyecto)) {
    header('Location: tareas.php');
    exit;
}

$extras = $pdo->prepare('
    SELECT ce.*, u.nombre AS creado_por_nombre
    FROM costos_extra ce JOIN usuarios u ON u.id = ce.creado_por
    WHERE ce.proyecto_id = ? ORDER BY ce.created_at ASC
');
$extras->execute([$id]);
$extras = $extras->fetchAll(PDO::FETCH_ASSOC);

$sumaExtras = array_sum(array_map(fn($e) => (float) $e['monto'], $extras));
$total = (float) $proyecto['costo'] + $sumaExtras;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticket de cobros · <?= htmlspecialchars($proyecto['nombre']) ?> · M-Core</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600&family=Inter:wght@500;600&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/ticket.css">
<?php
$ap = timbra_apariencia();
$negocio = null;
if (!empty($proyecto['negocio_id'])) {
    $stmtN = $pdo->prepare('SELECT nombre, logo_path FROM negocios WHERE id = ?');
    $stmtN->execute([(int) $proyecto['negocio_id']]);
    $negocio = $stmtN->fetch(PDO::FETCH_ASSOC) ?: null;
}
$marcaNombre = $negocio['nombre'] ?? 'M-Core';
$marcaLogo = $negocio['logo_path'] ?? $ap['logo_path'];
?>
<style>:root{ --ticket-ink:<?= htmlspecialchars($ap['color_ink']) ?>; --ticket-brand:<?= htmlspecialchars($ap['color_brand']) ?>; --ticket-brand-dark:<?= htmlspecialchars($ap['color_brand_dark']) ?>; }</style>
</head>
<body>

<div class="ticket">
  <div class="ticket-header">
    <div class="marca">
      <?= !empty($marcaLogo) ? '<img src="' . htmlspecialchars($marcaLogo) . '" alt="">' : htmlspecialchars($marcaNombre) ?>
    </div>
    <div class="subtitulo">Ticket de cobros · <?= htmlspecialchars($marcaNombre) ?></div>
  </div>

  <div class="ticket-meta">
    <div><span>Proyecto</span><span><?= htmlspecialchars($proyecto['nombre']) ?></span></div>
    <div><span>Cliente</span><span><?= htmlspecialchars($proyecto['cliente_nombre'] ?: '—') ?></span></div>
    <div><span>Responsable</span><span><?= htmlspecialchars($proyecto['creado_por_nombre']) ?></span></div>
    <div><span>Generado</span><span><?= date('Y-m-d H:i') ?></span></div>
  </div>

  <hr class="ticket-separador">

  <?php if ($proyecto['tipo'] !== 'externo'): ?>
    <p class="ticket-nota">Este proyecto es <strong>interno</strong> y no tiene costos asociados — no hay nada que cobrar.</p>
  <?php else: ?>
    <table class="ticket-tabla">
      <thead>
        <tr><th>Concepto</th><th class="col-num">Monto</th></tr>
      </thead>
      <tbody>
        <tr class="fila-item">
          <td>Costo base del proyecto</td>
          <td class="col-num">$<?= number_format((float) $proyecto['costo'], 2) ?></td>
        </tr>
        <?php foreach ($extras as $e): ?>
          <tr class="fila-item">
            <td>
              <?= htmlspecialchars($e['descripcion']) ?>
              <div class="ticket-nota" style="margin-top:0">Agregado por <?= htmlspecialchars($e['creado_por_nombre']) ?> · <?= htmlspecialchars(substr($e['created_at'], 0, 10)) ?></div>
            </td>
            <td class="col-num">$<?= number_format((float) $e['monto'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($extras): ?>
      <div class="ticket-subtotal-row"><span>Costo base</span><span>$<?= number_format((float) $proyecto['costo'], 2) ?></span></div>
      <div class="ticket-subtotal-row"><span>Costos extra (<?= count($extras) ?>)</span><span>$<?= number_format($sumaExtras, 2) ?></span></div>
    <?php endif; ?>

    <div class="ticket-total-row">
      <span>Total</span>
      <span>$<?= number_format($total, 2) ?></span>
    </div>
  <?php endif; ?>

  <div class="ticket-acciones no-imprimir">
    <button type="button" class="btn-primario" onclick="window.print()">Imprimir / Guardar PDF</button>
    <a href="tareas.php?id=<?= (int) $id ?>" class="btn-secundario">Cerrar</a>
  </div>
</div>

</body>
</html>
