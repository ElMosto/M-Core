# 🤝 Agentes del Proyecto Viajes

Equipo de agentes especializados para el desarrollo del **Sistema Web de Gestión y Venta de Viajes**.

## Índice de agentes

| Archivo | Rol | Fases en las que interviene |
|---|---|---|
| `lead-arquitecto.md` | Arquitecto / Lead implementador | Todas |
| `backend-engineer.md` | Ingeniero backend NestJS | 1–11 |
| `frontend-engineer.md` | Ingeniero frontend React + Vite + TS | 3, 7, 10–11 |
| `database-engineer.md` | Ingeniero de base de datos | 1, 3, 4, 6, 10 |
| `payments-engineer.md` | Ingeniero de pagos Mercado Pago | 5 |
| `test-engineer.md` | Ingeniero de pruebas | 2–11 |
| `security-engineer.md` | Ingeniero de seguridad | 2, 5, 11 |

## Protocolo de handoff por fase

1. **Fase 1 (Base)**: Lead + Backend + Database.
2. **Fase 2 (Auth)**: Lead + Backend + Security + Tests.
3. **Fase 3 (Viajes)**: Lead + Backend + Database + Frontend (parcial).
4. **Fase 4 (Reservas/capacidad)**: Backend + Database + Tests.
5. **Fase 5 (Pagos)**: Payments + Backend + Tests + Security.
6. **Fase 6 (Ventas externas)**: Backend + Database + Tests.
7. **Fase 7 (Pasajeros/asistencia/Excel)**: Backend + Frontend + Tests.
8. **Fase 8 (Inicio/fin)**: Backend + Tests + Lead.
9. **Fase 9 (Chat)**: Backend + Frontend + Tests.
10. **Fase 10 (Admin/reportes)**: Frontend + Backend + Database + Tests.
11. **Fase 11 (Entrega)**: Lead + Tests + Security.

Cada fase debe cerrar con build y tests en verde antes de pasar a la siguiente.
