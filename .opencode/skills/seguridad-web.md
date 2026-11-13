---
nombre: seguridad-web
aplicar_en:
  - apps/api
---

# Skill: Seguridad Web

## Objetivo

Aplicar los requisitos mínimos de seguridad del sistema.

## Cuándo aplicar

Fases 2, 5 y 11.

## Instrucciones clave

- Argon2id para contraseñas.
- Guards de rol y recurso; validación en backend.
- Rate limiting, CORS restrictivo, CSRF si se usan cookies.
- Logs sin contraseñas, tokens ni datos bancarios.

## Anti-patrones

- Hashes en texto plano.
- Endpoints protegidos solo en frontend.
- Exposición de secrets en respuestas.
