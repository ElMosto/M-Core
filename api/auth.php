<?php
/**
 * api/auth.php — punto único de autenticación: lo usan la web (login.php
 * le hace fetch) y la app (Capacitor, sin cookies entre orígenes).
 *
 * GET  api/auth.php
 * -> autenticado:    { autenticado: true,  usuario: {...}, modulos: [...] }
 * -> sin autenticar: { autenticado: false }
 * "modulos" es la lista de módulos a los que ese usuario tiene acceso —
 * así un cliente nuevo (la app) arma su propio menú sin reimplementar las
 * reglas de permisos, solo pregunta.
 *
 * POST api/auth.php  accion=login   { email, password }
 * -> 200 { success: true, usuario: {...}, modulos: [...], redirect: "dashboard.php",
 *          token: "...", token_expira: "2026-09-15 12:00:00" }
 * -> 401 { success: false, message: "..." }
 *
 * POST api/auth.php  accion=logout
 * -> { success: true }
 *
 * Dos formas de identificarse, según quién llame:
 *   - La web sigue usando la cookie de sesión de PHP (mismo-origen, no
 *     necesita nada más).
 *   - La app manda el `token` que le regresa el login en cada petición
 *     como header `Authorization: Bearer <token>` — timbra_usuario_actual()
 *     revisa ambas formas automáticamente (ver api/lib/auth.php).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/security-headers.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $usuario = timbra_usuario_actual();
    if (!$usuario) {
        timbra_json(['autenticado' => false]);
    }
    timbra_json([
        'autenticado' => true,
        'usuario'     => $usuario,
        'modulos'     => timbra_modulos_accesibles($usuario),
        'csrf_token'  => timbra_csrf_token(),
    ]);
}

$body = timbra_body_json();
$accion = $body['accion'] ?? '';

if ($accion === 'login') {
    $email = trim((string) ($body['email'] ?? ''));
    $password = (string) ($body['password'] ?? '');

    if ($email === '' || $password === '') {
        timbra_json(['success' => false, 'message' => 'Captura tu correo y tu contraseña.'], 422);
    }

    $claveRL = ($_SERVER['REMOTE_ADDR'] ?? 'ip') . '|' . strtolower($email);
    if (!timbra_rate_limit_login($claveRL)) {
        timbra_json(['success' => false, 'message' => 'Demasiados intentos fallidos. Intenta de nuevo en 15 minutos.'], 429);
    }

    if (!timbra_intentar_login($email, $password)) {
        timbra_rate_limit_registrar_fallo($claveRL);
        timbra_json(['success' => false, 'message' => 'Correo o contraseña incorrectos, o la cuenta está inactiva.'], 401);
    }
    timbra_rate_limit_limpiar($claveRL);

    $usuario = timbra_usuario_actual();
    // Se emite un token siempre, sin importar quién llame — la web lo
    // ignora (sigue con su cookie), la app lo guarda y lo manda en cada
    // petición siguiente.
    $token = timbra_generar_token((int) $usuario['id']);

    timbra_json([
        'success'      => true,
        'usuario'      => $usuario,
        'modulos'      => timbra_modulos_accesibles($usuario),
        'redirect'     => timbra_modulo_inicio($usuario),
        'token'        => $token['token'],
        'token_expira' => $token['expira_en'],
        'csrf_token'   => timbra_csrf_token(),
    ]);
}

if ($accion === 'logout') {
    $token = timbra_token_de_peticion();
    if ($token !== null) {
        timbra_revocar_token($token);
    } else {
        timbra_cerrar_sesion();
    }
    timbra_json(['success' => true]);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
