# Team / Equipo Skill

Módulo de administración de usuarios del equipo, perfiles, profesiones y horarios laborales.

## Core Files

### Backend

| Capa | Archivo | Propósito |
|---|---|---|
| Model | `app/Models/Profile.php` | Perfiles globales: Administrador, Gestor, Profesional |
| Model | `app/Models/Profession.php` | Profesiones por clínica (tenant-scoped) |
| Model | `app/Models/UserSchedule.php` | Horario semanal por usuario (day_of_week 0-6, start, end, enabled) |
| Model | `app/Models/UserScheduleException.php` | Excepciones de horario por fecha |
| Model | `app/Models/User.php` | Añadido: `profile_id`, `profession_id`, `allow_online_booking` |
| Model | `app/Models/Clinic.php` | Añadido: `max_users`, `professions()` |
| Service | `app/Services/Team/TeamUserService.php` | CRUD usuarios + horarios + enlace Booking |
| Service | `app/Services/Team/ProfessionService.php` | CRUD profesiones |
| Controller | `app/Http/Controllers/Api/Team/UserController.php` | Endpoints REST /team/users |
| Controller | `app/Http/Controllers/Api/Team/ProfessionController.php` | Endpoints REST /team/professions |
| Controller | `app/Http/Controllers/Api/Team/ProfileController.php` | GET /team/profiles |
| Request | `app/Http/Requests/Team/StoreUserRequest.php` | Validación crear usuario |
| Request | `app/Http/Requests/Team/UpdateUserRequest.php` | Validación editar usuario |
| Request | `app/Http/Requests/Team/StoreProfessionRequest.php` | Validación crear profesión |
| Request | `app/Http/Requests/Team/UpdateProfessionRequest.php` | Validación editar profesión |
| Gate | `App\Providers\AppServiceProvider` | `team-access`: solo admin/manager |

### Frontend

| Archivo | Propósito |
|---|---|
| `resources/js/views/team/Team.vue` | Página principal con tabs Usuarios/Profesiones |
| `resources/js/views/team/TeamUserForm.vue` | Formulario crear/editar usuario con horarios |
| `resources/js/layouts/MainLayout.vue` | Sidebar: navItem `/team` "Equipo" + icono |
| `resources/js/router/index.js` | Rutas `/team/users`, `/team/professions` |

### Migraciones

| Migración | Tablas |
|---|---|
| `xxxx_create_profiles_table.php` | `profiles` (global, seed: Admin/Gestor/Profesional) |
| `xxxx_create_professions_table.php` | `professions` (clinic_id, name) |
| `xxxx_create_user_schedules_table.php` | `user_schedules` (user_id, day_of_week, start, end, enabled) |
| `xxxx_create_user_schedule_exceptions_table.php` | `user_schedule_exceptions` (user_id, date, start, end, reason) |
| `xxxx_add_team_fields_to_users_table.php` | `users`: +profile_id, +profession_id, +allow_online_booking |
| `xxxx_add_max_users_to_clinics_table.php` | `clinics`: +max_users (default 3) |

## Rules & Architecture

### Profile-Based Access

- Gate `team-access` definido en `AppServiceProvider`:
  ```php
  Gate::define('team-access', fn ($user) => in_array($user->profile?->slug, ['admin', 'manager']));
  ```
- Usar `Gate::authorize('team-access')` en cada método de controlador Team.
- El owner de la clínica se asigna automáticamente perfil `admin` (slug) al crearse/verificarse.

### User Limits

- `clinic.max_users` controla cuántos usuarios puede tener la clínica (owner incluido).
- Validar en `TeamUserService::store()`: contar `User::where('clinic_id', $clinicId)->count()` contra `$clinic->max_users`.
- El límite es configurable por plan (se modifica `clinic.max_users` desde backoffice).

### Schedule Storage — Tablas Nuevas

- Los horarios de cada usuario se guardan en `user_schedules` (no reutilizar `ProfessionalSchedule`).
- Tipos de excepción: `full_day` (sin hora) o `time_range` (con start/end).
- Al crear usuario sin schedules explícitos, clonar los `business_hours` de la clínica como defaults (mapear enabled, start, end a day_of_week).

### Booking Link

- El checkbox "Habilitar reserva online" (`allow_online_booking`) controla la creación del `BookingProfessional`.
- En `TeamUserService::store()/update()`:
  - Si `allow_online_booking = true` y no existe `BookingProfessional` → crearlo con `user_id`.
  - Si `allow_online_booking = false` y existe `BookingProfessional` → desactivar (no eliminar). Poner `allow_online_booking = false` en el registro existente.
- El AvailabilityEngine ya consulta `BookingProfessional` con `allow_online_booking = true`; no requiere cambios.
- Los horarios de booking (ProfessionalSchedule) son independientes de user_schedules. Si se necesita sincronización futura, se hace desde el formulario de usuario.

### Owner Immutability

- El usuario owner (`role = 'owner'`) tiene perfil `admin` fijo.
- Su perfil no puede cambiarse desde el TeamUserForm (deshabilitar selector).
- No se puede eliminar al owner.

### API Endpoints

```
GET    /team/profiles          → Profile::all()
GET    /team/users             → Paginated list with profile, profession, schedules, exceptions
POST   /team/users             → Create user + schedules + optionally BookingProfessional
GET    /team/users/{id}        → Show user with relations
PUT    /team/users/{id}        → Update user + schedules + BookingProfessional sync
DELETE /team/users/{id}        → Soft delete + cleanup BookingProfessional
GET    /team/professions       → Clinic's professions list
POST   /team/professions       → Create profession
PUT    /team/professions/{id}  → Update profession
DELETE /team/professions/{id}  → Delete profession (fail if linked to users)
```

Todas las rutas bajo el middleware group `auth:sanctum + clinic + check.subscription` y protegidas por `team-access`.

## Known Pitfalls

1. **Profile no es Role:** El User tiene `role` (owner) y `profile_id` (admin/manager/professional). Son conceptos distintos. `role` es el propietario de la clínica; `profile` es el nivel de acceso del usuario dentro del equipo. No confundir ni mezclar.
2. **User schedule default:** Siempre clonar `business_hours` de la clínica. Si la clínica no tiene horarios, crear schedules por defecto (Lun-Vie 09:00-18:00 enabled, Sab-Dom disabled).
3. **max_users cuenta al owner:** El límite incluye al usuario admin (owner). Si `max_users = 3`, solo hay 2 slots disponibles para crear nuevos usuarios.
4. **Frontend sidebar:** Condicionar la visibilidad del menú "Equipo" al perfil del usuario autenticado. Cargar `profile` en `/me` y usar `v-if` en el nav item.
5. **BookingProfessional cleanup:** Al hacer soft-delete de un usuario, no eliminar automáticamente el BookingProfessional (por integridad histórica de citas). Dejar el registro pero marcar `allow_online_booking = false`.

## Tests

- `tests/Feature/Team/` — Feature tests para endpoints team
- Casos mínimos: crear usuario (éxito), excede límite, sin permisos, perfil owner inmutable, CRUD profesiones, enlace booking toggle
- Correr con: `php artisan test --filter=Team`
