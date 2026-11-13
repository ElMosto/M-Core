---
nombre: mercadopago-integracion
aplicar_en:
  - apps/api
---

# Skill: Integración Mercado Pago

## Objetivo

Garantizar el flujo seguro de pagos y notificaciones con Mercado Pago.

## Cuándo aplicar

Fase 5 del roadmap.

## Instrucciones clave

- Checkout mediante preferencias / mecanismo oficial vigente.
- Webhook con verificación de notificación.
- Consultar estado real del pago cuando sea necesario.
- Idempotencia ante reintentos.
- No confirmar por return URL de éxito.

## Anti-patrones

- Confirmar pago sin verificación.
- Duplicar boletos por notificaciones repetidas.
- Almacenar datos de tarjeta.
