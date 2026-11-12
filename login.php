<?php
require_once __DIR__ . '/api/lib/auth.php';
require_once __DIR__ . '/api/lib/security-headers.php';

$usuarioActual = timbra_usuario_actual();
if ($usuarioActual) {
    header('Location: ' . timbra_modulo_inicio($usuarioActual));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión · M-Core</title>
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
<?= timbra_apariencia_css_vars() ?>
</head>
<body style="background:var(--ink)">
  <div class="d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div style="width:100%; max-width:380px;">

      <div class="text-center mb-4">
        <?= timbra_logo_html(44) ?>
        <div class="font-display mt-2" style="color:#fff; font-size:1.4rem;">M-Core</div>
      </div>

      <div class="card-timbra">
        <div class="card-body-timbra">
          <h2 class="mb-3" style="font-size:1.05rem">Iniciar sesión</h2>

          <div class="alert alert-danger py-2 d-none" id="login-error" style="font-size:.88rem"></div>

          <form id="form-login" novalidate>
            <div class="mb-3">
              <label for="email" class="form-label">Correo</label>
              <input type="email" class="form-control" id="email" name="email" required autofocus>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Contraseña</label>
              <div class="input-group">
                <input type="password" class="form-control" id="password" name="password" required>
                <button class="btn btn-outline-secondary pw-toggle" type="button" data-target="password"
                        onclick="const i=document.getElementById('password'); const showing=i.type==='text'; i.type=showing?'password':'text'; this.querySelector('i').className=showing?'bi bi-eye':'bi bi-eye-slash';">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <button type="submit" class="btn btn-timbra w-100" id="btn-login">
              <span class="btn-text">Entrar</span>
              <span class="btn-spinner spinner-border spinner-border-sm ms-1 d-none"></span>
            </button>
          </form>
        </div>
      </div>

      <p class="text-center mt-3" style="color:#8A9895; font-size:.8rem">
        ¿Olvidaste tu acceso? Pide a un administrador que lo restablezca desde el módulo de Usuarios.
      </p>
    </div>
  </div>

<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('service-worker.js').catch(() => {});
}

document.getElementById('form-login').addEventListener('submit', async (e) => {
  e.preventDefault();
  const errorBox = document.getElementById('login-error');
  errorBox.classList.add('d-none');

  const btn = document.getElementById('btn-login');
  const btnText = btn.querySelector('.btn-text');
  const btnSpinner = btn.querySelector('.btn-spinner');
  btn.disabled = true; btnSpinner.classList.remove('d-none'); btnText.classList.add('d-none');

  try {
    const res = await fetch('api/auth.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        accion: 'login',
        email: document.getElementById('email').value,
        password: document.getElementById('password').value,
      })
    });
    const data = await res.json();
    if (data.success) {
      window.location.href = data.redirect || 'index.php';
      return; // deja el botón deshabilitado mientras navega
    }
    errorBox.textContent = data.message || 'No se pudo iniciar sesión.';
    errorBox.classList.remove('d-none');
  } catch (err) {
    errorBox.textContent = 'No se pudo conectar con el servidor. Intenta de nuevo.';
    errorBox.classList.remove('d-none');
  }
  btn.disabled = false; btnSpinner.classList.add('d-none'); btnText.classList.remove('d-none');
});
</script>
</body>
</html>
