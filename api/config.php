<?php
/**
 * Configuración central de M-Core.
 * Ajusta estas constantes según el entorno de despliegue.
 */

// Raíz del proyecto (una carpeta arriba de /api)
define('TIMBRA_ROOT', dirname(__DIR__));

// Base de datos SQLite (ligera, sin servidor aparte). Para producción real
// con más de un servidor de aplicación, sustituir por MySQL/Postgres.
define('TIMBRA_DB_PATH', TIMBRA_ROOT . '/storage/timbra.sqlite');

// Carpeta privada donde se guardan los CSD (.cer/.key). NUNCA debe ser
// accesible por HTTP: en producción, colócala fuera del webroot y ajusta
// esta ruta; aquí se protege además con storage/.htaccess.
define('TIMBRA_CSD_DIR', TIMBRA_ROOT . '/storage/csd');

// Folios contratados con el PAC (ajusta a tu plan real o léelo de tu propio
// sistema de facturación).
define('TIMBRA_FOLIOS_TOTAL', 5000);

// Endpoint real de verificación de salud del PAC (WSDL/status URL). Si se
// deja vacío, /api/pac-status.php reporta un estado local de referencia en
// vez de inventar una verificación remota.
define('TIMBRA_PAC_HEALTHCHECK_URL', '');

// Umbrales de latencia (ms) para clasificar el estado del PAC.
define('TIMBRA_PAC_LATENCIA_LENTA', 900);
define('TIMBRA_PAC_LATENCIA_CAIDA', 4000);

// Umbral para la alerta de "racha de rechazos consecutivos".
define('TIMBRA_RACHA_RECHAZOS_MINIMA', 5);

// Catálogos mínimos usados para validar el CFDI de prueba / filas del CSV.
// Amplíalos con el catálogo oficial c_UsoCFDI / c_MetodoPago del SAT.
define('TIMBRA_USOS_CFDI_VALIDOS', ['G01', 'G03', 'P01']);
define('TIMBRA_METODOS_PAGO_VALIDOS', ['PUE', 'PPD']);

// Roles y permisos por módulo ahora viven en la base de datos (tablas
// `roles` y `rol_modulos`, administrables desde la pantalla de Roles) — ya
// no son constantes fijas aquí. La única excepción es `admin`: su acceso
// total es un bypass fijo en código (api/lib/auth.php), no depende de filas
// en `rol_modulos`, así que nunca se puede quedar sin permisos por error.

// Módulos que un admin puede asignar a un rol desde la pantalla de Roles,
// con su nombre para mostrar en el checklist de permisos. "usuarios" y
// "roles" quedan fuera a propósito: administrar cuentas y permisos es
// exclusivo de admin siempre, no es asignable (evita que un rol se dé a sí
// mismo más acceso). "perfil" y "agenda" tampoco aparecen aquí porque son
// universales (cualquier sesión los tiene, sin importar el rol).
define('TIMBRA_MODULOS_ASIGNABLES', [
    'dashboard'           => 'Panel de control',
    'tareas'              => 'Tareas',
    'clientes'            => 'Clientes',
    'inventario'          => 'Inventario',
    'ventas'              => 'Ventas',
    'productos_servicios' => 'Productos y servicios',
    'finanzas'            => 'Finanzas',
    'leads'               => 'Leads',
]);

// Orden de prioridad para decidir a dónde mandar a alguien justo después de
// iniciar sesión: el primer módulo de esta lista al que su rol tenga acceso
// gana. "perfil" va al final como red de seguridad — todo el mundo lo
// tiene, así que un rol recién creado sin ningún módulo asignado nunca se
// queda sin ningún lugar a dónde ir.
define('TIMBRA_MODULOS_ORDEN', [
    'dashboard', 'tareas', 'clientes', 'leads',
    'inventario', 'ventas', 'productos_servicios', 'finanzas',
    'usuarios', 'roles', 'apariencia', 'negocios', 'agenda', 'perfil',
]);

// Cuántas filas de un lote se procesan por cada solicitud de estado.
// Con este valor el propio polling del front actúa como "motor" del
// procesamiento; para volúmenes grandes, sustituir por un worker con cron
// o una cola (ver nota en README.md).
define('TIMBRA_FILAS_POR_TICK', 25);

// Foto de perfil: tipos y tamaño máximo permitidos.
define('TIMBRA_AVATAR_DIR', TIMBRA_ROOT . '/assets/avatars');
define('TIMBRA_AVATAR_MIME_PERMITIDOS', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']);
define('TIMBRA_AVATAR_MAX_BYTES', 2 * 1024 * 1024); // 2 MB

// Logo del sistema (apariencia.php): igual que los avatares, se sirve
// directo por HTTP — no es un dato sensible.
define('TIMBRA_LOGO_DIR', TIMBRA_ROOT . '/assets/branding');
define('TIMBRA_LOGO_MAX_BYTES', 1 * 1024 * 1024); // 1 MB — es un logo, no una foto

// CORS: orígenes desde los que se acepta llamar a la API sin ser el mismo
// dominio (la web, al ser mismo-origen, no necesita esto). La app armada
// con Capacitor corre por default en https://localhost en Android — si
// cambias el "androidScheme" en capacitor.config.json, agrega ese origen
// aquí también. "http://localhost" queda para probar la app en un
// navegador de escritorio durante el desarrollo.
define('TIMBRA_CORS_ORIGENES_PERMITIDOS', [
    'https://localhost',
    'capacitor://localhost',
    'http://localhost',
]);

// Cuántos días dura un token de sesión de la app antes de pedir login de
// nuevo (la web no usa esto — sigue con la sesión de PHP normal).
define('TIMBRA_TOKEN_DIAS_VALIDEZ', 30);
