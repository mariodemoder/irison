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

### 4. Docs-after-approval rule
- Every time the user approves a plan, updating the documentation in `docs/` is part of the plan.
- The plan must include a "docs" task that reflects the executed changes (routes, flows, architecture, module/class names), and it is completed in the same workflow, not deferred.
- The plan agent validates that the docs task is done before marking the plan complete.

### 5. Delegation policy (two-agent model)
- **plan** orquesta todo trabajo no trivial: entiende la tarea, la descompone, presenta el plan y supervisa la ejecución.
- **build** ejecuta los planes aprobados end-to-end (backend + frontend) cargando la skill que el dominio requiera.
- **No hay subagentes especialistas.** Ambos agentes comparten el catálogo completo de skills (skills opencode auto-descubiertos en `.opencode/skills/` + nativos vendored en `.agents/skills/`) y cargan la skill por necesidad según el dominio.
- Small or simple tasks → plan agent handles directly.

## Agent Architecture

This project is organized into 5 layers:

1. Governance Layer

- AGENTS.md defines the global rules, the required context, risk limits, and the delegation policy.

2. Orchestration Layer

- The planning agent is the single entry point for complex tasks.

- It plans, prioritizes, delegates, and validates progress.

3. Execution Layer

- **build** executes the approved plans end-to-end (backend + frontend).

- There are **no specialist subagents**. Both `plan` and `build` share the full skill catalog (opencode skills auto-discovered in `.opencode/skills/` + native vendor SKILL.md in `.agents/skills/`) and load the skill the domain requires.

4. Knowledge Layer

- The skills in `.opencode/skills/` are opencode skills (frontmatter `name`/`description`) auto-discovered by the `skill` tool; they provide technical context and domain knowledge.

- Skills should include implementation guidelines, not permissions or routing logic.

5. Cleaning and Verification Layer (on user demand)

- Dead-code cleanup (`con clean`) and QA passes (`con tests`) are executed **only on demand**, unless the approved plan/spec explicitly defines a **"complete flow"** that includes them. Otherwise, no cleanup, tests, or verification are executed.

## Practical Routing Matrix

Use this decision map when a task starts:

- If the task is a simple bug or small UI tweak → plan agent handles it directly.
- If the task touches backend logic, services, controllers, or database behavior → load the `backend` skill.
- If the task changes Vue components, routes, forms, or user flows → load the `frontend` skill (+ `vue-best-practices`, `frontend-a11y`).
- If the task touches Stripe, subscriptions, invoices, payments, or billing webhooks → load the `billing` skill.
- If the task affects tenant lifecycle, backoffice operations, invoices, or internal management flows → load the `backoffice` skill.
- If the task is a regression, risk-hardening, or validation pass → load the `qa` skill (`con tests`).
- If the task affects deployment, queues, environment, release safety, or production readiness → load the `deployment` skill.
- If the task touches Portal del Paciente, patient auth/reset, or `clinics.slug` → load the `patient-portal` skill.
- **If dead-code cleanup was requested** (`con clean`) or a "complete flow" plan includes it → `build` runs the cleanup following the Clean rule below.

Both `plan` and `build` execute these paths: `plan` plans and supervises, `build` implements loading the corresponding skill.

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
6. Every approved plan must update `docs/` in the same workflow (see Operating Model → Docs-after-approval rule).

## Skill Index (load on demand, saves tokens)

Skills opencode reales auto-descubiertos en `.opencode/skills/` (frontmatter `name`/`description`); se cargan con la herramienta `skill`. Router humano detallado: `.opencode/skills/index.md`.
- `core` — Setup, architecture, conventions, auth recovery
- `backend` — Logging, soft deletes, DB pitfalls
- `frontend` — Error handling, buttons, popups, date picker protocol
- `auth` — Roles, policies, registration, financial data stripping
- `billing` — Stripe, subscriptions, webhooks, backoffice sync
- `appointments` — Form, availability, pitfalls
- `bonus` — Multi-type sessions, BonusService, consumption flow, session lines
- `booking` — Online booking engine, models, routes
- `consent` — Plantillas, firma digital, envío remoto
- `company-services` — Sessions, bonuses, booking settings page
- `patient-portal` — Portal del Paciente, patient auth/reset, `clinics.slug`
- `activity` — Registro de actividad, cap de logins, logins ocultos al SPA
- `team` — User management, profiles, professions, schedules, booking link
- `qa` — Testing strategies, delegation (`con tests`)
- `deployment` — Production checklist, queues
- `backoffice` — Tenant management, invoices, upgrade flow, hard-delete

## Clean Delegation Rule

Dead-code cleanup se ejecuta **únicamente bajo demanda**: cuando el usuario lo solicite (`con clean`) o cuando el plan/spec aprobado defina explícitamente un **"complete flow"** que lo incluya. Nunca se ejecuta de forma automática. `build` elimina imports sin uso, variables muertas, ramas inalcanzables, depuración residual y archivos huérfanos, y **nunca borra tests**.

## QA Delegation Rule

QA, tests, regresión o validación se ejecutan **solo bajo demanda** (`con tests`) o si el plan/spec aprobado define un **"complete flow"** que lo incluya. Sin petición explícita, no se ejecutan tests ni verificación. La ejecución la hace `build` cargando la skill `qa`.

## Known Pitfalls

- Local dev defaults to PostgreSQL; on WAMP ensure `pdo_pgsql` + `pgsql` in PHP CLI and Apache.
- Some legacy migrations include MySQL-specific SQL (tests use SQLite in-memory).
- Queue defaults to `database`; use process supervision and restart queues on deploy.
- **Subscription upgrade proration**: Trial→paid upgrades must charge the FULL price of the new plan (no credit). Basic-paid→PRO uses Stripe's native proration. See `backoffice/upgrade-flow.md` for details.
- **Price ID resolution**: Each plan has its own Stripe product. `resolvePriceIdForPlan()` only falls back to `STRIPE_PRICE_ID` for the `basic` plan. Trial→paid checkout passes `price_id` explicitly to avoid incorrect fallback.
- **Modo solo lectura post-trial**: tras el fin del trial (o del periodo pagado de una cancelación) la clínica solo puede ver datos, activar la cuenta de pago y descargar el backup XLSX. Toda escritura —incluidas las rutas admin de Booking (`check.subscription` en `modules/Booking/Routes/api.php`) y la reserva online pública (`PublicBookingService::ensureClinicCanBeBooked()`)— se bloquea. Enforcement: middleware `check.subscription`, guarda axios (`api.js`), CSS `.readonly-mode` + `allow-readonly-action`. Detalle: `docs/backend/read-only-policy.md`.
- **Dos slugs independientes por clínica**: `clinics.slug` (Portal del Paciente, editable desde Servicios → Portal del Paciente) vs `booking_pages.slug` (Reserva Online). No sincronizar. `clinics.slug` alimenta branding público, enlace copiable y `?clinic=` de emails de reset. Detalle: `docs/backend/patient-portal.md` §13.
- **Reset de password del Portal del Paciente es por clinic-patient (no por email)**: token vive en `patient_password_reset_tokens.email = patientId` (broker `patients`); `forgot`/`reset`/`login` escopan por `clinics.slug`. Sin esto, un email compartido cambiaba el password del paciente de menor id. Detalle: `docs/backend/patient-portal.md` §2, §3, §12.
- **Clínicas sin `clinics.slug` (NULL) quedan fuera de todo el circuito del portal** (login, forgot, reset y rutas autenticadas). No hay backfill automático; se activa asignando el slug desde Servicios → Portal del Paciente. Gate: `Patient::canUsePortal()`; enforcement en `PatientAuthService` + middleware `patient.auth`. Detalle: `docs/backend/patient-portal.md` §13.
- Detalle de los pitfalls del Portal del Paciente (slugs, reset, NULL slug) consolidado en la skill `patient-portal`.

## Notifications Module

Organized as a modular bounded context under `modules/Notifications/` with two subdomains:

- **Patient/** — Appointment status notifications (created/updated/cancelled via `SendAppointmentStatusNotification` listener), consent email (`SendConsentEmail` listener via `Mail::to()`).
- **Backoffice/** — Subscription/billing notifications (`SubscriptionUpgradeRequestedNotification`, `BackofficeAlertNotification`, `SubscriptionRejectedNotification`, `SubscriptionUpgradedNotification`, `PaymentCompletedNotification`, `CheckoutLinkGeneratedNotification`) with corresponding listeners.

Events: `App\Events\AppointmentCreated`, `AppointmentUpdated`, `AppointmentCancelled` dispatched from `AppointmentService` and `PublicBookingService`.

Key model requirements: `Patient` uses `Notifiable` trait; `Clinic` has `getAdmins()` method.

> Recordatorios de citas: los jobs `SendAppointmentReminder24hJob` y `SendAppointmentReminder2hJob` se ejecutan con una **frecuencia variable** definida por `REMINDER_INTERVAL_MINUTES` (default 15 min, `config/reminders.php`). Detalle: `docs/backend/reminder-scheduling.md`.

> **Catálogo completo de notificaciones** (22 emails, branding por audiencia, tests y formato): `docs/backend/notifications.md`. Tests en `tests/Feature/Mail/EmailDispatchTest.php` y `tests/Feature/Notifications/NotificationsTest.php`.

## High-Value References

- `bootstrap/app.php` — Middleware aliases, scheduler
- `app/helpers.php` — `currentClinicId()`
- `routes/api.php` — API routes + middleware groups
- `routes/console.php` — Reminder commands
- `app/Models/Concerns/BelongsToClinic.php` — Tenant trait
- `app/Models/Scopes/ClinicScope.php` — Tenant scope
- `app/Traits/MultiTenantAuthorization.php` — Tenant auth
- `docs/backend/auth-flow.md` — Auth completo: registro, activación, login, password reset
- `docs/backend/finance-restructure-spec.md` — Spec completo de reestructuración del módulo Finanzas PRO (6 fases, roadmap)
