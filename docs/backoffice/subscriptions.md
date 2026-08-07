# Suscripciones — Definiciones de Comportamiento

## Propósito

Documenta el comportamiento del ciclo de suscripción (trial, conversión, cancelación, upgrades) y cómo alimenta las notificaciones internas de backoffice. Para el detalle de las notificaciones en sí, ver [notificaciones-internas.md](notificaciones-internas.md).

## Estados relevantes

- `subscription_status`: `trial`, `trial_warning`, `inactive`, `active`, `past_due`, `canceled`/`cancelled`.
- `status` (operativo): `trial`, `trial_warning`, `trial_read_only`, `active`, `churned`, etc.

## Comportamiento del ciclo

### Trial → vencimiento (`trial_expired`)

`TrialLifecycleService::activateReadOnlyIfDue()` (`app/Services/Trials/TrialLifecycleService.php:138`):

- Precondiciones: `subscription_status ∈ {trial, trial_warning}` y `trial_ends_at <= now` y `status != trial_read_only`.
- Acción: `status = trial_read_only` + alerta `trial_expired`.
- Idempotente: no re-dispara si ya está en `trial_read_only`.

### Trial → pago (`trial_converted`)

Solo se dispara si el **estado previo era `trial` o `trial_warning`**. Puntos de disparo:

| Punto | Archivo |
|---|---|
| Checkout fake | `modules/Subscriptions/Infrastructure/Controllers/BillingController.php` (`createCheckout` → `SubscriptionActivationService`) |
| Suscripción manual (legacy Stripe/Cashier) | `modules/Subscriptions/Infrastructure/Controllers/Api/SubscribeController.php` |
| Webhook Stripe `checkout.session.completed` | `modules/Subscriptions/Infrastructure/Payment/StripeWebhookHandler.php` (`handleCheckoutCompleted`) |

### Cancelación (`subscription_cancelled`)

| Punto | Archivo |
|---|---|
| Cancelación desde clínica | `modules/Subscriptions/Infrastructure/Controllers/BillingController.php` (`cancelSubscription` vía `Resolver::resolveForCancellation`) |
| Cancelación desde backoffice | `app/Services/Backoffice/ClinicManagementService.php` (acepta `$reason` opcional) |
| Webhook Stripe `customer.subscription.deleted` | `modules/Subscriptions/Infrastructure/Payment/StripeWebhookHandler.php` (`handleSubscriptionDeleted`) |

### Solicitud de upgrade (`backoffice_upgrade_requested`)

- `SubscriptionRequestService::createRequest()` dispara el evento `UpgradeRequested`.
- Listener `SendUpgradeRequestNotificationToBackoffice` llama a `BackofficeAlertService::upgradeRequested()`.
- Se notifica a todos los `AdminUser` activos.

### Rechazo de upgrade (`subscription_rejected`)

- `SubscriptionRequestController::reject()` dispara `SubscriptionRejected::dispatch($request)`.
- Listener `SendSubscriptionRejectedNotification` envía `SubscriptionRejectedNotification` (database + mail al owner).
- Reemplazó al antiguo `sendStatusMail()` inexistente.

## Matriz de derivación de alertas (estado actual → alerta)

Algoritmo de `BackofficeAlertService::applicableAlertKeys()` (`app/Services/Backoffice/BackofficeAlertService.php:43`):

| Condición del estado actual | Alerta |
|---|---|
| Existe solicitud de upgrade `pending` | `backoffice_upgrade_requested` |
| `trial_ends_at <= now` y `subscription_status ∈ {trial, trial_warning, inactive}` o `status ∈ {trial_read_only, churned}` | `trial_expired` |
| `trial_ends_at <= now` y `subscription_status ∈ {active, past_due, canceled, cancelled}` | `trial_converted` |
| `subscription_status ∈ {canceled, cancelled}` | `subscription_cancelled` |

Pueden coexistir varias alertas (p. ej. clínica cancelada con trial vencido → `subscription_cancelled` + `trial_converted`).

## Reglas

- **Sin backfill retroactivo**: un trial vencido que luego se convierte a pago deriva `trial_converted`, nunca `trial_expired`.
- `trial_converted` se deriva del estado actual (clínica `active` con trial vencido); no es un registro de evento pasado.
- La reconciliación en cada carga del índice de clínicas materializa las alertas pendientes sin depender del scheduler (ver `notificaciones-internas.md`).

## Normalización de la clínica al activar la suscripción

Cuando se confirma un pago y se activa la suscripción, la clínica se normaliza para que **`subscription_status='active'` sea la fuente de verdad** y no queden residuos de trial en el `status` operativo.

`SubscriptionActivationService::activateClinic()` (`modules/Subscriptions/Application/Services/SubscriptionActivationService.php`) y las rutas legacy (`FakeSubscribeController`, `SubscribeController`) y `SubscriptionUpgradeService::upgradeClinic()`:

- `status = 'active'`
- `churned_at = null`
- `plan` (de `options['plan']`, default `clinic->plan ?? 'basic'`, validado a `basic|pro|enterprise`)
- `max_users = Clinic::PLAN_USER_LIMITS[plan]`
- `trial_ends_at = null`, `subscription_status = 'active'`

`options['plan']` se pasa desde `BillingController` (checkout fake/confirm/fake-success → `'basic'`) y desde `StripeWebhookHandler` (webhook `checkout.session.completed` → `metadata.plan ?? clinic.plan ?? 'basic'`).

### Trampa conocida: status de trial obsoleto

Un bug histórico dejaba clínicas con `subscription_status='active'`, `plan='basic'` pero `status='trial_read_only'` (p. ej. clínica real id=9). Ese residuo rompía:

1. **`MeController::__invoke()`**: la rama `if ($tenantStatus === 'trial_read_only')` ganaba sobre `subscription_status='active'` → `/me` devolvía `status='trial_read_only'` + `read_only_no_transactions=true` → el frontend mostraba el cartel de trial vencido, ocultaba los `.btn-primary` (modo readonly) y no renderizaba "Solicitar upgrade".
2. **Backoffice** (`resources/views/backoffice/clinics/index.blade.php`): el badge salía rojo aunque la suscripción estuviera activa.

Arreglos aplicados:
- `MeController`: si `subscription_status === 'active'` → `status='active'` (y `read_only_no_transactions=false`) **antes** de evaluar el `status` operativo.
- `Clinic::backofficeStatusColor()` (`app/Models/Clinic.php`): una suscripción activa siempre es `green`, aunque `status` conserve `trial_read_only`/`churned`. Devuelve `green|red|blue`; el blade del índice la usa en vez de la lógica inline.
- La normalización de activación evita que vuelva a ocurrir.

Tests de regresión: `tests/Feature/Billing/BillingLifecycleTest.php`, `tests/Feature/Billing/SubscriptionRequestTest.php`, `tests/Feature/Backoffice/ClinicAlertsReconcileTest.php`, `tests/Unit/ClinicBackofficeStatusTest.php`.

## Scheduler en desarrollo

- El ciclo de trial depende de `php artisan schedule:work` (comando `trials:process`).
- En WAMP/dev no hay cron, por eso `scripts/start_dev.py` incluye `php artisan schedule:work` en el arranque.
- La reconciliación al cargar el índice de clínicas es la red de seguridad cuando el scheduler no corre.

## Archivos clave

- `app/Services/Trials/TrialLifecycleService.php`
- `modules/Subscriptions/Application/Services/SubscriptionRequestService.php`
- `modules/Subscriptions/Application/Services/SubscriptionUpgradeService.php`
- `modules/Subscriptions/Application/Services/SubscriptionActivationService.php` (activa suscripción/clínica tras pago confirmado, para cualquier provider)
- `modules/Subscriptions/Infrastructure/Payment/Resolver.php` (resuelve provider activo por `billing.provider`)
- `modules/Subscriptions/Infrastructure/Payment/StripePaymentProvider.php` / `FakePaymentProvider.php` (implementan `PaymentProviderInterface`)
- `modules/Subscriptions/Infrastructure/Payment/StripeWebhookHandler.php` (webhook único; reemplaza al antiguo `StripeWebhookController`)
- `modules/Subscriptions/Infrastructure/Controllers/BillingController.php` (checkout/confirm/cancel/webhook)
- `modules/Subscriptions/Infrastructure/Controllers/Api/SubscribeController.php` (legacy Stripe-only)
- `modules/Subscriptions/Infrastructure/Controllers/Backoffice/SubscriptionRequestController.php`
- `modules/Subscriptions/Domain/Events/UpgradeRequested.php`, `SubscriptionRejected.php`, `PaymentCompleted.php`, `SubscriptionUpgraded.php`, `CheckoutCreated.php`
- `app/Services/Backoffice/BackofficeAlertService.php`
- `app/Services/Backoffice/ClinicManagementService.php`
- `modules/Notifications/Backoffice/Listeners/SendUpgradeRequestNotificationToBackoffice.php`
- `modules/Notifications/Backoffice/Listeners/SendSubscriptionRejectedNotification.php`

## Tests

- `tests/Feature/Billing/BillingLifecycleTest.php`
- `tests/Feature/Billing/StripeWebhookControllerTest.php`
- `tests/Feature/Billing/BillingCancellationTest.php`
- `tests/Feature/Trials/TrialLifecycleTest.php`
- `tests/Feature/Backoffice/UpgradeRequestNotificationsTest.php`
- `tests/Feature/Backoffice/SubscriptionRejectTest.php`
- `tests/Feature/Backoffice/ClinicAlertsReconcileTest.php`
- `tests/Unit/ClinicBackofficeStatusTest.php`
