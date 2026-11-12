<?php
/**
 * Layout compartido — parte superior.
 * Variables esperadas antes del include:
 *   $usuarioActual (array) resultado de timbra_requerir_modulo(), ya validado
 *   $pageTitle   (string) Título de la pestaña del navegador
 *   $activeNav   (string) 'dashboard' | 'contribuyentes' | 'timbrado' | 'tareas' | 'usuarios'
 *   $headerTitle (string) Título mostrado en la topbar
 *   $headerSub   (string) Subtítulo/descripcion corta en la topbar
 */
require_once __DIR__ . '/../api/lib/auth.php';
require_once __DIR__ . '/../api/lib/security-headers.php';
$csrfToken = function_exists('timbra_csrf_token') ? timbra_csrf_token() : '';
if (!isset($usuarioActual)) {
    // Salvaguarda: si una vista olvidó llamar a timbra_requerir_modulo(),
    // al menos exige que haya sesión antes de mostrar nada.
    $usuarioActual = timbra_usuario_actual();
    if (!$usuarioActual) {
        header('Location: login.php');
        exit;
    }
}

$pageTitle   = $pageTitle   ?? 'M-Core';
$activeNav   = $activeNav   ?? '';
$headerTitle = $headerTitle ?? '';
$headerSub   = $headerSub   ?? '';

$gruposNav = [
    'Inicio' => [
        ['modulo' => 'dashboard', 'href' => 'dashboard.php', 'icon' => 'bi-speedometer2', 'label' => 'Panel de control'],
    ],
    'Ventas' => [
        ['modulo' => 'clientes', 'href' => 'clientes.php', 'icon' => 'bi-person-vcard', 'label' => 'Clientes'],
        ['modulo' => 'leads', 'href' => 'leads.php', 'icon' => 'bi-magnet', 'label' => 'Leads'],
        ['modulo' => 'ventas', 'href' => 'ventas.php', 'icon' => 'bi-cart3', 'label' => 'Ventas'],
        ['modulo' => 'productos_servicios', 'href' => 'productos-servicios.php', 'icon' => 'bi-tags', 'label' => 'Productos y servicios'],
        ['modulo' => 'negocios', 'href' => 'negocios.php', 'icon' => 'bi-shop', 'label' => 'Negocios'],
    ],
    'Operaciones' => [
        ['modulo' => 'tareas', 'href' => 'tareas.php', 'icon' => 'bi-kanban', 'label' => 'Proyectos / Tareas'],
        ['modulo' => 'inventario', 'href' => 'inventario.php', 'icon' => 'bi-box-seam', 'label' => 'Inventario'],
        ['modulo' => 'agenda', 'href' => 'agenda.php', 'icon' => 'bi-calendar3', 'label' => 'Agenda'],
    ],
    'Finanzas' => [
        ['modulo' => 'finanzas', 'href' => 'finanzas.php', 'icon' => 'bi-graph-up-arrow', 'label' => 'Finanzas'],
    ],
    'Administración' => [
        ['modulo' => 'usuarios', 'href' => 'usuarios.php', 'icon' => 'bi-people', 'label' => 'Usuarios'],
        ['modulo' => 'roles', 'href' => 'roles.php', 'icon' => 'bi-shield-lock', 'label' => 'Roles'],
        ['modulo' => 'apariencia', 'href' => 'apariencia.php', 'icon' => 'bi-palette', 'label' => 'Apariencia'],
    ],
    'Cuenta' => [
        ['modulo' => 'perfil', 'href' => 'perfil.php', 'icon' => 'bi-person-circle', 'label' => 'Mi usuario'],
    ],
];
$mostrarPacPill = false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> · M-Core</title>

<!-- PWA: manifest + íconos (necesarios para "Instalar app" y para empaquetar con Bubblewrap/TWA) -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#12211F">
<link rel="icon" type="image/png" sizes="32x32" href="assets/icons/icon-32.png">
<link rel="icon" type="image/png" sizes="192x192" href="assets/icons/icon-192.png">
<link rel="apple-touch-icon" href="assets/icons/icon-180.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="assets/css/theme.css">
<?= timbra_apariencia_css_vars() /* después de theme.css a propósito: para que gane la cascada, ver el comentario sobre el bug de móvil en theme.css */ ?>
<script>
(function(){
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const origFetch = window.fetch;
  window.fetch = function(input, init){
    init = init || {};
    const method = (init.method || 'GET').toUpperCase();
    if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
      init.headers = init.headers || {};
      if (init.headers instanceof Headers) {
        if (!init.headers.has('X-CSRF-Token')) init.headers.set('X-CSRF-Token', token);
      } else if (Array.isArray(init.headers)) {
        if (!init.headers.some(h => String(h[0]).toLowerCase() === 'x-csrf-token')) init.headers.push(['X-CSRF-Token', token]);
      } else {
        if (!Object.keys(init.headers).some(k => k.toLowerCase() === 'x-csrf-token')) init.headers['X-CSRF-Token'] = token;
      }
    }
    return origFetch(input, init);
  };
})();
</script>
</head>
<body>
<div class="sidebar-overlay" id="sidebar-overlay"></div>
<div class="app-shell">

  <aside class="sidebar" id="app-sidebar">
    <div class="brand">
      <?= timbra_logo_html(26) ?>
      <span>M-Core</span>
    </div>

    <nav class="nav-timbra">
      <?php foreach ($gruposNav as $grupo => $items): ?>
        <?php $visibles = array_filter($items, fn($it) => timbra_puede($usuarioActual, $it['modulo'])); ?>
        <?php if (!$visibles) continue; ?>
        <div class="nav-grupo-label"><?= htmlspecialchars($grupo) ?></div>
        <?php foreach ($visibles as $item): ?>
          <a href="<?= $item['href'] ?>" class="<?= $activeNav === $item['modulo'] ? 'active' : '' ?>">
            <i class="bi <?= $item['icon'] ?>"></i> <?= $item['label'] ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
      <div class="d-flex align-items-center justify-content-between">
        <a href="perfil.php" class="d-flex align-items-center gap-2 text-decoration-none">
          <?php if (!empty($usuarioActual['foto_path'])): ?>
            <img src="<?= htmlspecialchars($usuarioActual['foto_path']) ?>" alt="" class="avatar-sidebar">
          <?php else: ?>
            <i class="bi bi-person-circle avatar-sidebar-fallback"></i>
          <?php endif; ?>
          <span>
            <strong style="color:#DCE7E4"><?= htmlspecialchars($usuarioActual['nombre']) ?></strong><br>
            <span class="mono"><?= htmlspecialchars(timbra_rol_nombre($usuarioActual['rol'])) ?></span>
          </span>
        </a>
        <a href="logout.php" title="Cerrar sesión" style="color:#B7C7C3">
          <i class="bi bi-box-arrow-right fs-5"></i>
        </a>
      </div>
    </div>
  </aside>

  <div class="main-col">
    <header class="topbar">
      <div class="d-flex align-items-center">
        <button type="button" class="btn-menu-movil" id="btn-abrir-menu" aria-label="Abrir menú">
          <i class="bi bi-list"></i>
        </button>
        <div>
          <h1><?= htmlspecialchars($headerTitle) ?></h1>
          <?php if ($headerSub): ?><div class="topbar-sub"><?= htmlspecialchars($headerSub) ?></div><?php endif; ?>
        </div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-dark-mode" title="Modo oscuro">
        <i class="bi bi-moon"></i>
      </button>

    </header>

    <main class="content">
