<?php
/**
 * ticket-venta.php?id=N — ticket imprimible de una venta, con cada línea
 * desglosada. Se abre en pestaña nueva desde ventas.php; no usa el layout
 * general (sidebar/topbar), es una página angosta pensada para imprimirse
 * o guardarse como PDF desde el diálogo de impresión del navegador.
 */

require_once __DIR__ . '/api/lib/auth.php';
require_once __DIR__ . '/api/lib/security-headers.php';
$usuarioActual = timbra_requerir_modulo('ventas');

$id = (int) ($_GET['id'] ?? 0);
$pdo = timbra_db();

$stmt = $pdo->prepare('
    SELECT v.*, cl.nombre AS cliente_nombre, u.nombre AS vendido_por_nombre
    FROM ventas v
    JOIN usuarios u ON u.id = v.vendido_por
    LEFT JOIN clientes cl ON cl.id = v.cliente_id
    WHERE v.id = ?
');
$stmt->execute([$id]);
$venta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$venta) {
    header('Location: ventas.php');
    exit;
}

// Control de acceso: un usuario solo puede ver tickets de ventas que él
// registró, salvo admin que ve todos. Evita IDOR por ?id=.
if ($usuarioActual['rol'] !== 'admin' && (int) $venta['vendido_por'] !== (int) $usuarioActual['id']) {
    header('Location: ventas.php');
    exit;
}

$items = $pdo->prepare('SELECT * FROM venta_items WHERE venta_id = ? ORDER BY id ASC');
$items->execute([$id]);
$items = $items->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ticket de venta #<?= $venta['id'] ?> · M-Core</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600&family=Inter:wght@500;600&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/ticket.css">
<?php
$ap = timbra_apariencia();
$negocio = null;
if (!empty($venta['negocio_id'])) {
    $stmtN = $pdo->prepare('SELECT nombre, logo_path FROM negocios WHERE id = ?');
    $stmtN->execute([(int) $venta['negocio_id']]);
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
    <div class="subtitulo">Ticket de venta · <?= htmlspecialchars($marcaNombre) ?></div>
  </div>

  <div class="ticket-meta">
    <div><span>Folio</span><span>#<?= str_pad((string) $venta['id'], 6, '0', STR_PAD_LEFT) ?></span></div>
    <div><span>Fecha</span><span><?= htmlspecialchars(str_replace('T', ' ', substr($venta['created_at'], 0, 16))) ?></span></div>
    <div><span>Cliente</span><span><?= htmlspecialchars($venta['cliente_nombre'] ?: 'Sin cliente registrado') ?></span></div>
    <div><span>Atendió</span><span><?= htmlspecialchars($venta['vendido_por_nombre']) ?></span></div>
  </div>

  <hr class="ticket-separador">

  <table class="ticket-tabla">
    <thead>
      <tr><th>Artículo</th><th class="col-num">Cant.</th><th class="col-num">P. unit.</th><th class="col-num">Subtotal</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr class="fila-item">
          <td><?= htmlspecialchars($it['nombre_item']) ?></td>
          <td class="col-num"><?= (int) $it['cantidad'] ?></td>
          <td class="col-num">$<?= number_format((float) $it['precio_unitario'], 2) ?></td>
          <td class="col-num">$<?= number_format((float) $it['subtotal'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="ticket-total-row">
    <span>Total</span>
    <span>$<?= number_format((float) $venta['total'], 2) ?></span>
  </div>

  <?php if ($venta['notas']): ?>
    <div class="ticket-nota"><strong>Notas:</strong> <?= htmlspecialchars($venta['notas']) ?></div>
  <?php endif; ?>

  <div class="ticket-acciones no-imprimir">
    <button type="button" class="btn-primario" onclick="window.print()">Imprimir / Guardar PDF</button>
    <a href="ventas.php" class="btn-secundario">Cerrar</a>
  </div>
</div>

</body>
</html>
