# Backoffice Upgrade Flow (Optimizado para Agentes)

Objetivo: resolver incidencias de upgrade sin explorar todo el codebase.

## Regla de negocio vigente

- Caso A: clínica en `trial`:
  - Backoffice aprueba solicitud.
  - El provider retorna `action: checkout_required`.
  - Estado de solicitud: `waiting_payment`.
  - Se genera `checkout_url` (Stripe) y se envía por email.
  - En UI cliente debe aparecer CTA "Ir a pagar en Stripe".

- Caso B: clínica en plan activo (pagado, no trial):
  - Backoffice aprueba solicitud.
  - El provider actualiza la sub existente con prorrateo (Stripe) o auto-completa (Fake).
  - Estado de solicitud: `completed`.
  - Se actualiza `clinic.plan` y `clinic.max_users`.
  - Se crea `BillingPayment` con `method: prorated_upgrade` y el monto real cobrado.
  - Se envía comprobante por email.

## Vista previa de facturación (nuevo)

Antes de aprobar, el modal carga por AJAX un preview del prorrateo:

1. Admin abre modal "Aprobar" → `GET /backoffice/subscription-requests/{id}/preview-upgrade`
2. `Backoffice\SubscriptionRequestController::previewUpgrade()` resuelve el provider activo
3. `Provider::previewUpgrade()`:
   - **Stripe** con sub ID: `Stripe invoice->upcoming()` — cálculo exacto de Stripe
   - **Stripe** sin sub ID: cálculo manual con `config('pricing')` y días restantes
   - **Fake**: cálculo manual con `config('pricing')`
4. El modal muestra: crédito, coste prorrateado, total a pagar hoy, próxima factura
5. **El preview es solo lectura** — no modifica nada en Stripe ni en DB
6. Admin puede cerrar el modal sin aprobar (la solicitud sigue `pending`)

## Arquitectura: Provider-agnostic upgrade

El upgrade ahora se delega al `PaymentProviderInterface`:

```
SubscriptionUpgradeService::approveAndGenerateCheckout()
  → Resolver::resolve()->upgradeSubscription([...])
    → StripePaymentProvider:
        - Tiene sub ID → subscriptions->update() con proration_behavior: always_invoice
        - No tiene sub ID → createCheckout (checkout_required)
    → FakePaymentProvider:
        - Clinic activa (paid) → action: upgraded
        - Trial → action: checkout_required (fake checkout)
  → Según action:
    - 'upgraded' → handlePaymentCompleted() + BillingPayment con amount_charged real
    - 'checkout_required' → generateCheckoutUrl() + CheckoutCreated event
```

## Archivos clave (orden de lectura)

1. `app/Services/PaymentProvider/PaymentProviderInterface.php`
2. `app/Services/PaymentProvider/StripePaymentProvider.php` — métodos `previewUpgrade()` y `upgradeSubscription()`
3. `app/Services/PaymentProvider/FakePaymentProvider.php` — métodos `previewUpgrade()` y `upgradeSubscription()`
4. `app/Services/Subscription/SubscriptionUpgradeService.php` — orquestador
5. `app/Http/Controllers/Backoffice/SubscriptionRequestController.php` — ruta `previewUpgrade`
6. `routes/web.php` — ruta GET `subscription-requests/{sr}/preview-upgrade`
7. `resources/views/backoffice/subscription_requests/index.blade.php` — modal con Alpine.js
8. `app/Http/Controllers/Api/StripeWebhookController.php`
9. `app/Listeners/SendCheckoutEmail.php`
10. `app/Listeners/SendPaymentConfirmationEmail.php`
11. `resources/js/views/Configuration.vue`

## Estados esperados de `subscription_requests`

- `pending`: creada por clínica
- `waiting_payment`: aprobada y esperando checkout (trial o sin sub Stripe)
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

4. Si el preview de facturación falla:
   - Revisar logs con `stripe.preview_upgrade.fallback` — Stripe API puede fallar por timeout.
   - El fallback manual usa `config('pricing')` y días restantes del período actual.

## Pruebas de referencia

- `tests/Feature/Backoffice/ClinicManagementTest.php`
- `tests/Feature/Billing/SubscriptionRequestTest.php`
- `tests/Feature/Billing/StripeWebhookControllerTest.php`
