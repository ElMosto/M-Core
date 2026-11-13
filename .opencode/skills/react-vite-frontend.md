---
nombre: react-vite-frontend
aplicar_en:
  - apps/web
---

# Skill: React + Vite Frontend

## Objetivo

Garantizar la UI del Sistema Web de Gestión y Venta de Viajes con buen manejo de estados.

## Cuándo aplicar

En fases de frontend: 3, 7, 10–11.

## Instrucciones clave

- Estados de UI: loading, error, empty, success en toda pantalla.
- Consumo de API con capa de servicios; no acoplar fetch directamente a componentes.
- React Router para navegación por rol.

## Anti-patrones

- `alert()` o `confirm()` crudos sin manejo de errores.
- Confiar en datos del servidor para habilitar acciones sin validación de backend.
