<?php
/**
 * GET  api/apariencia.php                        -> { logo_path, paleta, color_ink, color_brand, color_brand_dark }
 * POST api/apariencia.php  accion=guardar_colores  { paleta, color_ink, color_brand, color_brand_dark }
 * POST api/apariencia.php  accion=subir_logo        (multipart/form-data, campo "logo")
 * POST api/apariencia.php  accion=quitar_logo
 *
 * Todo exclusivo de admin (timbra_requerir_admin(), como usuarios.php/roles.php
 * — no pasa por el checklist de módulos asignables, cambiar la marca del
 * sistema para todos no es algo que otro rol deba poder tocar).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/auth.php';

timbra_requerir_admin(true);
$pdo = timbra_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    timbra_json(timbra_apariencia());
}

/** true si el string es un color hex válido de 6 dígitos (#RRGGBB). */
function timbra_hex_valido(string $valor): bool
{
    return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $valor);
}

// accion=subir_logo llega como multipart (no JSON) — se detecta aparte.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'subir_logo') {
    $archivo = $_FILES['logo'] ?? null;

    if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
        timbra_json(['success' => false, 'message' => 'Sube un archivo PNG.', 'errors' => ['logo' => 'No se recibió el archivo.']], 422);
    }
    if ($archivo['size'] > TIMBRA_LOGO_MAX_BYTES) {
        timbra_json(['success' => false, 'message' => 'El logo es demasiado pesado.', 'errors' => ['logo' => 'Máximo 1 MB.']], 422);
    }

    $mime = mime_content_type($archivo['tmp_name']);
    if ($mime !== 'image/png') {
        timbra_json(['success' => false, 'message' => 'El logo debe ser un PNG.', 'errors' => ['logo' => 'Formato no válido — sube un .png.']], 422);
    }

    if (!is_dir(TIMBRA_LOGO_DIR)) {
        mkdir(TIMBRA_LOGO_DIR, 0770, true);
    }

    // Borra el logo anterior si había uno (no dejar archivos huérfanos).
    $anterior = timbra_apariencia()['logo_path'] ?? null;
    if ($anterior && str_starts_with($anterior, 'assets/branding/') && is_file(TIMBRA_ROOT . '/' . $anterior)) {
        @unlink(TIMBRA_ROOT . '/' . $anterior);
    }

    $nombreArchivo = 'logo-' . bin2hex(random_bytes(6)) . '.png';
    $rutaDestino = TIMBRA_LOGO_DIR . '/' . $nombreArchivo;
    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        timbra_json(['success' => false, 'message' => 'No se pudo guardar el logo en el servidor.'], 500);
    }
    chmod($rutaDestino, 0644);

    $rutaPublica = 'assets/branding/' . $nombreArchivo;
    $pdo->prepare('UPDATE apariencia_config SET logo_path = ?, updated_at = datetime("now") WHERE id = 1')
        ->execute([$rutaPublica]);

    timbra_json(['success' => true, 'message' => 'Logo actualizado.', 'logo_path' => $rutaPublica]);
}

$body = timbra_body_json();
$accion = $body['accion'] ?? '';

if ($accion === 'guardar_colores') {
    $paleta = trim((string) ($body['paleta'] ?? 'personalizada'));
    $colorInk = trim((string) ($body['color_ink'] ?? ''));
    $colorBrand = trim((string) ($body['color_brand'] ?? ''));
    $colorBrandDark = trim((string) ($body['color_brand_dark'] ?? ''));

    $errores = [];
    foreach (['color_ink' => $colorInk, 'color_brand' => $colorBrand, 'color_brand_dark' => $colorBrandDark] as $campo => $valor) {
        if (!timbra_hex_valido($valor)) {
            $errores[$campo] = 'Color no válido (usa el selector).';
        }
    }
    if ($errores) {
        timbra_json(['success' => false, 'message' => 'Revisa los colores.', 'errors' => $errores], 422);
    }

    $pdo->prepare('
        UPDATE apariencia_config
        SET paleta = ?, color_ink = ?, color_brand = ?, color_brand_dark = ?, updated_at = datetime("now")
        WHERE id = 1
    ')->execute([$paleta ?: 'personalizada', $colorInk, $colorBrand, $colorBrandDark]);

    timbra_json(['success' => true, 'message' => 'Colores actualizados.']);
}

if ($accion === 'quitar_logo') {
    $actual = timbra_apariencia()['logo_path'] ?? null;
    if ($actual && str_starts_with($actual, 'assets/branding/') && is_file(TIMBRA_ROOT . '/' . $actual)) {
        @unlink(TIMBRA_ROOT . '/' . $actual);
    }
    $pdo->prepare('UPDATE apariencia_config SET logo_path = NULL, updated_at = datetime("now") WHERE id = 1')->execute();
    timbra_json(['success' => true, 'message' => 'Logo quitado — se vuelve a usar el sello de fábrica.']);
}

timbra_json(['success' => false, 'message' => 'Acción no reconocida.'], 422);
