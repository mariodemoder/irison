---
name: billing
description: Use when touching Stripe, subscriptions, plans, billing webhooks, upgrade proration, price ID resolution, Stripe customer sync, read-only post-trial policy, or the XLSX clinic backup export.
---

# Billing Skill — Planes y Suscripciones

## Router rápido (anti-exploración)

- Si el incidente es de upgrade aprobado en backoffice pero no reflejado en cliente/pago:
  - Cargar `../backoffice/upgrade-flow.md` antes de explorar código.

## Planes disponibles (`Clinic::PLAN_USER_LIMITS`)

Definidos en `app/Models/Clinic.php`:
| Plan | Precio | Máx usuarios | Stripe Product |
|------|--------|-------------|----------------|
| `basic` | 29€/mes | 1 | — (usa `STRIPE_PRICE_ID` env) |
| `pro` | 89€/mes | 10 | `STRIPE_PRODUCT_PRO` env |
| `enterprise` | 189€/mes | ilimitado | `STRIPE_PRODUCT_ENTERPRISE` env |

### Price ID resolution
- `resolvePriceIdForPlan($plan)` busca primero en `config('services.stripe.upgrade_products.{plan}')` (producto Stripe con default_price o precio recurring activo).
- Solo para `basic` usa `STRIPE_PRICE_ID` como fallback.
- Para upgrade trial→paid, se pasa `price_id` explícito al checkout para evitar que caiga al precio de Basic.

### Trampa: status operativo obsoleto tras activación
- Al activar una suscripción, **normalizar siempre** `status='active'`, `churned_at=null`, `plan` y `max_users` (ver `SubscriptionActivationService::activateClinic()`, `FakeSubscribeController`, `SubscribeController`, `SubscriptionUpgradeService::upgradeClinic()`).
- Si una clínica muestra "trial vencido" pero tiene `subscription_status='active'`, el `status` operativo quedó como `trial_read_only`/`churned` (residuo). `MeController` trata `subscription_status='active'` como autoritativo y `Clinic::backofficeStatusColor()` fuerza verde en el backoffice. Detalle: `docs/backoffice/subscriptions.md`.

## Restricción de usuarios por plan

- `clinics.max_users` default: 3 (basic)
- Al cambiar plan via backoffice → `max_users` se actualiza automáticamente desde `Clinic::PLAN_USER_LIMITS`
- Al registrar clínica → se setea `plan=basic`, `max_users=3`
- Límite controlado en: `app/Services/Team/TeamUserService.php:87-90`
- Frontend muestra "X / Y usuarios" en `resources/js/views/team/Team.vue`

## Stripe Error Handling
- Unreachable Stripe → backend returns `503` with `code=STRIPE_UNREACHABLE` from `modules/Subscriptions/Infrastructure/Controllers/BillingController.php`
- UI fallback in `resources/js/views/BillingRequired.vue` (local activation in dev only)
- Local fallback: `POST /api/subscribe/fake` (`modules/Subscriptions/Infrastructure/Controllers/Api/FakeSubscribeController.php`)

## Stripe Customer Sync
Mandatory for Backoffice visibility. Persist on clinic:
- `clinics.stripe_id`
- `clinics.stripe_customer_id`

Write points (must keep synced):
- `modules/Subscriptions/Infrastructure/Controllers/BillingController.php` — `confirmCheckout`
- `modules/Subscriptions/Infrastructure/Payment/StripeWebhookHandler.php` — `checkout.session.completed`

## `clinic.subscribed_at` — Must Set on Activation
Set to now on:
- `POST /api/subscribe` (`modules/Subscriptions/Infrastructure/Controllers/Api/SubscribeController.php`)
- `POST /api/subscribe/fake` (`modules/Subscriptions/Infrastructure/Controllers/Api/FakeSubscribeController.php`)
- `POST /api/billing/confirm` (`modules/Subscriptions/Infrastructure/Controllers/BillingController.php`)
- Stripe webhook `checkout.session.completed` (`modules/Subscriptions/Infrastructure/Payment/StripeWebhookHandler.php`)

Toda activación de suscripción (fake o Stripe, webhook o confirm) pasa por `SubscriptionActivationService::activateClinic()` (`modules/Subscriptions/Application/Services/SubscriptionActivationService.php`), que centraliza `subscribed_at`, estado de clínica, `ActivityLogger`, `trial_converted` y el email de activación.

Webhook hardening: support fallback by `metadata.clinic_id` and by Stripe customer ID (not just `customer_email`).

## Pre-Payment Data Gate (Clinic Tab)
Before activating paid plan, require:
- Valid Spanish tax ID (DNI/NIE/CIF)
- Non-empty clinic address

Frontend guard: `resources/js/views/Configuration.vue` (disabled buttons + toast + redirect to Clinic tab).

## BillingRequired Copy — State-Aware
- Trial expired (`blocked` / `trial_read_only`): show urgency copy
- Trial active: positive onboarding copy + days left if available

## Modo solo lectura post-trial (política)

- Tras el fin del trial (o del periodo pagado de una cancelación) la clínica entra en solo lectura: **solo puede ver datos, activar la cuenta de pago y descargar el backup XLSX**. Nada de guardados.
- Enforcement: middleware `check.subscription` (permite GET + lista blanca de checkout), **rutas admin de Booking también bajo `check.subscription`** (`modules/Booking/Routes/api.php`), guarda axios en `api.js`, CSS `.readonly-mode` con `allow-readonly-action`, y bloqueo de la reserva pública en `PublicBookingService::ensureClinicCanBeBooked()`.
- Detalle completo: `docs/backend/read-only-policy.md`. Tests: `tests/Feature/Booking/BookingReadOnlyPolicyTest.php`.

## Backup Export (.xlsx) — Configuración → Suscripción

Button en `resources/js/views/settings/Subscription.vue:94` (clase `allow-readonly-action` para que siga visible en solo lectura) que descarga un Excel multi-hoja con todos los datos de la clínica.

### Backend
- `app/Exports/XlsxWriter.php` — Generador XLSX ligero vía `ZipArchive` + XML nativo (sin dependencias Composer). Cada hoja escribe a `php://temp` y se vuelca al ZIP al final.
- `app/Exports/ClinicBackupExport.php` — 9 hojas con chunking de 500 registros:
  1. **Pacientes** — `Patient::where('clinic_id', ...)`
  2. **Pagos** — `Payment::where('clinic_id', ...)` con `patient:id,counter,first_name,last_name`
  3. **Facturas** — `Document::where('clinic_id', ...)` con `patient:id,counter,first_name,last_name`
  4. **Consentimientos** — `PatientConsent::where('clinic_id', ...)` con `patient:id,counter,first_name,last_name`
  5. **Bonos** — `Bonus::where('clinic_id', ...)` con `patient:id,counter,first_name,last_name`
  6. **Productos** — `Product::where('clinic_id', ...)`
  7. **Agenda pendiente** — `Appointment::whereIn('status', ['scheduled','rescheduled'])` con `appointmentType` y `professional`
  8. **Historias clínicas** — `Appointment::where('status', 'completed')->whereNotNull('notes')` (NO usa `ClinicalRecord`), una fila por cita
  9. **Adjuntos** — `PatientImage::where('clinic_id', ...)` con `patient:id,counter,first_name,last_name`
- `app/Http/Controllers/Api/SubscriptionBackupController.php` — `download()` genera XLSX en `sys_get_temp_dir()`, retorna `StreamedResponse` y elimina el temporal.
- `routes/api.php:116` — `GET /api/settings/subscription/backup` (protegida por `auth:sanctum` + `clinic` + `check.subscription`)
- `modules/Subscriptions/Routes/api.php` — Rutas del módulo (pricing, billing/webhook, stripe/checkout, subscribe*, settings/subscription, billing/checkout|confirm|cancel) cargadas vía `SubscriptionsServiceProvider::loadRoutesFrom()`. Requieren `Route::prefix('api')` porque `loadRoutesFrom()` no hereda el prefijo automático de `routes/api.php` (Laravel 12).

### Frontend
- Sección "Backup de datos" en Subscription.vue con botón "📥 Generar backup (.xlsx)" y estado `backupping`.
- El botón lleva la clase `allow-readonly-action` para no ser ocultado por `.readonly-mode` en el estado de solo lectura.
- Llamada con `api.get('/settings/subscription/backup', { responseType: 'blob' })` + `URL.createObjectURL` + descarga tipo Blob.

### Consideraciones
- **Sin dependencias Composer** — SSL bloqueado por Avast, se optó por XLSX manual con ZipArchive nativo de PHP.
- **Chunk 500** — evita memory leaks en clínicas con +10k registros.
- **`Patient.name` es accessor** (`first_name + last_name`), no columna DB. En eager loads usar `first_name,last_name`.
- **`Bonus.is_paid` no es columna real** — se deriva de `invoice_id != null` en el export.
- **Historia clínica** se extrae de `Appointment.notes` directamente, una fila por cita completada.
- Backup sincrónico; clínicas con +50k registros pueden demorar segundos.
