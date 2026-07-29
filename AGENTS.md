# AI Agent Guide for Irison

Minimum project context to be productive quickly.

## ⚠️ Mandatory — Read this file first

All agents MUST read this file at the start of every session before planning or executing any work.

## Operating Model

### 1. Orchestrator rule
- The orchestrator for all non-trivial work is always the plan agent.
- The plan agent is responsible for understanding the task, decomposing it, choosing the right specialist, and supervising execution.
- The human assistant is a manual fallback layer. The user decides when to use it.

### 2. When manual fallback is allowed
- When the plan agent is blocked, uncertain, or unable to continue safely.
- When the task touches a high-risk flow and the user wants human supervision.
- When the user explicitly overrides the orchestration flow.

### 3. When in doubt, ask
- If the plan agent is unsure about the approach, risk level, or delegation target → ask the user before proceeding.
- Never make irreversible changes (billing, tenant data, destructive DB ops) without user confirmation.

### 4. Delegation policy
- Backend changes → **Backend** agent.
- Frontend/UI changes → **Frontend** agent.
- Billing/Stripe/subscription flows → **Billing** agent.
- QA, regression, or validation work → **QA** agent.
- Deploy/release risk → **Deploy** agent.
- Backoffice or tenant lifecycle work → **Backoffice** agent.
- Small or simple tasks → plan agent handles directly.

## Agent Architecture

This project is organized in 5 layers:

1. Governance layer
   - AGENTS.md defines the global rules, mandatory context, risk boundaries, and delegation policy.

2. Orchestration layer
   - The plan agent is the single entry point for complex tasks.
   - It plans, prioritizes, delegates, and validates progress.

3. Specialist layer
   - Domain specialists handle focused implementation work.
   - Each specialist owns one slice of the product: backend, frontend, billing, backoffice, QA, or deploy.
   - Dedicated agents for **Opencode** (`.opencode/agents/`): `@backend`, `@frontend`, `@qa`, `@billing`, `@backoffice`, `@deploy`.
   - Dedicated agents for **Copilot / VS Code** (`.github/agents/`): **Backend**, **Frontend**, **QA**, **Billing**, **Backoffice**, **Deploy**.
   - Both share the same skill files in `.opencode/skills/agents/`.

4. Knowledge layer
   - Skills in `.opencode/skills/index.md` provide technical context and domain know-how.
   - Skills should contain implementation guidance, not permissions or routing logic.

5. Verification layer
   - Every meaningful change should be validated with tests, build checks, or regression review.
   - The QA specialist should be involved for regressions and critical flows.

## Practical Routing Matrix

Use this decision map when a task starts:

- If the task is a simple bug or small UI tweak → plan agent handles it directly.
- If the task touches backend logic, services, controllers, or database behavior → delegate to the backend specialist.
- If the task changes Vue components, routes, forms, or user flows → delegate to the frontend specialist.
- If the task touches Stripe, subscriptions, invoices, payments, or billing webhooks → delegate to the billing specialist.
- If the task affects tenant lifecycle, backoffice operations, invoices, or internal management flows → delegate to the backoffice specialist.
- If the task is a regression, risk-hardening, or validation pass → delegate to the QA specialist.
- If the task affects deployment, queues, environment, release safety, or production readiness → delegate to the deploy specialist.

### Delegation target by platform

| Platform | Invocation |
|---|---|
| **Opencode** (plan agent) | Use `@agent_name` or Task tool → `.opencode/agents/{name}.md` |
| **GitHub Copilot** (VS Code) | Use the `.agent.md` files → `.github/agents/{name}.agent.md` |

## Domain-Driven Design Guidance for New Features

For new functionality, prefer a bounded-context structure inside `modules/<bounded-context>`.

### Lightweight DDD for small modules
For small or focused features such as exports, read-model projections, or data-output modules, a simplified structure can be enough:
- `Application` — use cases, small orchestration services, DTOs, queries
- `Domain` — business rules and simple domain objects
- `Infrastructure` — adapters for persistence, external services, and output generation

In this lightweight version, Infrastructure may include subfolders such as `Controllers` and `Persistence` when that separation helps keep the module clean.

### Full DDD for more complex features
If the feature is substantial or has multiple flows, follow a richer structure similar to existing modules such as `modules/Booking` or `modules/Bonus`, with clear separation between:
- domain logic,
- application orchestration,
- infra adapters,
- API/controllers,
- UI-facing integration points.

### Design principles
- Keep business rules inside the Domain layer.
- Keep controllers and HTTP concerns thin.
- Keep persistence and external integrations inside Infrastructure.
- Prefer bounded contexts over cross-cutting logic scattered across the app.
- Use the lightweight DDD shape only when the module is genuinely small and focused.
- When a feature grows, split it into smaller modules rather than forcing everything into the global app layer.

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
5. Never make irreversible billing or tenant changes without validation and review.

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

For focused tests, regression, HTTP validation, or risk hardening → delegate to `.github/agents/qa.agent.md`.

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
