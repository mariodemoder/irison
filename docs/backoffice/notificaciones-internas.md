# Notificaciones internas de Backoffice — Definiciones de Comportamiento

## Propósito

Documenta cómo el backoffice recibe alertas internas (bandeja) sobre el ciclo de suscripción: tipos, cuándo se crean, cómo se reconcilian y cómo se muestran en la UI. Para el comportamiento del ciclo de suscripción en sí, ver [subscriptions.md](subscriptions.md).

## Catálogo de alertas internas

Todas usan la notificación genérica `BackofficeAlertNotification` (canal `database`, sin email).

| Tipo (`data.type`) | Label UI | Disparador proactivo | Derivación por reconciliación |
|---|---|---|---|
| `backoffice_upgrade_requested` | Upgrade solicitado | Evento `UpgradeRequested` (nueva solicitud) | Solicitud `pending` en el índice |
| `trial_expired` | Trial vencido | `TrialLifecycleService::activateReadOnlyIfDue` | `trial_ends_at <= now` con estado trial/read-only |
| `trial_converted` | Trial a pago | Checkout fake / `SubscribeController` / webhook `checkout.session.completed` (solo si previo era trial) | `active` + `trial_ends_at <= now` |
| `subscription_cancelled` | Suscripción cancelada | `BillingController` / `ClinicManagementService` / webhook `customer.subscription.deleted` | `subscription_status ∈ {canceled, cancelled}` |

## Notificación genérica `BackofficeAlertNotification`

- Clase: `modules/Notifications/Backoffice/Notifications/BackofficeAlertNotification.php`.
- Canal: solo `database` (bandeja interna de `admin_users`).
- Destino: todos los `AdminUser` con `is_active = true`.
- Payload en `data`: `type`, `clinic_id`, `clinic_name`, `message` + extras según tipo (`request_id`, `requested_plan`, `requester_name`, `plan`, `reason`).

## Reconciliación en cada carga del índice de clínicas

`ClinicController::index()` → `BackofficeAlertService::reconcileMany($clinics->getCollection())` (`app/Http/Controllers/Backoffice/ClinicController.php:42`).

Comportamiento:

1. Carga los `AdminUser` activos y las claves de notificaciones `BackofficeAlertNotification` ya existentes.
2. Carga las solicitudes de upgrade `pending` de las clínicas de la página.
3. Por cada clínica: `applicableAlertKeys()` deriva las alertas del estado actual.
4. Crea las notificaciones faltantes por admin activo, con **dedupe** por clave `type|clinic_id|admin_id`.
5. Adjunta `$clinic->backoffice_alerts` (array de tipos) a cada clínica para la vista.

### Reglas de la reconciliación

- **Idempotente**: recargar el índice no duplica notificaciones (verificado por test).
- **No destructiva**: no borra notificaciones existentes aunque la condición desaparezca.
- **Sin backfill retroactivo**: deriva del estado actual, no reconstruye el histórico (un trial vencido convertido a pago da `trial_converted`, no `trial_expired`).
- **Solo página actual**: reconcilia las clínicas visibles de la página, no todo el tenant.

## UI

### Dropdown de notificaciones

`resources/views/backoffice/partials/notifications.blade.php`:

- Lista las notificaciones no leídas del admin con badge de contador (`9+` si supera).
- Enlace: `request_id > 0` → `backoffice.subscription-requests.index`; `clinic_id > 0` → `backoffice.clinics.show`.
- Marcar una leída: `POST` vía `fetch` en `@click` a `backoffice.notifications.read`.
- "Marcar todas como leídas": `POST backoffice.notifications.read-all`.

### Badges en la tabla de clínicas

`resources/views/backoffice/clinics/index.blade.php:95` — mapa `$alertBadges` por tipo:

| Tipo | Label | Clase |
|---|---|---|
| `backoffice_upgrade_requested` | Upgrade pendiente | `bg-amber-100 text-amber-800` |
| `trial_expired` | Trial vencido | `bg-rose-100 text-rose-700` |
| `trial_converted` | Trial a pago | `bg-emerald-100 text-emerald-700` |
| `subscription_cancelled` | Susc. cancelada | `bg-rose-100 text-rose-700` |

Renderiza un badge por cada clave en `$clinic->backoffice_alerts`.

## Rechazo de upgrade (`subscription_rejected`)

- `SubscriptionRejectedNotification` (database + mail al owner de la clínica).
- Disparada por el evento `SubscriptionRejected` en `SubscriptionRequestController::reject()`.
- Registrada en `app/Providers/EventServiceProvider.php:70` y en `email_logs.php` como categoría `subscription_rejected`.

## Archivos clave

- `app/Services/Backoffice/BackofficeAlertService.php` — `notify()`, `applicableAlertKeys()`, `reconcileMany()`, wrappers de alerta.
- `modules/Notifications/Backoffice/Notifications/BackofficeAlertNotification.php`
- `modules/Notifications/Backoffice/Notifications/SubscriptionRejectedNotification.php`
- `app/Http/Controllers/Backoffice/ClinicController.php`
- `modules/Subscriptions/Infrastructure/Controllers/Backoffice/SubscriptionRequestController.php`
- `modules/Subscriptions/Domain/Events/SubscriptionRejected.php`
- `modules/Notifications/Backoffice/Listeners/SendSubscriptionRejectedNotification.php`
- `resources/views/backoffice/partials/notifications.blade.php`
- `resources/views/backoffice/clinics/index.blade.php`

## Tests

- `tests/Feature/Backoffice/BackofficeAlertNotificationsTest.php`
- `tests/Feature/Backoffice/ClinicAlertsReconcileTest.php`
- `tests/Feature/Backoffice/UpgradeRequestNotificationsTest.php`
- `tests/Feature/Backoffice/SubscriptionRejectTest.php`
- `tests/Feature/Notifications/NotificationsTest.php`
