<?php
require_once __DIR__ . '/api/lib/auth.php';
timbra_cerrar_sesion();
header('Location: login.php');
exit;
