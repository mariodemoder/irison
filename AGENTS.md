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

- Backend: Laravel 11 API (`app/`, `routes/api.php`)
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

## Known Pitfalls

- Local dev expects PostgreSQL by default from `.env.example`.
- On Windows/WAMP, ensure `pdo_pgsql` and `pgsql` are enabled in PHP CLI and Apache.
- SQLite in-memory tests are configured in `phpunit.xml`, but some legacy migrations may include MySQL-specific SQL.
- Queue defaults to `database`; in production use process supervision and restart queues on deploy.
