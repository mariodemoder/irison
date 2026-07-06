# Backoffice Upgrade Flow (Optimizado para Agentes)

Objetivo: resolver incidencias de upgrade sin explorar todo el codebase.

## Regla de negocio vigente

- Caso A: clínica en `trial`:
  - Backoffice aprueba solicitud.
  - Estado de solicitud: `waiting_payment`.
  - Se genera `checkout_url` (Stripe) y se envía por email.
  - En UI cliente debe aparecer CTA "Ir a pagar en Stripe".

- Caso B: clínica en `basic` activa (no trial):
  - Backoffice aprueba solicitud.
  - Se completa automáticamente (sin pago manual del cliente).
  - Estado de solicitud: `completed`.
  - Se actualiza `clinic.plan` y `clinic.max_users`.
  - Se envía comprobante por email.

## Archivos clave (orden de lectura)

1. `app/Services/Subscription/SubscriptionUpgradeService.php`
2. `app/Http/Controllers/Backoffice/SubscriptionRequestController.php`
3. `app/Http/Controllers/Api/StripeWebhookController.php`
4. `app/Listeners/SendCheckoutEmail.php`
5. `app/Listeners/SendPaymentConfirmationEmail.php`
6. `resources/js/views/Configuration.vue`

## Estados esperados de `subscription_requests`

- `pending`: creada por clínica
- `waiting_payment`: aprobada y esperando checkout (trial)
- `paid`: pago confirmado
- `completed`: upgrade finalizado
- `rejected`: rechazada por backoffice

## Diagnóstico rápido (sin exploración amplia)

1. Si no aparece link de pago en cliente:
   - Verificar que solicitud esté en `waiting_payment` y tenga `checkout_url`.
   - Verificar que `Configuration.vue` consuma `/settings/subscription/history`.

2. Si paga en Stripe y no cambia plan:
   - Revisar webhook `checkout.session.completed`.
   - Buscar solicitud por `stripe_checkout_session_id` o `metadata.subscription_request_id`.
   - Confirmar que `handlePaymentCompleted()` ejecuta `upgradeClinic()`.

3. Si no llega email:
   - Validar listeners (`SendCheckoutEmail`, `SendPaymentConfirmationEmail`).
   - Validar vistas de mail: `emails/upgrade-checkout-link`, `emails/payment-completed`.

## Pruebas de referencia

- `tests/Feature/Backoffice/ClinicManagementTest.php`
- `tests/Feature/Billing/SubscriptionRequestTest.php`
- `tests/Feature/Billing/StripeWebhookControllerTest.php`
