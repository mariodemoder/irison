# AI Agent Guide for dueleahi

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

## Billing Error Handling (Stripe)

- If Stripe is unreachable while creating checkout, backend returns `503` with `code=STRIPE_UNREACHABLE` from `app/Http/Controllers/BillingController.php`.
- Billing UI fallback for this case lives in `resources/js/views/BillingRequired.vue` and shows a local activation action only in dev.
- Local fallback uses `POST /api/subscribe/fake` (`app/Http/Controllers/Api/FakeSubscribeController.php`) to avoid leaving trial/canceled clinics blocked in read-only mode during local outages.

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
