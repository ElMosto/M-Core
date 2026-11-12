---
name: M-Core Project Context
description: Contexto de arquitectura, convenciones y rutas del proyecto M-Core ERP
---

# M-Core ERP

## Stack
- PHP 8.2 (XAMPP), SQLite (`storage/timbra.sqlite`), Apache.
- Front: Bootstrap 5 + Bootstrap Icons por CDN, JS vanilla.
- Sin build ni dependencias externas.

## Estructura
- Vistas: `*.php` en raíz.
- API: `api/*.php` (JSON puro).
- Helpers: `api/lib/helpers.php`, `auth.php`, `Csd.php`, `XlsxWriter.php`.
- Config: `api/config.php`, DB/migraciones: `api/db.php`.
- Layout: `includes/layout-top.php`, `layout-bottom.php`.
- Assets: `assets/css`, `assets/js`, `assets/avatars`, `assets/branding`.

## Autenticación
- `api/auth.php`: GET whoami+módulos; POST login/logout.
- Sesión por cookie PHP o `Authorization: Bearer <token>`.

## Roles y permisos
- Roles dinámicos en DB (`roles`, `rol_modulos`).
- `admin` bypass fijo; `usuarios`/`roles` exclusivos de admin; `perfil` universal.

## Convenciones de código
- Validar en backend, no solo front.
- Fechas/hora locales.
- Textos en español.
- Endpoints JSON nunca redirigen (401/403).

## Prueba rápida
1. `C:\xampp\php\php.exe -l <archivo>`
2. HTTP smoke contra `http://localhost/room/`.
