# Billing Skill — Planes y Suscripciones

## Router rápido (anti-exploración)

- Si el incidente es de upgrade aprobado en backoffice pero no reflejado en cliente/pago:
  - Cargar `backoffice/upgrade-flow.md` antes de explorar código.

## Planes disponibles (`Clinic::PLAN_USER_LIMITS`)

Definidos en `app/Models/Clinic.php`:
| Plan | Máx usuarios | Stripe Price ID |
|------|-------------|-----------------|
| `basic` | 3 | `STRIPE_PRICE_ID` (env) |
| `pro` | 6 | `STRIPE_PRICE_ID` (env) |
| `enterprise` | 10 | `STRIPE_PRICE_ID` (env) |

Todos los planes usan el mismo `STRIPE_PRICE_ID` de entorno; la diferenciación es por `plan` y `max_users` en DB.

## Restricción de usuarios por plan

- `clinics.max_users` default: 3 (basic)
- Al cambiar plan via backoffice → `max_users` se actualiza automáticamente desde `Clinic::PLAN_USER_LIMITS`
- Al registrar clínica → se setea `plan=basic`, `max_users=3`
- Límite controlado en: `app/Services/Team/TeamUserService.php:87-90`
- Frontend muestra "X / Y usuarios" en `resources/js/views/team/Team.vue`

## Stripe Error Handling
- Unreachable Stripe → backend returns `503` with `code=STRIPE_UNREACHABLE` from `app/Http/Controllers/BillingController.php`
- UI fallback in `resources/js/views/BillingRequired.vue` (local activation in dev only)
- Local fallback: `POST /api/subscribe/fake` (`app/Http/Controllers/Api/FakeSubscribeController.php`)

## Stripe Customer Sync
Mandatory for Backoffice visibility. Persist on clinic:
- `clinics.stripe_id`
- `clinics.stripe_customer_id`

Write points (must keep synced):
- `app/Http/Controllers/BillingController.php` — `confirmCheckout`
- `app/Http/Controllers/Api/StripeWebhookController.php` — `checkout.session.completed`

## `clinic.subscribed_at` — Must Set on Activation
Set to now on:
- `POST /api/subscribe` (`SubscribeController.php`)
- `POST /api/subscribe/fake` (`FakeSubscribeController.php`)
- `POST /api/billing/confirm` (`BillingController.php`)
- Stripe webhook `checkout.session.completed` (`StripeWebhookController.php`)

Webhook hardening: support fallback by `metadata.clinic_id` and by Stripe customer ID (not just `customer_email`).

## Pre-Payment Data Gate (Clinic Tab)
Before activating paid plan, require:
- Valid Spanish tax ID (DNI/NIE/CIF)
- Non-empty clinic address

Frontend guard: `resources/js/views/Configuration.vue` (disabled buttons + toast + redirect to Clinic tab).

## BillingRequired Copy — State-Aware
- Trial expired (`blocked` / `trial_read_only`): show urgency copy
- Trial active: positive onboarding copy + days left if available

## Backup Export (.xlsx) — Configuración → Suscripción

Button en `resources/js/views/settings/Subscription.vue:82-86` que descarga un Excel multi-hoja con todos los datos de la clínica.

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
- `routes/api.php:173` — `GET /api/settings/subscription/backup` (protegida por `auth:sanctum` + `clinic` + `check.subscription`)

### Frontend
- Sección "Backup de datos" en Subscription.vue con botón "📥 Generar backup (.xlsx)" y estado `backupping`.
- Llamada con `api.get('/settings/subscription/backup', { responseType: 'blob' })` + `URL.createObjectURL` + descarga tipo Blob.

### Consideraciones
- **Sin dependencias Composer** — SSL bloqueado por Avast, se optó por XLSX manual con ZipArchive nativo de PHP.
- **Chunk 500** — evita memory leaks en clínicas con +10k registros.
- **`Patient.name` es accessor** (`first_name + last_name`), no columna DB. En eager loads usar `first_name,last_name`.
- **`Bonus.is_paid` no es columna real** — se deriva de `invoice_id != null` en el export.
- **Historia clínica** se extrae de `Appointment.notes` directamente, una fila por cita completada.
- Backup sincrónico; clínicas con +50k registros pueden demorar segundos.
