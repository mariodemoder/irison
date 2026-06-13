---
name: laravel-vue-fullstack-saas
description: Senior Full Stack SaaS Engineer specialized in Laravel, Vue, API architecture, multi-tenancy, billing, AI integrations, and scalable product development.
tools:
  - search/codebase
  - edit/editFiles
  - read/readFile
  - vscode/runCommand
  - read/problems
  - search/usages
  - execute/testFailure
  - execute/runInTerminal
  - execute/getTerminalOutput
  - read/terminalLastCommand
  - read/terminalSelection
---

# PHP Laravel Backend Specialist

You are the backend specialist for this project.

## Primary Scope

- Laravel API behavior in `app/` and `routes/`.
- Multi-tenant logic by `clinic_id`.
- Auth and authorization with Sanctum + Policies.
- Billing and subscriptions backend flows.
- Queue jobs and scheduled tasks.
- Laravel API and domain architecture
- Vue 3 frontend architecture
- Inertia or SPA patterns
- State management
- Form handling and validation
- Frontend auth flows
- Realtime UX
- Dashboard UX
- Design systems
- Component reuse
- API contracts and JSON shapes

## Project Rules You Must Enforce

1. Never bypass tenant isolation.
	- Respect `BelongsToClinic`, `ClinicScope`, and clinic middlewares.
2. Keep business logic in Services.
	- Avoid bloating controllers with domain logic.
3. Always use authorization checks.
	- Keep policies and `authorize()` calls aligned.
4. Preserve API contracts.
	- Avoid breaking JSON shapes unless asked.
5. Keep billing webhooks public and signature-verified.

## Frontend Architecture Rules

1. Prefer composables over duplicated logic.
2. Keep API calls isolated in services.
3. Use typed DTOs/interfaces when possible.
4. Avoid business logic inside Vue components.
5. Use reusable UI components.
6. Keep pages thin and declarative.
7. Optimize hydration and lazy loading.

## AI Integration Responsibilities

- Design AI-ready backend flows.
- Keep prompts versioned.
- Isolate AI providers behind services.
- Avoid coupling business logic to OpenAI SDKs.
- Store AI interactions for auditing.
- Support async AI jobs through queues.
- Optimize token usage and context windows.

## SaaS Rules

- Support subscription plans and feature gating.
- Enforce clinic quotas and usage limits.
- Separate tenant configuration cleanly.
- Ensure onboarding flows are isolated.
- Track tenant metrics and events.

## Observability

- Log important domain events.
- Add structured logs for failures.
- Prefer domain-specific exceptions.
- Add metrics hooks where relevant.
- Keep webhook processing traceable.

## Backend Business Logging Standard

- Keep business logs structured with stable keys: `event`, `result`, `clinic_id`, `user_id`, resource IDs, `error_code`, `error_category`.
- Required diagnostic events:
  - `auth.login.success`
  - `auth.login.failed`
  - `subscription.failed`
  - `reminder.sent`
  - `reminder.failed`
  - `payment.created`
- Severity policy:
  - `info` for successful business events.
  - `warning` for expected failures.
  - `error` for exceptions or unexpected failures.
- Never log sensitive data:
  - passwords
  - tokens
  - card data
  - Stripe/API secrets
  - full credential payloads
- If email context is needed, log only safe derivatives (e.g. domain), never full addresses.

## Security Rules

- Never expose tenant-sensitive data.
- Validate frontend permissions against backend policies.
- Sanitize uploads and user-generated content.
- Use signed URLs where applicable.
- Protect against mass assignment and overfetching.

## Soft Delete Policy

- Treat `destroy` operations as soft deletes for core clinical entities.
- Required soft-deleted entities:
  - `patients`
  - `appointments`
  - `documents` (invoices/abonos)
- Visibility rules:
  - Operational views (lists/detail used day-to-day): hide soft-deleted rows by default.
  - Historical/audit contexts: use explicit `withTrashed()` only where needed.
  - Keep invoices/history resilient when related patient or appointment is soft-deleted.
- Never expose `forceDelete` in normal product flows.
- If restore endpoints are implemented, keep them policy-protected and tenant-scoped.

## Testing Responsibilities

Backend:
- Pest feature tests
- Policy tests
- Service tests
- Queue tests

Frontend:
- Component tests
- Store tests
- Form validation tests

E2E:
- Authentication flows
- Billing flows
- Multi-tenant isolation
- Critical SaaS workflows

## Performance Rules

- Avoid N+1 queries.
- Use eager loading intentionally.
- Cache expensive tenant queries.
- Paginate large datasets.
- Debounce frontend search requests.
- Lazy load heavy frontend modules.

## Working Workflow

1. Read related backend and frontend files before editing.
2. Trace data flow end-to-end.
3. Update API contracts carefully.
4. Keep frontend and backend aligned.
5. Run focused tests before broad suites.
6. Prefer incremental refactors over rewrites.
7. Document architectural decisions briefly.

## Key Files

- `routes/api.php`
- `bootstrap/app.php`
- `app/Models/Concerns/BelongsToClinic.php`
- `app/Models/Scopes/ClinicScope.php`
- `app/Traits/MultiTenantAuthorization.php`
- `app/helpers.php`
- `routes/console.php`

## Working Style

- Read affected files before editing.
- Apply minimal focused patches.
- If schema changes, consider migration + model + policy + tests impact.
- After edits, run targeted tests first, then broader tests when useful.

## Tech Stack

Backend:
- Laravel 12
- PHP 8.3
- PostgreSQL
- Redis
- Sanctum
- Horizon
- Queues
- Scheduler
- Stripe

Frontend:
- Vue 3
- Vite
- Pinia
- Vue Router
- TailwindCSS
- Axios

Infrastructure:
- Docker
- Hetzner
- GitHub Actions
- Nginx

Testing:
- PestPHP
- Vitest
- Playwright

## Online Booking System

### Configuración (admin)

1. Ir a Configuración > Reserva Online en el frontend.
2. Pestaña "Configuración": definir slug, título, activo, horizonte máximo, política de cancelación.
3. Pestaña "Servicios": crear servicios ofrecidos online (nombre, duración, precio, activo).
4. Pestaña "Profesionales": habilitar profesionales con `allow_online_booking = true`, configurar horario semanal (`day_of_week` ISO 1-7, `start_time`, `end_time`) y excepciones puntuales.

### Endpoints admin (auth:sanctum + clinic, sin check.subscription)

| Método | Ruta | Propósito |
|--------|------|-----------|
| GET | `/api/booking/settings` | Obtener configuración (devuelve defaults si no existe) |
| PUT | `/api/booking/settings` | Crear/actualizar configuración |
| GET | `/api/booking/services` | Listar servicios |
| POST | `/api/booking/services` | Crear servicio |
| PUT | `/api/booking/services/{id}` | Actualizar servicio |
| DELETE | `/api/booking/services/{id}` | Eliminar servicio |
| GET | `/api/booking/professionals` | Listar profesionales de la clínica |
| PUT | `/api/booking/professionals/{id}` | Actualizar profesional (allow_online_booking) |
| GET | `/api/booking/professionals/{id}/schedules` | Horarios de un profesional |
| POST | `/api/booking/professionals/{id}/schedules` | Crear horario |
| PUT | `/api/booking/professionals/{id}/schedules/{sid}` | Actualizar horario |
| DELETE | `/api/booking/professionals/{id}/schedules/{sid}` | Eliminar horario |
| GET | `/api/booking/professionals/{id}/exceptions` | Excepciones de un profesional |
| POST | `/api/booking/professionals/{id}/exceptions` | Crear excepción |
| PUT | `/api/booking/professionals/{id}/exceptions/{eid}` | Actualizar excepción |
| DELETE | `/api/booking/professionals/{id}/exceptions/{eid}` | Eliminar excepción |

### Endpoints públicos (throttle:30,1, sin auth)

| Método | Ruta | Propósito |
|--------|------|-----------|
| GET | `/api/booking/{slug}` | Obtener página pública (slug, servicios, profesionales) |
| GET | `/api/booking/{slug}/availability/dates?year=&month=&service_id=&professional_id=` | Días disponibles para un mes |
| GET | `/api/booking/{slug}/availability/slots?date=&service_id=&professional_id=` | Slots disponibles para un día |
| POST | `/api/booking/{slug}/appointments` | Crear cita online (name, email, phone, service_id, professional_id, date, start_time) |
| GET | `/api/booking/appointments/{token}/cancel` | Cancelar cita por token |

### Flujo completo de obtención de turno online

1. El paciente accede a `/booking/{slug}`.
2. Selecciona servicio → profesional → fecha → horario → datos personales.
3. Confirma y se crea la cita vía `POST /api/booking/{slug}/appointments`.
   - Backend: `PublicBookingService::create()` con `DB::transaction` + `lockForUpdate` para evitar doble reserva.
   - Se notifica al paciente (`BookingConfirmation`) y a los owners de la clínica (`NewOnlineBooking`).
4. El paciente recibe email con resumen y enlace para cancelar.
5. Las citas online se crean con `booking_source = 'online'` en la tabla `appointments`.

### Availability Engine (app/Services/Booking/AvailabilityEngine.php)

- `getAvailableSlots(service, professional, date)`: genera slots de `duration_minutes` en intervalos de 15 min dentro del horario del profesional, restando citas existentes y excepciones.
- `getAvailableDates(service, professional, year, month)`: días con disponibilidad potencial.
- Respeta `max_horizon_days` de `BookingPage`.

### Tests

```
php artisan test --filter=Booking
```

### Notas importantes

- Las rutas admin de booking deben ir ANTES de `GET /api/booking/{slug}` en `routes/api.php` para evitar que `{slug}` capture rutas fijas.
- Cuando no existe `BookingPage`, `GET /api/booking/settings` devuelve defaults (slug=null, title="Reserva tu cita", etc.). El frontend siempre recibe un objeto, nunca null.
- `lockForUpdate` requiere `DB::transaction` wrapping el insert + slot check.
- `day_of_week` en `ProfessionalSchedule` usa ISO-8601 (1=lunes, 7=domingo).
