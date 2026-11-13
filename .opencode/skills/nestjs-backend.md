---
nombre: nestjs-backend
aplicar_en:
  - apps/api
---

# Skill: NestJS Backend

## Objetivo

Garantizar la implementación correcta de la API REST del Sistema Web de Gestión y Venta de Viajes.

## Cuándo aplicar

En todas las fases que toquen el backend (`apps/api`).

## Instrucciones clave

- Organizar por módulos de dominio: auth, users, drivers, vehicles, trips, reservations, tickets,
  payments, external-sales, passengers, attendance, chat, reports, audit.
- Validar con DTOs + `class-validator` en backend.
- Usar Prisma con transacciones para cualquier operación de capacidad.
- Bloquear fila del viaje con `SELECT ... FOR UPDATE` antes de recalcular disponibilidad.

## Anti-patrones

- Lógica de negocio en controladores sin capa de servicio.
- Confiar en el body del request para estado, rol o precio.
- Operaciones de escritura sin transacción.
