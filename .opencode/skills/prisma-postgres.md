---
nombre: prisma-postgres
aplicar_en:
  - prisma
  - apps/api
---

# Skill: Prisma + PostgreSQL

## Objetivo

Garantizar el schema, migraciones y control de concurrencia de la base de datos.

## Cuándo aplicar

En fases de base de datos: 1, 3, 4, 6, 10.

## Instrucciones clave

- Fechas con `TIMESTAMPTZ`.
- `iniciado_at` / `finalizado_at` como `DateTime?`.
- Restricciones únicas para boletos y pagos (idempotencia).
- Migraciones reproducibles con `prisma migrate`.

## Anti-patrones

- Soft deletes arbitrarios sin política definida.
- Capacidad del viaje > capacidad operativa del vehículo sin validación.
