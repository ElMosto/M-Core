---
nombre: stripe-integracion
aplicar_en:
  - apps/api
---

# Skill: Integración Stripe

## Objetivo

Garantizar un cobro seguro con Stripe Checkout y la confirmación de reservas solo con pagos
verificados.

## Cuándo aplicar

Fase 5 del roadmap (junto con Mercado Pago).

## Instrucciones clave

- Usar **Checkout Sessions** alojadas por Stripe; nunca manipular ni almacenar datos de tarjeta.
- Verificar `Stripe-Signature` con HMAC-SHA256 sobre el **cuerpo sin procesar** y tolerancia de
  5 minutos antes de parsear el evento.
- Ante una notificación, consultar la sesión real (`GET /v1/checkout/sessions/:id`) y confirmar
  únicamente si `payment_status = paid`.
- Validar que `amount_total` (en centavos) y `currency` coincidan con el pago local.
- Usar `metadata.paymentId` / `client_reference_id` para vincular la sesión con el pago local.
- Idempotencia: actualizar el pago con condición de estado y no volver a emitir boletos ante
  webhooks repetidos o concurrentes.
- Si el pago es aprobado pero la reserva ya no se puede confirmar, dejar el pago en `REVISION`
  para conciliación manual.

## Anti-patrones

- Confirmar un pago desde `success_url`.
- Parsear el JSON antes de validar la firma.
- Confiar en el monto o la referencia enviados por el cliente.
- Duplicar boletos por reintentos de webhooks.
