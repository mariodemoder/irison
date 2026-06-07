# AI Agent Guide for Irison

This file gives coding agents the minimum project context to be productive quickly.

## Quick Start

1. Install dependencies:
   - `composer install`
   - `npm install`
2. Prepare env and app key:
   - `cp .env.example .env`
   - `php artisan key:generate`
3. Run migrations:
   - `php artisan migrate`

Development options:
- Standard: `composer run dev` (server + queue + logs + vite)
- Windows helper: `py scripts/restart_dev.py`

Build and tests:
- Frontend build: `npm run build`
- Full tests: `php artisan test`

## Architecture Snapshot

- Backend: Laravel 12 API (`app/`, `routes/api.php`)
- Frontend: Vue 3 SPA + Vite (`resources/js/`)
- Auth: Sanctum tokens
- Billing: Stripe + Cashier
- Tenancy: app-level by `clinic_id`

## Non-Negotiable Conventions

1. Keep tenant isolation intact.
   - Do not remove or bypass clinic filtering in models, policies, or middleware.
2. Respect current layering.
   - Controllers orchestrate HTTP.
   - Business rules live in Services.
   - Authorization goes through Policies/Gates.
3. Keep public webhooks public.
   - Stripe and billing webhooks must stay accessible without auth.
4. Preserve reminder scheduling behavior.
   - If touching reminders, verify jobs, schedule, and artisan commands together.

## High-Value References

- Project overview: [README.md](README.md)
- Linux/cloud deployment: [docs/deployment/linux-cloud.md](docs/deployment/linux-cloud.md)
- API routes and middleware groups: [routes/api.php](routes/api.php)
- App boot, middleware aliases, scheduler: [bootstrap/app.php](bootstrap/app.php)
- Tenant model trait: [app/Models/Concerns/BelongsToClinic.php](app/Models/Concerns/BelongsToClinic.php)
- Tenant query scope: [app/Models/Scopes/ClinicScope.php](app/Models/Scopes/ClinicScope.php)
- Tenant authorization trait: [app/Traits/MultiTenantAuthorization.php](app/Traits/MultiTenantAuthorization.php)
- Shared clinic helpers: [app/helpers.php](app/helpers.php)
- Console reminder commands: [routes/console.php](routes/console.php)
- Composer scripts: [composer.json](composer.json)
- NPM scripts: [package.json](package.json)
- Test env defaults: [phpunit.xml](phpunit.xml)

## Existing Specialized Agents

- Billing specialist: [.github/agents/facturacion.agent.md](.github/agents/facturacion.agent.md)
- Backend specialist: [.github/agents/php-laravel-backend.agent.md](.github/agents/php-laravel-backend.agent.md)
- Frontend specialist: [.github/agents/MISTER FRONT.agent.md](.github/agents/MISTER%20FRONT.agent.md)
- QA specialist: [.github/agents/irison-qa.agent.md](.github/agents/irison-qa.agent.md)
- Deploy specialist: [.github/agents/Mr. DEPLOY.agent.md](.github/agents/Mr.%20DEPLOY.agent.md)
- Backoffice specialist: [.github/agents/Mr. BackOffice.agent.md](.github/agents/Mr.%20BackOffice.agent.md)

### QA Delegation Rule

- Si se requieren pruebas específicas (feature focalizadas, regresión de módulos concretos, validaciones de contratos HTTP o hardening de escenarios de riesgo), delegar la ejecución y criterio de cobertura al agente `IRISON QA` (`.github/agents/irison-qa.agent.md`).

## Known Pitfalls

- Local dev expects PostgreSQL by default from `.env.example`.
- On Windows/WAMP, ensure `pdo_pgsql` and `pgsql` are enabled in PHP CLI and Apache.
- SQLite in-memory tests are configured in `phpunit.xml`, but some legacy migrations may include MySQL-specific SQL.
- Queue defaults to `database`; in production use process supervision and restart queues on deploy.

## Frontend Error Handling

- Use `resources/js/services/api.js` for authenticated SPA requests. It centralizes 401 logout, 402 subscription-required redirects, 403 forbidden/read-only messaging, 422 validation payload handling, and fallback messages for 500 responses.
- Keep field-level validation in the form component. Use `resources/js/components/ErrorAlert.vue` for page-level/banner-level errors through global state in `resources/js/shared/globalHttpError.js`, rendered from `resources/js/App.vue`.
- Login and registration intentionally stay on plain `axios` so invalid credentials do not trigger the authenticated-session interceptor.
- Keep repeated load-error copy unified via `resources/js/shared/httpErrors.js` (for example `getLoadErrorMessage`) instead of duplicating per-view message logic.
- Keep toast plugin configuration centralized in `resources/js/services/toastConfig.js` and registered once in `resources/js/app.js`.
- Keep toast visual language centralized in `resources/css/app.css` using the Irison classes (`.irison-toast`, variant colors, progress bar, close button).
- Billing lifecycle coverage lives in `tests/Feature/BillingLifecycleTest.php`; keep trial, canceled, read-only, and reactivation paths aligned with `/api/me`.

## Auth Recovery Shortcut (Agent)

- Endpoints SPA públicas para recuperación de contraseña:
   - `POST /api/password/forgot` con `{ email }`.
   - `POST /api/password/reset` con `{ token, email, password, password_confirmation }`.
- Límite operativo: máximo 4 correos de recuperación por cuenta. Desde el intento 5 no se envía correo y la API devuelve `code=PASSWORD_RESET_LIMIT_REACHED` con el mensaje de contacto técnico.
- El contador se reinicia cuando el usuario inicia sesión correctamente (`AuthController@login`).
- Enlace desde login SPA: `resources/js/views/Login.vue` -> ruta `/forgot-password`.
- Vistas SPA del flujo:
   - `resources/js/views/ForgotPassword.vue`
   - `resources/js/views/ResetPassword.vue`
- URL del correo de reset está redirigida a frontend en `AppServiceProvider` usando `ResetPassword::createUrlUsing(...)` con `/reset-password?token=...&email=...`.
- Atajo de validación rápida para agentes:
  - Prueba manual local en Mailpit: abrir `http://127.0.0.1:8025/`, solicitar recuperación 4 veces con el mismo email y verificar que solo llegan 4 correos; en el intento 5 validar que no llega correo y se muestra el mensaje técnico en la UI.

## Billing Error Handling (Stripe)

- If Stripe is unreachable while creating checkout, backend returns `503` with `code=STRIPE_UNREACHABLE` from `app/Http/Controllers/BillingController.php`.
- Billing UI fallback for this case lives in `resources/js/views/BillingRequired.vue` and shows a local activation action only in dev.
- Local fallback uses `POST /api/subscribe/fake` (`app/Http/Controllers/Api/FakeSubscribeController.php`) to avoid leaving trial/canceled clinics blocked in read-only mode during local outages.

## Billing / Backoffice Shortcuts (Agent)

**Stripe customer sync is mandatory for visibility in Backoffice**
- Backoffice invoice listing depends on clinic Stripe customer IDs.
- Keep Stripe customer persisted on clinic in both fields:
   - `clinics.stripe_id`
   - `clinics.stripe_customer_id`
- Required write points:
   - `app/Http/Controllers/BillingController.php` (`confirmCheckout`)
   - `app/Http/Controllers/Api/StripeWebhookController.php` (`checkout.session.completed`)

**Backoffice invoices must use resilient customer resolution**
- In `app/Http/Controllers/Backoffice/ClinicController.php` (`loadStripeInvoices`), resolve customer IDs in this order:
   1. `clinic.stripe_id`
   2. `clinic.stripe_customer_id`
   3. latest `subscriptions.stripe_customer_id`
   4. fallback lookup in Stripe by clinic email
- Merge invoices across all resolved customer IDs and dedupe by Stripe invoice ID.

**`clinic.subscribed_at` must be set on subscription activation**
- Ensure activation paths set `clinic.subscribed_at` to now:
   - `POST /api/subscribe` (`app/Http/Controllers/Api/SubscribeController.php`)
   - `POST /api/subscribe/fake` (`app/Http/Controllers/Api/FakeSubscribeController.php`)
   - `POST /api/billing/confirm` (`app/Http/Controllers/BillingController.php`)
   - Stripe webhook `checkout.session.completed` (`app/Http/Controllers/Api/StripeWebhookController.php`)
- Webhook hardening: do not rely only on `customer_email`; support fallback by `metadata.clinic_id` and by Stripe customer ID.

**Pre-payment data gate (Clinic tab)**
- Before activating paid plan from configuration, require:
   - valid Spanish tax ID (DNI/NIE/CIF)
   - non-empty clinic address
- Frontend guard is in `resources/js/views/Configuration.vue` (buttons disabled + toast + redirect to Clinic tab).
- Keep backend checks aligned when adding new activation entry points.

**BillingRequired copy should be state-aware**
- In `resources/js/views/BillingRequired.vue`, show urgency copy only when trial is expired (`blocked` / `trial_read_only`).
- If trial is still active, show positive onboarding copy and include days left when available.

## Backend Business Logging

- Keep business logs structured with stable event names and context keys (`event`, `result`, `clinic_id`, `user_id`, resource IDs, `error_code`).
- Required diagnostic events: `auth.login.success|failed`, `subscription.failed`, `reminder.sent|failed`, `payment.created`.
- Use `info` for successful business events, `warning` for expected failures, and `error` for exceptions.
- Never log sensitive data: passwords, tokens, card data, Stripe secrets, or full credential payloads.
- If email context is required, log only safe derivatives (for example domain), never full email addresses.

## Soft Deletes (Core Entities)

- Use soft deletes for critical clinical records to prevent accidental data loss and preserve history.
- Core scope:
   - `patients`
   - `appointments`
   - `documents` (invoices/abonos)
- Visibility after deletion:
   - Daily operational views: do not show soft-deleted records.
   - Historical/audit flows: include soft-deleted records only through explicit `withTrashed` paths.
   - Billing/history consistency: keep invoice history readable even if linked patient/appointment was soft-deleted.
- Public API behavior:
   - `destroy` means soft delete.
   - `forceDelete` is not part of normal product flows.

## Frontend Button Styling

- Global button styles are part of frontend responsibilities and should be centralized in `resources/css/`.
- Reuse existing shared classes (`.btn`, `.btn--solid`, `.btn--ghost`) before introducing new button variants.
- Use `.muted` for "Volver" and secondary pills, and `.edit-btn` for all edit actions so both keep the same pill height across the app.
- Use `.quick-trigger` for the actions menu button that appears next to "Volver" so it keeps the same pill height and icon sizing everywhere.
- Avoid inline padding/font-size overrides on edit/back buttons; keep those dimensions in the shared CSS layer.

## Frontend Popup Form Styling

- Creation popups must always show visible labels for every field; placeholders are only complementary.
- Reuse shared popup form styles from `resources/css/app.css`: `.swal-popup-card`, `.swal-card`, `.create-row`, `.create-grid-2`.
- Do not duplicate popup styles inside view-scoped files; keep popup styling centralized.

## Appointment Work Skills

- Core appointment form UI is in `resources/js/views/appointments/Form.vue`.
- Shared appointment date/time helpers are in `resources/js/shared/appointmentHelpers.js`.
- Keep appointment time slot granularity consistent at 15 minutes across form options and calendar/list formatting.
- For appointment API orchestration, use `app/Http/Controllers/Api/AppointmentController.php` and `app/Services/Appointments/AppointmentService.php`.
- Respect tenant isolation (`clinic_id`) and avoid bypassing appointment policies/scopes.
- When changing availability/overlap behavior, run and update `tests/Feature/AppointmentAvailabilityTest.php`.

### Known Appointment Pitfalls & Shortcuts

**Date/time payload normalization (datetime-local vs date+H:i)**
- The Vue form sends `start_time`/`end_time` as `YYYY-MM-DDTHH:mm` (datetime-local input).
- `StoreAppointmentRequest` and `UpdateAppointmentRequest` use `prepareForValidation()` to split these into separate `date` (Y-m-d) and `start_time`/`end_time` (H:i) fields.
- `AppointmentService::create()` recombines them into full datetimes (`date + H:i → Y-m-d H:i:s`) before `Appointment::create()`. Without this step the model stores only `"09:00"`.
- The `date` field is `unset()` from `$data` after combining to avoid mass-assignment noise.

**ValidateDateAfterNow rule**
- Do NOT use `Carbon::parse($value)->isPast()` — it treats today at 00:00 as past.
- Correct check: `Carbon::parse($value)->startOfDay()->lt(Carbon::today())` — rejects only dates strictly before today.
- Today's appointments are valid; hour-level past validation belongs in `start_time`, not the date rule.

**StoreAppointmentRequest must declare all fields the service needs**
- The controller calls `$request->validated()`, so any field not listed in `rules()` is silently stripped.
- Required service fields: `price`, `payment_type`, `status`, `use_bonus_id`, `bonus_notes`, `apply_credit`, `apply_credit_mode`, `apply_credit_amount`, `use_credit_payment_id`, `use_credit_amount`.
- Missing `price` causes DomainException `"Debes indicar el precio de la sesión"` from `AppointmentService::validatePaymentRules()`.

**Overlap validation — one source of truth**
- `ValidateSlotAvailability` rule (FormRequest) and `CheckAvailability` service both detect overlaps.
- Keep overlap validation only in `AppointmentService` (via `CheckAvailability`). Removing it from the FormRequest avoids duplicate/conflicting 422 shapes (`errors.end_time` vs `error`).
- The test `test_reject_overlapping_appointment` asserts `assertJsonPath('error', '...')` (DomainException shape), not `errors.end_time`.

**Helper function name**
- Use `currentClinicId()` (from `app/helpers.php`), NOT `clinic()` — the latter does not exist and throws a fatal error.

**Availability service**
- `CheckAvailability::validate()` returns `['valid' => bool, 'errors' => []]`.
- `CheckAvailability::check()` returns `'disponible'` or `'ocupado'` (simpler, no patient check).
- Both use full Carbon datetimes; always pass combined `date+time` Carbon objects, never bare `H:i` strings.
