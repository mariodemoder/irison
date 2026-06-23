# Core Project Context

## Quick Start
1. Install deps: `composer install` + `npm install`
2. Env + key: `cp .env.example .env` + `php artisan key:generate`
3. Migrate: `php artisan migrate`
4. Dev: `composer run dev` (server + queue + logs + vite)
5. Windows: `py scripts/restart_dev.py`
6. Build: `npm run build`
7. Tests: `php artisan test`

## Architecture Snapshot
- Backend: Laravel 12 API (`app/`, `routes/api.php`)
- Frontend: Vue 3 SPA + Vite (`resources/js/`)
- Auth: Sanctum tokens
- Billing: Stripe + Cashier
- Tenancy: app-level by `clinic_id`

## Non-Negotiable Conventions
1. Keep tenant isolation intact — never bypass clinic filtering in models, policies, or middleware.
2. Respect layering: Controllers orchestrate HTTP, business rules in Services, authorization via Policies/Gates.
3. Keep public webhooks public — Stripe/billing webhooks stay accessible without auth.
4. Preserve reminder scheduling — verify jobs, schedule, and artisan commands together when touching reminders.

## High-Value References
- `README.md` — Project overview
- `docs/deployment/linux-cloud.md` — Linux/cloud deploy
- `routes/api.php` — API routes and middleware groups
- `bootstrap/app.php` — App boot, middleware aliases, scheduler
- `app/Models/Concerns/BelongsToClinic.php` — Tenant model trait
- `app/Models/Scopes/ClinicScope.php` — Tenant query scope
- `app/Traits/MultiTenantAuthorization.php` — Tenant auth trait
- `app/helpers.php` — Shared clinic helpers (`currentClinicId()`)
- `routes/console.php` — Console reminder commands
- `composer.json` — Composer scripts
- `package.json` — NPM scripts
- `phpunit.xml` — Test env defaults

## Auth Recovery Shortcut
- `POST /api/password/forgot` with `{ email }`
- `POST /api/password/reset` with `{ token, email, password, password_confirmation }`
- Max 4 recovery emails per account; 5th returns `code=PASSWORD_RESET_LIMIT_REACHED`
- Counter resets on successful login (`AuthController@login`)
- SPA views: `Login.vue`, `ForgotPassword.vue`, `ResetPassword.vue`
- Reset URL configured in `AppServiceProvider` via `ResetPassword::createUrlUsing(...)` with `/reset-password?token=...&email=...`
- Quick check: send 4 requests in Mailpit (`http://127.0.0.1:8025/`), 5th should show tech contact message.
