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
| Checkout fake | `app/Http/Controllers/BillingController.php:143` |
| Suscripción manual | `app/Http/Controllers/Api/SubscribeController.php:70` |
| Webhook Stripe `checkout.session.completed` | `app/Http/Controllers/Api/StripeWebhookController.php:133` |

### Cancelación (`subscription_cancelled`)

| Punto | Archivo |
|---|---|
| Cancelación desde clínica | `app/Http/Controllers/BillingController.php:444` |
| Cancelación desde backoffice | `app/Services/Backoffice/ClinicManagementService.php:416` (acepta `$reason` opcional) |
| Webhook Stripe `customer.subscription.deleted` | `app/Http/Controllers/Api/StripeWebhookController.php:307` |

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

## Scheduler en desarrollo

- El ciclo de trial depende de `php artisan schedule:work` (comando `trials:process`).
- En WAMP/dev no hay cron, por eso `scripts/start_dev.py` incluye `php artisan schedule:work` en el arranque.
- La reconciliación al cargar el índice de clínicas es la red de seguridad cuando el scheduler no corre.

## Archivos clave

- `app/Services/Trials/TrialLifecycleService.php`
- `app/Services/Subscription/SubscriptionRequestService.php`
- `app/Services/Backoffice/BackofficeAlertService.php`
- `app/Services/Backoffice/ClinicManagementService.php`
- `app/Http/Controllers/BillingController.php`
- `app/Http/Controllers/Api/SubscribeController.php`
- `app/Http/Controllers/Api/StripeWebhookController.php`
- `app/Http/Controllers/Backoffice/SubscriptionRequestController.php`
- `app/Events/UpgradeRequested.php`, `app/Events/SubscriptionRejected.php`
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
