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

### 5. Delegation policy
- Backend changes → **Backend** agent.
- Frontend/UI changes → **Frontend** agent.
- Billing/Stripe/subscription/upgrade flows → **Billing** agent.
- Dead-code cleanup after any code generation → **Clean** agent (always before QA).
- QA, regression, or validation work → **QA** agent.
- Deploy/release risk → **Deploy** agent.
- Backoffice or tenant lifecycle work → **Backoffice** agent.
- Small or simple tasks → plan agent handles directly.

## Agent Architecture

This project is organized into 5 layers:

1. Governance Layer

- AGENTS.md defines the global rules, the required context, risk limits, and the delegation policy.

2. Orchestration Layer

- The planning agent is the single entry point for complex tasks.

- It plans, prioritizes, delegates, and validates progress.

3. Specialist Layer

- Domain specialists handle specific implementation work.

- Each specialist is responsible for a part of the product: backend, frontend, billing, back office, quality assurance, cleanup, or deployment.

- Dedicated agents for **Opencode** (`.opencode/agents/`): `@backend`, `@frontend`, `@qa`, `@billing`, `@backoffice`, `@deploy`, `@clean`.

- Skills files in `.opencode/skills/agents/`.

4. Knowledge Layer

- The skills in `.opencode/skills/index.md` provide technical context and domain knowledge.

- Skills should include implementation guidelines, not permissions or routing logic.

5. Cleaning and Verification Layer (on user demand)

- All significant changes must be validated with tests, build checks, or regression analysis.

- Dead code cleanup (Cleanup Agent) is run before QA and on user demand with the request "con clean"; otherwise, no cleanup is executed.

The QA specialist must participate on demand with "con tests"; otherwise, no tests are executed.

## Practical Routing Matrix

Use this decision map when a task starts:

- If the task is a simple bug or small UI tweak → plan agent handles it directly.
- If the task touches backend logic, services, controllers, or database behavior → delegate to the backend specialist.
- If the task changes Vue components, routes, forms, or user flows → delegate to the frontend specialist.
- If the task touches Stripe, subscriptions, invoices, payments, or billing webhooks → delegate to the billing specialist.
- If the task affects tenant lifecycle, backoffice operations, invoices, or internal management flows → delegate to the backoffice specialist.
- **If code was generated or modified → delegate to the Clean agent (dead-code cleanup) before QA.**
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
6. Every approved plan must update `docs/` in the same workflow (see Operating Model → Docs-after-approval rule).

## Skill Index (load on demand, saves tokens)

See `.opencode/skills/index.md` for detailed routing. Key skills:
- `core` — Setup, architecture, conventions, auth recovery
- `backend` — Logging, soft deletes, DB pitfalls
- `frontend` — Error handling, buttons, popups, date picker protocol
- `billing` — Stripe, subscriptions, webhooks, backoffice sync
- `appointments` — Form, availability, pitfalls
- `bonus` — Multi-type sessions, BonusService, consumption flow, session lines
- `booking` — Online booking engine, models, routes
- `company-services` — Sessions, bonuses, booking settings page
- `qa` — Testing strategies, delegation
- `clean` — Dead-code cleanup tras toda generación de código, antes de QA
- `deployment` — Production checklist, queues
- `backoffice` — Tenant management, invoices
- `consent` — Consentimientos informados, plantillas, firma digital, envío remoto
- `activity` — Registro de actividad, cap de logins (3 por usuario/clínica), logins ocultos al SPA

## Clean Delegation Rule (mandatory)

Every time code is generated, modified, or generated by an agent during a plan, the plan agent delegates a dead-code cleanup pass to the Clean agent (`.opencode/agents/clean.md` / `.github/agents/clean.agent.md`) **before** QA validates. The Clean agent removes imports sin uso, variables muertas, ramas inalcanzables, depuración residual y archivos huérfanos, and never removes tests. QA only runs after the cleanup pass reports zero dead code.

## QA Delegation Rule

For focused tests, regression, HTTP validation, or risk hardening → delegate to `.github/agents/qa.agent.md`.

## Known Pitfalls

- Local dev defaults to PostgreSQL; on WAMP ensure `pdo_pgsql` + `pgsql` in PHP CLI and Apache.
- Some legacy migrations include MySQL-specific SQL (tests use SQLite in-memory).
- Queue defaults to `database`; use process supervision and restart queues on deploy.
- **Subscription upgrade proration**: Trial→paid upgrades must charge the FULL price of the new plan (no credit). Basic-paid→PRO uses Stripe's native proration. See `backoffice/upgrade-flow.md` for details.
- **Price ID resolution**: Each plan has its own Stripe product. `resolvePriceIdForPlan()` only falls back to `STRIPE_PRICE_ID` for the `basic` plan. Trial→paid checkout passes `price_id` explicitly to avoid incorrect fallback.
- **Modo solo lectura post-trial**: tras el fin del trial (o del periodo pagado de una cancelación) la clínica solo puede ver datos, activar la cuenta de pago y descargar el backup XLSX. Toda escritura —incluidas las rutas admin de Booking (`check.subscription` en `modules/Booking/Routes/api.php`) y la reserva online pública (`PublicBookingService::ensureClinicCanBeBooked()`)— se bloquea. Enforcement: middleware `check.subscription`, guarda axios (`api.js`), CSS `.readonly-mode` + `allow-readonly-action`. Detalle: `docs/backend/read-only-policy.md`.

## Notifications Module

Organized as a modular bounded context under `modules/Notifications/` with two subdomains:

- **Patient/** — Appointment status notifications (created/updated/cancelled via `SendAppointmentStatusNotification` listener), consent email (`SendConsentEmail` listener via `Mail::to()`).
- **Backoffice/** — Subscription/billing notifications (`SubscriptionUpgradeRequestedNotification`, `BackofficeAlertNotification`, `SubscriptionRejectedNotification`, `SubscriptionUpgradedNotification`, `PaymentCompletedNotification`, `CheckoutLinkGeneratedNotification`) with corresponding listeners.

Events: `App\Events\AppointmentCreated`, `AppointmentUpdated`, `AppointmentCancelled` dispatched from `AppointmentService` and `PublicBookingService`.

Key model requirements: `Patient` uses `Notifiable` trait; `Clinic` has `getAdmins()` method.

### Catálogo completo de notificaciones

#### A Pacientes (email)

| # | Nombre | Motivo | Contenido | From | To |
|---|---|---|---|---|---|
| 1 | **BookingConfirmation** | Reserva online | Confirmación con fecha, hora, profesional, clínica + enlace cancelación | Clínica | Email paciente |
| 2 | **AppointmentCreatedNotification** | Cita creada manualmente | Aviso de nueva cita con fecha y hora | Clínica | Email paciente |
| 3 | **AppointmentUpdatedNotification** | Cita modificada | Cambios realizados + nueva fecha/hora | Clínica | Email paciente |
| 4 | **AppointmentCancelledNotification** | Cita cancelada | Cancelación con fecha original | Clínica | Email paciente |
| 5 | **AppointmentReminderNotification** | Job 2h/24h o reenvío manual | Recordatorio: fecha, hora, dirección, teléfono | Clínica | Email paciente |
| 6 | **ConsentSignRequestMail** | Clínica envía consentimiento | Enlace para firmar (expira 72h) | Clínica | Email paciente |
| 7 | **ResetPasswordNotificationEs** | Solicitud restablecer contraseña | Enlace restablecer (expira N min) | Irison | Email usuario |

#### A Clínica/Propietarios (email)

| # | Nombre | Motivo | Contenido | From | To |
|---|---|---|---|---|---|
| 8 | **NewOnlineBooking** | Nueva reserva online | Datos paciente (nombre, email, teléfono), fecha, hora, notas | Irison | Owners clínica |
| 9 | **SubscriptionActivatedMail** | Nueva suscripción activada | Bienvenida, plan, fecha activación, enlace factura | Irison | Owner/admin |
| 10 | **CheckoutLinkGeneratedNotification** | Enlace de pago generado para upgrade | Enlace Stripe para completar pago | Irison | Owner/admin |
| 11 | **PaymentCompletedNotification** | Pago de upgrade completado | Confirmación de pago, plan actualizado | Irison | Owner/admin |
| 12 | **SubscriptionUpgradedNotification** | Upgrade completado | Plan actualizado, bienvenida | Irison | Owner/admin |
| 13 | **SubscriptionUpgradedNotificationMail** | (Fallback) Confirmación upgrade | Mismo contenido que #12, enviado directo desde controlador | Irison | Owner/admin |
| 14 | **InvoicePaymentFailedMail** | Pago factura falló (webhook) | Aviso pago pendiente, monto, próximo intento | Irison | Email clínica + owner |
| 15 | **ResendInvoiceMail** | Admin reenvía factura | Enlace factura + mensaje personalizado | Irison | Email destinatario |
| 21 | **SubscriptionRejectedNotification** | Upgrade rechazado | Rechazo con comentarios del admin, plan solicitado | Irison | Email owner |

#### Internas / Backoffice (email)

| # | Nombre | Motivo | Contenido | From | To |
|---|---|---|---|---|---|
| 16 | **SubscriptionCanceledInternalMail** | Suscripción cancelada | Datos clínica + IDs Stripe | Irison | `cancellation_notification_to` |
| 17 | **ContactMail** | Formulario de contacto | Nombre, email, asunto, mensaje | Usuario | `CONTACT_EMAIL` |
| 18 | **AccountActivationMail** | Nuevo registro | Enlace activar cuenta + trial | Irison | Email registrado |
| 19 | **TrialLifecycleMail** | Hitos trial (día 1,7,20,27,30) | Mensajes según milestone, enlace facturación | Irison | Email owner |

#### Solo Database (bandeja interna)

| # | Nombre | Motivo | Contenido | From | To |
|---|---|---|---|---|---|
| 20 | **SubscriptionUpgradeRequestedNotification** | Solicitud upgrade de plan | Plan solicitado, clínica, solicitante | Sistema | Admins clínica |
| 22 | **BackofficeAlertNotification** | Alertas internas de suscripción (`backoffice_upgrade_requested`, `trial_expired`, `trial_converted`, `subscription_cancelled`) | `type`, clínica, mensaje + extras según tipo | Sistema | Admins backoffice activos |

> Nota: `BackofficeAlertNotification` se reconcilia en cada carga del índice de clínicas (`ClinicController@index` → `BackofficeAlertService::reconcileMany`). Es idempotente (dedupe `type|clinic_id|admin_id`) y deriva alertas del estado actual, sin backfill retroactivo.

Tests en `tests/Feature/Mail/EmailDispatchTest.php` y `tests/Feature/Notifications/NotificationsTest.php`.

Detalle de comportamiento: `docs/backoffice/subscriptions.md` (ciclo de suscripción) y `docs/backoffice/notificaciones-internas.md` (alertas internas de backoffice).

## High-Value References

- `bootstrap/app.php` — Middleware aliases, scheduler
- `app/helpers.php` — `currentClinicId()`
- `routes/api.php` — API routes + middleware groups
- `routes/console.php` — Reminder commands
- `app/Models/Concerns/BelongsToClinic.php` — Tenant trait
- `app/Models/Scopes/ClinicScope.php` — Tenant scope
- `app/Traits/MultiTenantAuthorization.php` — Tenant auth
- `docs/backend/auth-flow.md` — Auth completo: registro, activación, login, password reset
