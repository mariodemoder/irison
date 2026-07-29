# AI Agent Guide for Irison

Minimum project context to be productive quickly.

## ⚠️ Mandatory — Read this file first

All agents MUST read this file at the start of every session before planning or executing any work.

## Quick Start

1. `composer install && npm install`
2. `cp .env.example .env && php artisan key:generate`
3. `php artisan migrate`
4. Dev: `composer run dev` / Windows: `py scripts/restart_dev.py`
5. Build: `npm run build` / Tests: `php artisan test`

## Architecture Snapshot

- Backend: Laravel 12 API (`app/`, `routes/api.php`)
- Frontend: Vue 3 SPA + Vite (`resources/js/`)
- Auth: Sanctum tokens / Billing: Stripe + Cashier / Tenancy: app-level by `clinic_id`

## Non-Negotiable Conventions

1. Keep tenant isolation intact — never bypass clinic filtering in models, policies, or middleware.
2. Respect layering: Controllers orchestrate HTTP, business rules in Services, authorization via Policies/Gates.
3. Keep public webhooks public — Stripe/billing webhooks stay accessible without auth.
4. Preserve reminder scheduling — verify jobs, schedule, and artisan commands together when touching reminders.

## Skill Index (load on demand, saves tokens)

See `.opencode/skills/index.md` for detailed routing. Key skills:
- `core` — Setup, architecture, conventions, auth recovery
- `backend` — Logging, soft deletes, DB pitfalls
- `frontend` — Error handling, buttons, popups
- `billing` — Stripe, subscriptions, webhooks, backoffice sync
- `appointments` — Form, availability, pitfalls
- `bonus` — Multi-type sessions, BonusService, consumption flow, session lines
- `booking` — Online booking engine, models, routes
- `company-services` — Sessions, bonuses, booking settings page
- `qa` — Testing strategies, delegation
- `deployment` — Production checklist, queues
- `backoffice` — Tenant management, invoices
- `consent` — Consentimientos informados, plantillas, firma digital, envío remoto

## QA Delegation Rule

For focused tests, regression, HTTP validation, or risk hardening → delegate to `.github/agents/irison-qa.agent.md`.

## Known Pitfalls

- Local dev defaults to PostgreSQL; on WAMP ensure `pdo_pgsql` + `pgsql` in PHP CLI and Apache.
- Some legacy migrations include MySQL-specific SQL (tests use SQLite in-memory).
- Queue defaults to `database`; use process supervision and restart queues on deploy.
- **Subscription upgrade proration**: Trial→paid upgrades must charge the FULL price of the new plan (no credit). Basic-paid→PRO uses Stripe's native proration. See `backoffice/upgrade-flow.md` for details.
- **Price ID resolution**: Each plan has its own Stripe product. `resolvePriceIdForPlan()` only falls back to `STRIPE_PRICE_ID` for the `basic` plan. Trial→paid checkout passes `price_id` explicitly to avoid incorrect fallback.

## High-Value References

- `bootstrap/app.php` — Middleware aliases, scheduler
- `app/helpers.php` — `currentClinicId()`
- `routes/api.php` — API routes + middleware groups
- `routes/console.php` — Reminder commands
- `app/Models/Concerns/BelongsToClinic.php` — Tenant trait
- `app/Models/Scopes/ClinicScope.php` — Tenant scope
- `app/Traits/MultiTenantAuthorization.php` — Tenant auth
- `docs/backend/auth-flow.md` — Auth completo: registro, activación, login, password reset
