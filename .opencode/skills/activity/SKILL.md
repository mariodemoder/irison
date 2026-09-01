---
name: activity
description: Use when working on the Registro de Actividad module: activity_logs feed, ActivityLogger, login cap (3 per user/clinic), login events hidden from the SPA, backoffice clinic activity view.
---

# Skill: Registro de Actividad

## Cuándo usar este skill

Cuando se trabaje en el "Registro de actividad" (funcionalidad PRO), auditoría de eventos en `activity_logs`, logins de clínica/backoffice, o el feed del SPA de la clínica.

## Arquitectura

Módulo DDD ligero en `modules/Activity/`:

- `Domain/Contracts/ActivityRepositoryInterface.php` — Contrato del repo
- `Application/DTOs/ActivityFilterData.php` — Filtros `q`, `event`, `user_id`, `entity`, `from_date`, `to_date`, `per_page`
- `Application/UseCases/ListActivityQuery.php` — Orquestador
- `Infrastructure/Persistence/ActivityLogQueryModel.php` — Extiende `App\Models\ActivityLog` con `protected $table = 'activity_logs'` (obligatorio; si no, Eloquent infiere `activity_log_query_models`)
- `Infrastructure/Persistence/ActivityRepository.php` — Query + mapeo; **excluye `event = 'login'`** del feed del SPA
- `Infrastructure/Policies/ActivityPolicy.php` — Solo `hasFullAccess()` (owner/admin); receptionist → 403
- `Infrastructure/Controllers/ActivityController.php` — `GET /api/activity`, `pro.access` + policy
- `Infrastructure/Providers/ActivityServiceProvider.php` — Registrado en `bootstrap/providers.php`
- `Routes/api.php` — Prefijo `api`, middleware `auth:sanctum` / `clinic` / `check.subscription` / `pro.access`
- `Tests/Feature/ActivityApiTest.php` — Tests del feed, filtros, permisos y cap de logins

La escritura usa `App\Support\ActivityLogger` (nunca rompe el flujo principal) sobre la tabla `activity_logs` (`tenant_id`, `user_id`, `event`, `description`, `metadata`, `ip`, `created_at`). `entity`/`entity_id` se guardan en `metadata`.

Eventos actuales: `patient.*`, `appointment.*`, `payment.*`, `consent.*` (vía `LogConsentActivity`), `login`, `system_error_500`, `subscription_*`, `trial_extended`, `document_*`, `hard_delete_functional_data`.

## Reglas de login (importante)

- **Cap por usuario y clínica**: al registrar un login se poda `activity_logs`, dejando solo los **3 más recientes** del mismo `(tenant_id, user_id)` con `event = 'login'`. Implementado en `AuthController::pruneLoginLogs()`.
  - Consecuencia: una clínica con 2 usuarios queda con máx. 6 filas de login (3 × usuarios).
  - No borrar el bloqueo de login en `AuthController::login` (evento `login`, metadata `channel => 'spa'`).
- **Oculto al SPA**: el feed del "Registro de actividad" de la clínica **no muestra** `login`. Filtro en `ActivityRepository::search` (`where('event', '!=', 'login')`). No quitar ese filtro.
- **Backoffice**: `Backoffice\ClinicController::show` lee eventos `login` de `activity_logs` (junto a `document_created`, `system_error_500`, `subscription_*`, `trial_extended`) para la vista de la clínica — ya recibe los logins capados a 3 por usuario. No cambiar esa consulta sin revisar el cap.
- Logins de backoffice (AdminUser) NO se escriben en `activity_logs`; la impersonación usa `backoffice_clinic_activity` (eventos `admin_impersonate.start/end`).

## Convenciones

- Los logs de auditoría usan `ActivityLogger::log(tenantId:, userId:, event:, description:, metadata:, ip:)`.
- `metadata['entity']` y `metadata['entity_id']` para poder filtrar por entidad.
- El Activity Log nunca debe romper el flujo principal (catch `Throwable`).
- El feed del SPA no debe inflarse con logins; si se agregan nuevos eventos de autenticación, evaluar si deben excluirse igual que `login`.
