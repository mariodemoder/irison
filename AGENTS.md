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
- **No hay subagentes especialistas.** Ambos agentes comparten el catálogo completo de skills (`.opencode/skills/index.md` + nativas en `.agents/skills/`) y cargan la skill por necesidad según el dominio.
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

- There are **no specialist subagents**. Both `plan` and `build` share the full skill catalog (`.opencode/skills/index.md` + native SKILL.md in `.agents/skills/`) and load the skill the domain requires.

4. Knowledge Layer

- The skills in `.opencode/skills/index.md` provide technical context and domain knowledge.

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

See `.opencode/skills/index.md` for detailed routing. Key skills:
- `core` — Setup, architecture, conventions, auth recovery
- `backend` — Logging, soft deletes, DB pitfalls
- `frontend` — Error handling, buttons, popups, date picker protocol
- `billing` — Stripe, subscriptions, webhooks, backoffice sync
- `appointments` — Form, availability, pitfalls
- `bonus` — Multi-type sessions, BonusService, consumption flow, session lines
- `booking` — Online booking engine, models, routes
- `company-services` — Sessions, bonuses, booking settings page
- `qa` — Testing strategies, delegation (`con tests`)
- `clean` — Dead-code cleanup **solo on demand** (`con clean`); regla en la sección Clean Delegation Rule
- `deployment` — Production checklist, queues
- `backoffice` — Tenant management, invoices
- `consent` — Consentimientos informados, plantillas, firma digital, envío remoto
- `activity` — Registro de actividad, cap de logins (3 por usuario/clínica), logins ocultos al SPA

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

## Notifications Module

Organized as a modular bounded context under `modules/Notifications/` with two subdomains:

- **Patient/** — Appointment status notifications (created/updated/cancelled via `SendAppointmentStatusNotification` listener), consent email (`SendConsentEmail` listener via `Mail::to()`).
- **Backoffice/** — Subscription/billing notifications (`SubscriptionUpgradeRequestedNotification`, `BackofficeAlertNotification`, `SubscriptionRejectedNotification`, `SubscriptionUpgradedNotification`, `PaymentCompletedNotification`, `CheckoutLinkGeneratedNotification`) with corresponding listeners.

Events: `App\Events\AppointmentCreated`, `AppointmentUpdated`, `AppointmentCancelled` dispatched from `AppointmentService` and `PublicBookingService`.

Key model requirements: `Patient` uses `Notifiable` trait; `Clinic` has `getAdmins()` method.

> Recordatorios de citas: los jobs `SendAppointmentReminder24hJob` y `SendAppointmentReminder2hJob` se ejecutan con una **frecuencia variable** definida por `REMINDER_INTERVAL_MINUTES` (default 15 min, `config/reminders.php`). Detalle: `docs/backend/reminder-scheduling.md`.

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

> Formato de email: todos los emails de Irison hacia subscriptores usan el layout unificado `emails/layouts/irison.blade.php` (header con logo Irison + pie legal genérico). Detalle: `docs/qa/email-tests.md`.

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
- `docs/backend/finance-restructure-spec.md` — Spec completo de reestructuración del módulo Finanzas PRO (6 fases, roadmap)
