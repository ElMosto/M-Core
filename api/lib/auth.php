<?php
/**
 * Autenticación por sesión + control de acceso por rol.
 *
 * Uso típico al inicio de una vista PHP:
 *   require_once __DIR__ . '/api/lib/auth.php';
 *   $usuario = timbra_requerir_modulo('timbrado'); // redirige si no aplica
 *
 * Uso típico al inicio de un endpoint de /api:
 *   $usuario = timbra_requerir_modulo_api('timbrado');
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/helpers.php';

function timbra_iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Endurece la cookie de sesión frente a robo por XSS/CSRF.
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            ini_set('session.cookie_secure', '1');
        }
        session_start();
    }
}

/** Token Bearer de la petición actual, o null si no viene ninguno. */
function timbra_token_de_peticion(): ?string
{
    $encabezado = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] // algunos hosts con FastCGI lo mueven aquí
        ?? '';
    if ($encabezado === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $nombre => $valor) {
            if (strcasecmp($nombre, 'Authorization') === 0) {
                $encabezado = $valor;
                break;
            }
        }
    }
    if (preg_match('/^Bearer\s+(.+)$/i', trim($encabezado), $m)) {
        return trim($m[1]);
    }
    return null;
}

/** Usuario dueño de un token válido y no vencido, o null. */
function timbra_usuario_por_token(string $token): ?array
{
    $pdo = timbra_db();
    $stmt = $pdo->prepare('
        SELECT u.id, u.nombre, u.email, u.rol, u.foto_path
        FROM sesiones_token st
        JOIN usuarios u ON u.id = st.usuario_id
        WHERE st.token = ? AND st.expira_en > datetime("now") AND u.activo = 1
    ');
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Usuario autenticado actual, sin importar cómo se identificó: primero se
 * revisa si trae un token Bearer (la app), y si no, la cookie de sesión de
 * PHP (la web). Cacheado en memoria por si se llama varias veces en la
 * misma petición.
 */
function timbra_usuario_actual(): ?array
{
    static $resuelto = false;
    static $usuario = null;
    if ($resuelto) {
        return $usuario;
    }
    $resuelto = true;

    $token = timbra_token_de_peticion();
    if ($token !== null) {
        $usuario = timbra_usuario_por_token($token);
        return $usuario;
    }

    timbra_iniciar_sesion();
    $usuario = $_SESSION['usuario'] ?? null;
    return $usuario;
}

/**
 * true si el usuario tiene acceso al módulo indicado.
 *   - admin: siempre true (bypass fijo, no depende de la base de datos).
 *   - usuarios / roles / apariencia: exclusivos de admin, nunca asignables a otro rol
 *     (evita que un rol se dé a sí mismo más permisos).
 *   - perfil: universal, cualquier sesión lo tiene.
 *   - cualquier otro módulo: se consulta en `rol_modulos` (administrable
 *     desde la pantalla de Roles).
 */
function timbra_puede(array $usuario, string $modulo): bool
{
    if (in_array($modulo, ['usuarios', 'roles', 'apariencia', 'negocios'], true)) {
        return $usuario['rol'] === 'admin';
    }
    if (in_array($modulo, ['perfil', 'agenda'], true)) {
        return true;
    }
    if ($usuario['rol'] === 'admin') {
        return true;
    }
    return in_array($modulo, timbra_modulos_de_rol($usuario['rol']), true);
}

/** Primer módulo al que el usuario tiene acceso (para redirigir tras login). */
function timbra_modulo_inicio(array $usuario): string
{
    if ($usuario['rol'] === 'admin') {
        return 'dashboard.php';
    }
    foreach (TIMBRA_MODULOS_ORDEN as $modulo) {
        if (timbra_puede($usuario, $modulo)) {
            return timbra_url_modulo($modulo);
        }
    }
    return 'login.php';
}

function timbra_url_modulo(string $modulo): string
{
    $mapa = [
        'dashboard'      => 'dashboard.php',
        'tareas'         => 'tareas.php',
        'clientes'       => 'clientes.php',
        'perfil'         => 'perfil.php',
        'inventario'     => 'inventario.php',
        'ventas'         => 'ventas.php',
        'productos_servicios' => 'productos-servicios.php',
        'finanzas'       => 'finanzas.php',
        'leads'          => 'leads.php',
        'agenda'         => 'agenda.php',
        'usuarios'       => 'usuarios.php',
        'roles'          => 'roles.php',
        'apariencia'     => 'apariencia.php',
        'negocios'       => 'negocios.php',
    ];
    return $mapa[$modulo] ?? 'login.php';
}

/**
 * Lista de módulos a los que el usuario tiene acceso, en el mismo orden
 * que TIMBRA_MODULOS_ORDEN. La usa api/auth.php para que un cliente
 * (la web o, más adelante, la app) pueda armar su propio menú sin tener
 * que repetir la lógica de permisos en cada plataforma.
 */
function timbra_modulos_accesibles(array $usuario): array
{
    return array_values(array_filter(
        TIMBRA_MODULOS_ORDEN,
        fn($modulo) => timbra_puede($usuario, $modulo)
    ));
}

/**
 * Para vistas PHP (HTML). Si no hay sesión, redirige a login.php. Si hay
 * sesión pero el rol no tiene acceso al módulo, redirige a su módulo de
 * inicio. Devuelve el usuario autenticado si todo está en orden.
 */
function timbra_requerir_modulo(string $modulo): array
{
    $usuario = timbra_usuario_actual();
    if (!$usuario) {
        header('Location: login.php');
        exit;
    }
    if (!timbra_puede($usuario, $modulo)) {
        header('Location: ' . timbra_modulo_inicio($usuario));
        exit;
    }
    return $usuario;
}

/** Igual que arriba, pero pensado para endpoints /api (responde JSON 401/403). */
function timbra_requerir_modulo_api(string $modulo): array
{
    require_once __DIR__ . '/helpers.php';
    $usuario = timbra_usuario_actual();
    if (!$usuario) {
        timbra_json(['success' => false, 'message' => 'Debes iniciar sesión.'], 401);
    }
    if (!timbra_puede($usuario, $modulo)) {
        timbra_json(['success' => false, 'message' => 'No tienes permiso para esta acción.'], 403);
    }
    timbra_validar_csrf();
    return $usuario;
}

/** Solo admin — usado por usuarios.php/roles.php y sus respectivas APIs. */
function timbra_requerir_admin(bool $esApi = false): array
{
    $usuario = timbra_usuario_actual();
    if (!$usuario) {
        if ($esApi) {
            require_once __DIR__ . '/helpers.php';
            timbra_json(['success' => false, 'message' => 'Debes iniciar sesión.'], 401);
        }
        header('Location: login.php');
        exit;
    }
    if ($usuario['rol'] !== 'admin') {
        if ($esApi) {
            require_once __DIR__ . '/helpers.php';
            timbra_json(['success' => false, 'message' => 'Solo un administrador puede hacer esto.'], 403);
        }
        header('Location: ' . timbra_modulo_inicio($usuario));
        exit;
    }
    if ($esApi) {
        timbra_validar_csrf();
    }
    return $usuario;
}

/** Intenta autenticar; true y llena la sesión si las credenciales son correctas. */
function timbra_intentar_login(string $email, string $password): bool
{
    $pdo = timbra_db();
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = ? AND activo = 1');
    $stmt->execute([strtolower(trim($email))]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
        return false;
    }

    timbra_iniciar_sesion();
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'        => $usuario['id'],
        'nombre'    => $usuario['nombre'],
        'email'     => $usuario['email'],
        'rol'       => $usuario['rol'],
        'foto_path' => $usuario['foto_path'] ?? null,
    ];
    return true;
}

/**
 * Crea un token nuevo para un usuario (usado por la app en vez de la
 * cookie de sesión). De paso, aprovecha para limpiar tokens ya vencidos
 * de cualquier usuario — no hace falta un cron aparte para algo tan barato.
 */
function timbra_generar_token(int $usuarioId): array
{
    $pdo = timbra_db();
    $pdo->exec('DELETE FROM sesiones_token WHERE expira_en <= datetime("now")');

    $token = bin2hex(random_bytes(32));
    $expiraEn = date('Y-m-d H:i:s', time() + TIMBRA_TOKEN_DIAS_VALIDEZ * 86400);

    $pdo->prepare('INSERT INTO sesiones_token (token, usuario_id, expira_en) VALUES (?, ?, ?)')
        ->execute([hash('sha256', $token), $usuarioId, $expiraEn]);

    return ['token' => $token, 'expira_en' => $expiraEn];
}

function timbra_revocar_token(string $token): void
{
    timbra_db()->prepare('DELETE FROM sesiones_token WHERE token = ?')->execute([hash('sha256', $token)]);
}

function timbra_cerrar_sesion(): void
{
    timbra_iniciar_sesion();
    $_SESSION = [];
    session_destroy();
}
