<?php
/**
 * index.php — punto de entrada del sistema.
 * No renderiza nada por sí mismo: solo decide a dónde mandar a quien
 * entra a la raíz del sitio.
 *   - Sin sesión           -> login.php
 *   - Con sesión, con rol  -> su módulo de inicio (timbra_modulo_inicio)
 */

require_once __DIR__ . '/api/lib/auth.php';

$usuarioActual = timbra_usuario_actual();

if (!$usuarioActual) {
    header('Location: login.php');
    exit;
}

header('Location: ' . timbra_modulo_inicio($usuarioActual));
exit;
