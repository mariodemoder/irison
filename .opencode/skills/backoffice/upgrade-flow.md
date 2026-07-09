# Backoffice Upgrade Flow (Optimizado para Agentes)

Objetivo: resolver incidencias de upgrade sin explorar todo el codebase.

## Regla de negocio vigente

- Caso A: clínica en `trial`:
  - Backoffice aprueba solicitud.
  - El provider retorna `action: checkout_required`.
  - Estado de solicitud: `waiting_payment`.
  - Se genera `checkout_url` (Stripe) con el **precio completo del plan destino** (sin prorrateo).
  - En UI cliente debe aparecer CTA "Ir a pagar en Stripe".
  - **No hay crédito** — el clinic nunca pagó el plan actual, así que se cobra el total del nuevo plan.

- Caso B: clínica en plan activo (pagado, no trial):
  - Backoffice aprueba solicitud.
  - El provider actualiza la sub existente con prorrateo (Stripe) o auto-completa (Fake).
  - Estado de solicitud: `completed`.
  - Se actualiza `clinic.plan` y `clinic.max_users`.
  - Se crea `BillingPayment` con `method: prorated_upgrade` y el monto real cobrado.
  - Se envía comprobante por email.

## Vista previa de facturación

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

### Comportamiento por tipo de clínica

- **Trial → Plan paid**: el preview muestra solo el precio completo del nuevo plan (sin línea de crédito). Ej: "Plan Pro (precio completo): 89.00 EUR".
- **Basic paid → PRO**: el preview muestra el prorrateo completo con crédito por días no usados. Ej: "Crédito: -28.03 EUR, Total: 60.97 EUR".

### Price ID resolution para upgrades

- `resolvePriceIdForPlan($plan)` resuelve el price ID desde `config('services.stripe.upgrade_products.{plan}')` o `config('services.stripe.price_id')`.
- Para trial → paid, se pasa `price_id` explícito al `createCheckout()` para evitar el fallback al precio de Basic.
- `resolvePriceIdForPlan()` solo usa `STRIPE_PRICE_ID` como fallback si el plan es `basic`.

## Arquitectura: Provider-agnostic upgrade

El upgrade ahora se delega al `PaymentProviderInterface`:

```
SubscriptionUpgradeService::approveAndGenerateCheckout()
  → Resolver::resolve()->upgradeSubscription([...])
    → StripePaymentProvider:
        - Tiene sub ID → subscriptions->update() con proration_behavior: always_invoice
        - No tiene sub ID → createCheckout (checkout_required) con price_id explícito del plan destino
    → FakePaymentProvider:
        - Clinic activa (paid) → action: upgraded
        - Trial → action: checkout_required (fake checkout)
  → Según action:
    - 'upgraded' → handlePaymentCompleted() + BillingPayment con amount_charged real
    - 'checkout_required' → generateCheckoutUrl() + CheckoutCreated event

Webhook checkout.session.completed (hardened):
  1. Busca SubscriptionRequest por stripe_checkout_session_id
  2. Fallback: metadata.subscription_request_id
  3. Fallback: clinic_id + status='waiting_payment' (más reciente)
  4. Si lo encuentra → handlePaymentCompleted() → upgradeClinic()
  5. Safety net: si clinic.plan sigue en 'basic' pero hay solicitud completed → actualiza plan directamente
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
   - El webhook busca SubscriptionRequest en 3 niveles:
     1. Por `stripe_checkout_session_id`
     2. Por `metadata.subscription_request_id`
     3. Por `clinic_id` + `status = 'waiting_payment'` (fallback)
   - Si la solicitud ya estaba `completed` (doble webhook), el safety net detecta que el plan sigue en `basic` y lo actualiza desde la solicitud completada.
   - Verificar logs: `Procesando webhook de checkout.session.completed para solicitud de upgrade` o `Safety net: plan actualizado via webhook`.

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
