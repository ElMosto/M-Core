# M-Core ERP — guía para OpenCode

Proyecto PHP 8.2 + SQLite servido por XAMPP en `C:\xampp\htdocs\room`.
URL local: `http://localhost/room/`. Base de datos: `storage/timbra.sqlite`.

## Convenciones
- Sin frameworks ni build: PHP puro + Bootstrap 5/Icons por CDN.
- Vistas en raíz (`*.php`), lógica en `api/*.php`, helpers en `api/lib/`.
- Todas las respuestas de API en JSON; vistas solo HTML + fetch.
- Fechas/hora en zona local del usuario.
- Idioma de UI y mensajes: español.

## Comandos útiles
- Lint PHP: `php -l <archivo>` (binario en `C:\xampp\php\php.exe`).
- Lint masivo: recorrer `*.php` con `php -l`.
- Diagnóstico servidor: ya eliminado `verificar-servidor.php`; usar `php -m` para extensiones.

## Pruebas rápidas
- Login: `POST http://localhost/room/api/auth.php` con `{"accion":"login","email":"admin@m-core.local","password":"CambiaEstaClave123"}`.
- Whoami: `GET http://localhost/room/api/auth.php`.
- Endpoints protegidos requieren cookie de sesión o `Authorization: Bearer <token>`.

## No tocar sin confirmar
- `storage/timbra.sqlite` (datos reales).
- `storage/.htaccess`.
- Credenciales del admin sembrado (solo instalaciones nuevas).

## Al modificar
- Mantener estilo y patrones existentes.
- Validar con `php -l` cada archivo editado.
- Probar el endpoint afectado por HTTP.
