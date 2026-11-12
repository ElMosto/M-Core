<?php
/** Utilidades compartidas por los endpoints de /api. */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/security-headers.php';

/**
 * CORS — se ejecuta apenas se carga este archivo (casi todo endpoint de
 * /api lo requiere de primero). La web es mismo-origen y no lo necesita,
 * pero la app (Capacitor) llama a la API desde otro origen, así que sin
 * esto el navegador/WebView bloquea la respuesta antes de que el
 * JavaScript de la app la vea siquiera.
 *
 * Solo responde con encabezados CORS a los orígenes de la lista blanca
 * (TIMBRA_CORS_ORIGENES_PERMITIDOS en config.php) — nunca "*", ya que las
 * peticiones autenticadas (con token) no deben aceptarse desde cualquier
 * origen.
 */
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origen && in_array($origen, TIMBRA_CORS_ORIGENES_PERMITIDOS, true)) {
    header('Access-Control-Allow-Origin: ' . $origen);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Vary: Origin');
}
// El navegador manda un OPTIONS de "preflight" antes del POST/GET real
// cuando hay headers personalizados (Authorization) de por medio — no
// trae body ni necesita lógica de negocio, solo confirmar que se puede.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function timbra_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Rate limiting simple basado en archivo para el login.
 * Devuelve true si se permite el intento, false si está bloqueado.
 */
function timbra_rate_limit_login(string $clave): bool
{
    $archivo = TIMBRA_ROOT . '/storage/.rate_limit.json';
    $datos = [];
    if (is_file($archivo)) {
        $raw = @file_get_contents($archivo);
        $datos = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    $ahora = time();
    // Limpia entradas viejas (>15 min)
    foreach ($datos as $k => $v) {
        if (($v['reset'] ?? 0) < $ahora) {
            unset($datos[$k]);
        }
    }
    if (!isset($datos[$clave])) {
        $datos[$clave] = ['intentos' => 0, 'reset' => $ahora + 900];
    }
    if ($datos[$clave]['intentos'] >= 5) {
        @file_put_contents($archivo, json_encode($datos));
        return false;
    }
    return true;
}

function timbra_rate_limit_registrar_fallo(string $clave): void
{
    $archivo = TIMBRA_ROOT . '/storage/.rate_limit.json';
    $datos = [];
    if (is_file($archivo)) {
        $raw = @file_get_contents($archivo);
        $datos = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    $ahora = time();
    if (!isset($datos[$clave]) || ($datos[$clave]['reset'] ?? 0) < $ahora) {
        $datos[$clave] = ['intentos' => 0, 'reset' => $ahora + 900];
    }
    $datos[$clave]['intentos']++;
    @file_put_contents($archivo, json_encode($datos));
}

function timbra_rate_limit_limpiar(string $clave): void
{
    $archivo = TIMBRA_ROOT . '/storage/.rate_limit.json';
    if (!is_file($archivo)) return;
    $raw = @file_get_contents($archivo);
    $datos = $raw ? (json_decode($raw, true) ?: []) : [];
    unset($datos[$clave]);
    @file_put_contents($archivo, json_encode($datos));
}

function timbra_body_json(): array
{
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 1024 * 1024) { // 1 MB
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Token CSRF de la sesión actual (se genera una vez por sesión). */
function timbra_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Valida el token CSRF en peticiones POST (header X-CSRF-Token o campo _csrf). */
function timbra_validar_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if ($token === null && isset($_POST['_csrf'])) {
        $token = (string) $_POST['_csrf'];
    }
    if ($token === null) {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        if (is_array($data) && isset($data['_csrf'])) {
            $token = (string) $data['_csrf'];
        }
    }
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        timbra_json(['success' => false, 'message' => 'Token CSRF inválido o ausente.'], 403);
    }
}

/** true si la petición viene por fetch/AJAX. */
function timbra_es_ajax(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/** Validación simple de forma de RFC (persona física o moral). No confirma
 *  existencia ante el SAT, solo la estructura. */
function timbra_rfc_valido(string $rfc): bool
{
    return (bool) preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/', strtoupper(trim($rfc)));
}

function timbra_regimen_valido(string $regimen): bool
{
    return (bool) preg_match('/^[0-9]{3}$/', trim($regimen));
}

/**
 * Valida los datos mínimos de un CFDI de prueba (timbrado individual o fila
 * de un lote). Devuelve un arreglo de errores; vacío si todo es válido.
 * Esto NO sustituye la validación del PAC/SAT — solo evita mandar datos
 * mal formados. Conecta aquí el SDK real del PAC para la validación fiscal
 * completa (ver comentarios en timbrar-individual.php).
 */
function timbra_validar_cfdi_basico(array $d): array
{
    $errores = [];

    if (empty($d['rfc_receptor']) || !timbra_rfc_valido($d['rfc_receptor'])) {
        $errores[] = ['codigo' => 'CFDI33101', 'motivo' => 'El RFC del receptor no cumple el formato esperado.'];
    }
    if (empty($d['razon_receptor'])) {
        $errores[] = ['codigo' => 'CFDI33102', 'motivo' => 'Falta la razón social del receptor.'];
    }
    if (empty($d['uso_cfdi']) || !in_array(strtoupper(trim($d['uso_cfdi'])), TIMBRA_USOS_CFDI_VALIDOS, true)) {
        $errores[] = ['codigo' => 'CFDI40110', 'motivo' => 'El uso de CFDI no está en el catálogo permitido.'];
    }
    if (!isset($d['total']) || !is_numeric($d['total']) || (float) $d['total'] <= 0) {
        $errores[] = ['codigo' => 'CFDI40103', 'motivo' => 'El total debe ser numérico y mayor a cero.'];
    }
    if (empty($d['metodo_pago']) || !in_array(strtoupper(trim($d['metodo_pago'])), TIMBRA_METODOS_PAGO_VALIDOS, true)) {
        $errores[] = ['codigo' => 'CFDI40108', 'motivo' => 'El método de pago no está en el catálogo permitido.'];
    }

    return $errores;
}

/**
 * Módulos a los que tiene acceso un rol (por su clave), según la tabla
 * `rol_modulos`. No incluye el bypass de admin — eso se resuelve aparte en
 * timbra_puede(). Cacheado en memoria por si se consulta varias veces en
 * la misma petición (ej. al construir el menú lateral).
 */
function timbra_modulos_de_rol(string $clave): array
{
    static $cache = [];
    if (isset($cache[$clave])) {
        return $cache[$clave];
    }
    $stmt = timbra_db()->prepare('
        SELECT rm.modulo FROM rol_modulos rm
        JOIN roles r ON r.id = rm.rol_id
        WHERE r.clave = ?
    ');
    $stmt->execute([$clave]);
    return $cache[$clave] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Claves de rol (sin admin) que tienen acceso a un módulo dado. Se usa para
 * decidir, por ejemplo, a quién se le puede asignar una tarea: "cualquiera
 * cuyo rol tenga acceso al módulo Tareas" en vez de una lista fija de roles.
 */
function timbra_roles_con_modulo(PDO $pdo, string $modulo): array
{
    $stmt = $pdo->prepare("
        SELECT r.clave FROM roles r
        JOIN rol_modulos rm ON rm.rol_id = r.id
        WHERE rm.modulo = ? AND r.clave != 'admin'
    ");
    $stmt->execute([$modulo]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/** true si existe un rol con esa clave (para validar formularios). */
function timbra_clave_rol_existe(PDO $pdo, string $clave): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM roles WHERE clave = ?');
    $stmt->execute([$clave]);
    return (bool) $stmt->fetchColumn();
}

/** Nombre legible de un rol por su clave (para mostrar en la interfaz). */
function timbra_rol_nombre(string $clave): string
{
    static $cache = [];
    if (isset($cache[$clave])) {
        return $cache[$clave];
    }
    $stmt = timbra_db()->prepare('SELECT nombre FROM roles WHERE clave = ?');
    $stmt->execute([$clave]);
    $nombre = $stmt->fetchColumn();
    return $cache[$clave] = ($nombre ?: $clave);
}

/**
 * Configuración de apariencia (fila única), cacheada por petición. Con
 * defaults seguros si por lo que sea la fila no existiera todavía.
 */
function timbra_apariencia(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $fila = timbra_db()->query('SELECT * FROM apariencia_config WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    return $config = $fila ?: [
        'logo_path' => null, 'paleta' => 'verde_sello',
        'color_ink' => '#12211F', 'color_brand' => '#0F6E5C', 'color_brand_dark' => '#0B4F42',
    ];
}

/** Mezcla un color hex hacia blanco/negro un porcentaje dado (para tonos derivados). */
function timbra_mezclar_color(string $hex, string $hacia, float $pct): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return '#' . str_repeat('8', 6); // gris de emergencia si el valor guardado está corrupto
    }
    [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    $objetivo = $hacia === 'blanco' ? 255 : 0;
    $mezclar = fn($c) => (int) round($c + ($objetivo - $c) * $pct);
    return sprintf('#%02X%02X%02X', $mezclar($r), $mezclar($g), $mezclar($b));
}

/**
 * <style> con las variables de color ya resueltas para esta instalación.
 * Se manda DESPUÉS de theme.css (que trae los valores de fábrica como
 * respaldo) para que gane la cascada — mismo principio que ya aprendimos
 * con el bug del CSS de móvil: lo que se quiere que gane va al final.
 */
function timbra_apariencia_css_vars(): string
{
    $a = timbra_apariencia();
    $ink2 = timbra_mezclar_color($a['color_ink'], 'blanco', 0.10);
    $brandSoft = timbra_mezclar_color($a['color_brand'], 'blanco', 0.88);
    return '<style>:root{'
        . '--ink:' . htmlspecialchars($a['color_ink']) . ';'
        . '--ink-2:' . $ink2 . ';'
        . '--brand:' . htmlspecialchars($a['color_brand']) . ';'
        . '--brand-dark:' . htmlspecialchars($a['color_brand_dark']) . ';'
        . '--brand-soft:' . $brandSoft . ';'
        . '}</style>';
}

/** Logo del sistema: el PNG que subió el admin, o el sello SVG de fábrica. */
function timbra_logo_html(int $tamano = 26): string
{
    $a = timbra_apariencia();
    if (!empty($a['logo_path'])) {
        $src = htmlspecialchars($a['logo_path']);
        return "<img src=\"{$src}\" alt=\"\" style=\"width:{$tamano}px;height:{$tamano}px;object-fit:contain;border-radius:6px;flex-shrink:0\">";
    }
    return '<svg class="seal" width="' . $tamano . '" height="' . $tamano . '" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="20" cy="20" r="18" stroke="#5FBFA9" stroke-width="2.2"/>
        <circle cx="20" cy="20" r="12.5" stroke="#5FBFA9" stroke-width="1.4" stroke-dasharray="2.6 3"/>
        <path d="M13 21.5l4.5 4.5L27.5 15" stroke="#5FBFA9" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>';
}

/**
 * Regla de visibilidad de un proyecto según el rol de quien pregunta:
 *   - admin: siempre true.
 *   - lider_proyecto: solo si él lo creó.
 *   - contador / colaborador: solo si tiene al menos una tarea asignada ahí.
 * $proyecto debe incluir al menos 'id' y 'creado_por'.
 */
function timbra_puede_ver_proyecto(PDO $pdo, array $usuario, array $proyecto): bool
{
    if ($usuario['rol'] === 'admin') {
        return true;
    }
    if ($usuario['rol'] === 'lider_proyecto') {
        return (int) $proyecto['creado_por'] === (int) $usuario['id'];
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tareas WHERE proyecto_id = ? AND asignado_a = ?');
    $stmt->execute([$proyecto['id'], $usuario['id']]);
    return (int) $stmt->fetchColumn() > 0;
}

function timbra_uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function timbra_folio(): string
{
    return 'F-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
