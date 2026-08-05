# 📋 Plan de Implementación: Pago Completo Stripe + Suscripciones

> Documento vivo. Actualizado con la arquitectura real del sistema (Stripe Hosted Checkout + módulo Subscriptions + flujo de upgrade por solicitud).

## 🔍 ESTADO ACTUAL (verificado en código)

### ✅ Backend — Activación de suscripción (100%)
- **BillingController** (`app/Http/Controllers/BillingController.php`)
  - `POST /billing/checkout` → crea `BillingPayment` (pending) y una sesión de checkout con el proveedor (`Resolver::resolve()`), devuelve `checkout_url` para redirigir al Hosted Checkout de Stripe. Con proveedor `fake` marca el pago `paid` y activa la suscripción al instante.
  - `POST /billing/confirm` → valida la sesión Stripe (`payment_status = paid | status = complete`), marca el pago `paid`, crea/actualiza `Subscription` a `active` y actualiza la clínica (`subscribed_at`, `subscription_status='active'`, `subscription_provider='stripe'`). Envía `SubscriptionActivatedMail` solo si es suscripción nueva.
  - `POST /billing/cancel` → cancela en Stripe (si aplica), marca la suscripción `canceled` y encola `SubscriptionCanceledInternalMail` a `billing.cancellation_notification_to`.
  - `POST /billing/webhook` → delega en el proveedor (`handleWebhook`). Endpoint público.
- **Modelos**: `BillingPayment` (clinic_id, amount, currency, status, provider, provider_ref, method), `Subscription` (status, trial_ends_at, current_period_end, stripe_customer_id, stripe_subscription_id), campos de clínica (`subscription_status`, `subscription_provider`, `subscription_reference`, `subscribed_at`, `trial_ends_at`, `plan`, `max_users`).
- **PaymentProvider** (`app/Services/PaymentProvider/`): `PaymentProviderInterface`, `Resolver`, `FakePaymentProvider`, `StripePaymentProvider`.
  - `createCheckout` → `mode=subscription`, `success_url`/`cancel_url` hacia la SPA.
  - `previewUpgrade` → prorrateo (ver Reglas de negocio).
  - `upgradeSubscription` → checkout nuevo si no hay suscripción Stripe; si existe, `subscriptions->update` con prorrateo nativo (`always_invoice`, `billing_cycle_anchor=now`).

### ✅ Backend — Flujo de upgrade basic→pro/enterprise (100%)
- **`SubscriptionRequest`** con estados `pending → waiting_payment → paid → completed` / `rejected` (`app/Services/Subscription/SubscriptionRequestService.php`).
- **`SubscriptionUpgradeService`**: `approveAndGenerateCheckout()` (aprueba → genera checkout del proveedor → guarda `checkout_url`/`stripe_checkout_session_id` → `CheckoutCreated`), `handlePaymentCompleted()` (marca `paid` → `upgradeClinic()` cambia `plan` + `max_users` → `completed` → eventos `PaymentCompleted` + `SubscriptionUpgraded`).
- **`SubscriptionRequestController`** (`app/Http/Controllers/Api/SubscriptionRequestController.php`): `store` (crea solicitud `pending`, valida upgrade ascendente), `confirmUpgrade` (procesa si queda pendiente y resuelve `hosted_invoice_url`).
- **Aprobación desde backoffice** (`routes/web.php`): `subscription-requests` index / preview-upgrade / approve / reject (guard `admin`). El E2E aprueba vía `PATCH /backoffice/subscription-requests/{id}/approve`.
- **Webhook** `POST /stripe/webhook` → `StripeWebhookController` procesa `checkout.session.completed` (nueva suscripción y upgrades) y `invoice.payment_failed`. Endpoint público con firma.

### ✅ Frontend (100% funcional)
- **`resources/js/views/BillingRequired.vue`** → página de activación (ruta `/billing/required`). Inicia `/billing/checkout`, redirige a Stripe, "Ya pagué, verificar estado" (`/billing/confirm`), fallback local (`/subscribe/fake`) en DEV ante `STRIPE_UNREACHABLE`.
- **`resources/js/views/settings/Subscription.vue`** → plan actual, features, solicitud de upgrade, historial con enlace "Ir a pagar", popup de éxito con factura al volver (`/settings/subscription/confirm-upgrade`), backup de datos.
- **`Configuration.vue`** → sección billing (checkout/cancelar).
- **Guardas**: `MainLayout.vue` y el interceptor de `services/api.js` redirigen a `/billing/required` ante `SUBSCRIPTION_REQUIRED` / estados bloqueados.

### 🧪 Testing (base corregida + E2E)
- Infra de módulo: `modules/Subscriptions/Tests/` (`TestCase`, `Concerns/InteractsWithSubscriptions`) + testsuite `Subscriptions` en `phpunit.xml` + autoload-dev en `composer.json`.
- **E2E `UpgradePlanE2ETest`**: flujo completo trial→pro `pending → waiting_payment → paid → completed` (backoffice + webhook), verifica plan, `max_users`, eventos. Verde (13 assertions).
- **Suite Billing**: `BillingCancellationTest`, `PlanChangeEmailTest`, `StripeWebhookControllerTest`, `SubscriptionRequestTest` corregidos (mails en cola → `assertQueued`; notificaciones reales del módulo → `Notification::fake`). 29 tests verdes.
- **Deuda pre-existente**: 23 tests rotos ajenos a billing (Auth/Appointments/Profile/Clinic) por esquema SQLite (`users` sin `remember_token`, `patients.clinic_id` NOT NULL). Ver checklist pendiente.

---

## 🧭 ARQUITECTURA REAL (flujos)

### Flujo A — Activación / renovación (nueva suscripción)
```
SPA (BillingRequired) ──POST /billing/checkout──▶ BillingController
      │                                              │ crea BillingPayment(pending) + sesión Stripe
      │ ◀── checkout_url ──                          ▼
      │  window.location = checkout_url       Stripe Hosted Checkout (modo subscription)
      │                                              │ pago OK
      │ ◀── redirect ──  {app.url}/billing/required?checkout=success&session_id=...
      ▼
   POST /billing/confirm ──▶ valida sesión → BillingPayment=paid → Subscription=active
   → clínica activa → SubscriptionActivatedMail  (webhook checkout.session.completed como respaldo)
```

### Flujo B — Upgrade basic→pro/enterprise
```
Clínica ──POST /settings/subscription/request──▶ SubscriptionRequest(pending) → UpgradeRequested
   ▼
Backoffice ──preview-upgrade → PATCH .../approve──▶ waiting_payment + checkout_url (CheckoutCreated)
   ▼
Clínica paga en el checkout_url (Stripe Hosted Checkout)
   ▼
Webhook checkout.session.completed ──▶ handlePaymentCompleted: paid → upgradeClinic(plan, max_users) → completed
   → eventos PaymentCompleted + SubscriptionUpgraded
   ▼
Clínica vuelve → /settings/subscription/confirm-upgrade → factura (hosted_invoice_url) + email backup
```

## ⚖️ Reglas de negocio clave

- **Prorrateo (upgrade)**:
  - Trial→paid: **cobra el precio completo** del nuevo plan (sin crédito).
  - Basic pagado→PRO: usa prorrateo nativo de Stripe (`always_invoice`, `billing_cycle_anchor=now`).
- **Price IDs**: cada plan tiene su producto. `resolvePriceIdForPlan()` solo hace fallback a `STRIPE_PRICE_ID` para el plan `basic`. El checkout de upgrade pasa `price_id` explícito para evitar fallback incorrecto.
- **Estados**: una sola suscripción activa por clínica; la activación no reenvía `SubscriptionActivatedMail` si ya estaba activa (renovación no dispara email de bienvenida).
- **Idempotencia**: `handlePaymentCompleted` y el webhook no duplican procesamiento si la solicitud ya está `completed`.

---

## 📊 CHECKLIST PENDIENTE (real)

### Pendiente 1 — Corregir los 23 tests pre-existentes (prioridad alta)
- [ ] Arreglar esquema SQLite: `users` sin columna `remember_token` (migración custom `2025_12_20_131151_create_clinic_saas_tables_welcome.php` pisa el default).
- [ ] `patients.clinic_id` NOT NULL rompe tests que crean pacientes sin clínica (`ClinicScopeTest`).
- [ ] Fallos resultantes en `AuthenticationTest`, `EmailVerificationTest`, `PasswordConfirmationTest`, `PasswordResetTest`, `PasswordUpdateTest`, `ProfileTest`, `RegistrationTest`, `RoleBasedAccessTest`, `AppointmentAvailabilityTest`.

### Pendiente 2 — Limpieza de legacy `POST /stripe/checkout` (prioridad media)
- [ ] `StripeCheckoutController` (`routes/api.php:121`) no lo usa el frontend y usa `price_id` genérico (`config('services.stripe.price_id')`).
- [ ] Decidir: eliminar endpoint + controlador, o alinearlo con `resolvePriceIdForPlan()`.

### Pendiente 3 — Cobertura de tests de activación (prioridad media)
- [ ] E2E de activación: `/billing/checkout` (fake) → suscripción `active` + `SubscriptionActivatedMail`.
- [ ] E2E de renovación: clínica ya activa → no reenvía `SubscriptionActivatedMail`.
- [ ] E2E/unit de `cancelSubscription` completo (cancela en proveedor + `canceled` + mail interno).

### Pendiente 4 — Coherencia de precios en frontend (prioridad baja)
- [ ] `BillingRequired.vue` hardcodea "Profesional 29€/mes" → leer de `config/pricing.php` vía `/pricing`.

### Pendiente 5 — Validación de configuración en producción (deploy)
- [ ] Verificar `services.stripe.upgrade_products.{pro,enterprise}` y `STRIPE_PRICE_ID` por plan en prod.
- [ ] Verificar webhook `checkout.session.completed` + `invoice.payment_failed` configurados en el Dashboard de Stripe.
- [ ] Colas (default `database`) con supervisión activa para mails en cola.

---

## 🔗 REFERENCIAS IMPORTANTES

- `app/Http/Controllers/BillingController.php` — activación/cancelación
- `app/Http/Controllers/Api/StripeCheckoutController.php` — legacy (ver Pendiente 2)
- `app/Http/Controllers/Api/StripeWebhookController.php` — webhooks (session.completed, invoice.payment_failed)
- `app/Http/Controllers/Api/SubscriptionRequestController.php` — store + confirmUpgrade
- `app/Http/Controllers/Api/SubscriptionController.php` — show/history
- `app/Services/Subscription/SubscriptionUpgradeService.php` / `SubscriptionRequestService.php`
- `app/Services/PaymentProvider/` — Interface, Resolver, Fake, Stripe
- `app/Models/Subscription.php`, `app/Models/BillingPayment.php`, `app/Models/SubscriptionRequest.php`
- `routes/api.php` (billing/subscription), `routes/web.php` (backoffice subscription-requests)
- `resources/js/views/BillingRequired.vue`, `resources/js/views/settings/Subscription.vue`
- `config/pricing.php`, `config/billing.php`, `config/services.php` (stripe.*)
- Tests: `modules/Subscriptions/Tests/`, `tests/Feature/Billing/`
- Docs: `AGENTS.md` (Pitfalls), `docs/backoffice/`
