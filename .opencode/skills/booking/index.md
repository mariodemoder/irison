# Online Booking Skill

## Arquitectura del Módulo

```
modules/Booking/
├── Config/booking.php              — Valores por defecto (60 días horizonte, 24h cancelación)
├── Contracts/
│   └── AvailabilityCheckerInterface.php  — Interfaz del motor de disponibilidad
├── Database/Migrations/            — 5 migraciones (booking_pages, servicios, profesionales, horarios, excepciones)
├── Http/Controllers/
│   ├── PublicBookingPageController.php   — GET /api/booking/{slug}
│   ├── AvailabilityController.php        — GET /api/booking/availability, /slots
│   ├── PublicBookingController.php       — POST /api/booking, GET/POST cancel
│   ├── BookingSettingsController.php     — GET|PUT /api/booking/settings, slug-check
│   ├── BookingServiceController.php      — CRUD /api/booking/services
│   ├── BookingProfessionalController.php — CRUD /api/booking/professionals
│   ├── ScheduleController.php            — CRUD /api/booking/.../schedules
│   ├── ExceptionController.php           — CRUD /api/booking/.../exceptions
│   └── BookingAppointmentController.php  — GET /api/booking/appointments
├── Models/
│   ├── BookingPage.php              — Página de reserva por clínica (slug único, is_active, horizonte)
│   ├── BookingService.php           — Servicio reservable (duración, precio, activo)
│   ├── BookingProfessional.php      — Profesional habilitado para booking online
│   ├── ProfessionalSchedule.php     — Horario semanal (day_of_week 1-7 ISO, start/end)
│   └── ScheduleException.php        — Bloqueo total o parcial por fecha
├── Notifications/
│   ├── BookingConfirmation.php      — Email al paciente con resumen + link de cancelación
│   └── NewOnlineBooking.php         — Email a owners con datos del paciente
├── Providers/
│   └── BookingServiceProvider.php   — Binding AvailabilityCheckerInterface → AvailabilityEngine
├── Routes/api.php                   — Todas las rutas del módulo
├── Services/
│   ├── AvailabilityEngine.php       — Motor de disponibilidad (slots, fechas)
│   └── PublicBookingService.php     — Orquestador de reserva pública
└── tests/                           — Tests del bounded context (ver sección Tests)
```

## Models

- `BookingPage` — Public booking page per clinic. Unique slug.
- `BookingService` — Online services (duration, price, active)
- `BookingProfessional` — Pivot User ↔ online booking. `allow_online_booking` controls visibility
- `ProfessionalSchedule` — Weekly schedule per pro (day_of_week 1-7 ISO, Lun=1)
- `ScheduleException` — Day-level or time-range blocks per pro
- `UserSchedule` — Team-wide weekly schedule (day_of_week 0-6 ISO, Dom=0, `enabled` flag). Fallback cuando no hay `ProfessionalSchedule`.
- `UserScheduleException` — Team-wide day-level or time-range blocks. Fallback cuando no hay `ScheduleException`. Soporta `end_date` para rangos de fechas.

### Arquitectura de Horarios (Dual System)

Booking tiene dos sistemas de horarios. `AvailabilityEngine` resuelve en orden de precedencia:

1. **`ProfessionalSchedule`** (específico de booking) — si existe para el día, se usa
2. **`UserSchedule`** (horario del equipo) — si no existe `ProfessionalSchedule`, se usa como fallback

Misma lógica para excepciones/bloqueos:
1. **`ScheduleException`** (específico de booking) — si existe para la fecha, se usa
2. **`UserScheduleException`** (excepción del equipo) — si no existe `ScheduleException`, se usa como fallback

**Mapeo day_of_week**: `ProfessionalSchedule` usa ISO 1-7 (Lun=1), `UserSchedule` usa 0-6 (Dom=0). El mapping es: `$userDow = $dayOfWeekIso === 7 ? 0 : $dayOfWeekIso;`

**Eager loading**: `resolveProfessionals()` carga `user.schedules` y `user.scheduleExceptions` para evitar N+1 queries en el fallback.

## Domain Services

### `PublicBookingService` (4 métodos públicos)

| Método | Parámetros | Retorna | DomainException |
|---|---|---|---|
| `resolveBookingPage(slug)` | `string $slug` | `BookingPage` | "Página de reserva no encontrada o desactivada." |
| `createAppointment(slug, serviceId, professionalId, date, startTime, patientData)` | `string, int, int, string, string, array` | `Appointment` | 6 posibles: página no encontrada, servicio no disponible, profesional no disponible, fecha pasada, horizonte excedido, slot no disponible |
| `cancelByToken(token)` | `string $token` | `Appointment` | "Token de cancelación no válido.", "No se puede cancelar una cita que ya ha pasado." |
| `findByToken(token)` | `string $token` | `Appointment` | "Cita no encontrada." |

### `AvailabilityEngine` (implementa `AvailabilityCheckerInterface`)

| Método | Descripción |
|---|---|
| `getAvailableSlots(clinicId, serviceId, professionalId, date)` | Slots de 15min dentro del horario, excluyendo ocupados y bloqueados |
| `getAvailableDates(clinicId, serviceId, professionalId, yearMonth)` | Días del mes con disponibilidad potencial |
| `resolveSchedules(bp, dayOfWeekIso)` | `ProfessionalSchedule` primero; fallback a `UserSchedule` (mapea ISO→User DOW) |
| `professionalHasSchedule(bp, dayOfWeekIso)` | Check de existencia en ambos sistemas |
| `getBlockedRanges(bp, date)` | `ScheduleException` primero; fallback a `UserScheduleException` (soporta `end_date`) |
| `isProfessionalBlocked(bp, date)` | Full-day block check en ambos sistemas |

## Routes: Public vs Admin

### Públicas (throttle:30,1, sin auth)

| Método | URI | Controller |
|---|---|---|
| GET | `/api/booking/availability` | `AvailabilityController@dates` |
| GET | `/api/booking/slots` | `AvailabilityController@slots` |
| POST | `/api/booking` | `PublicBookingController@store` |
| GET | `/api/booking/confirm/{token}` | `PublicBookingController@show` |
| POST | `/api/booking/cancel/{token}` | `PublicBookingController@cancel` |
| GET | `/api/booking/{slug}` | `PublicBookingPageController@show` |

**Importante:** Las rutas específicas deben definirse ANTES de `/{slug}` para evitar captura.

### Admin (auth:sanctum + clinic middleware)

| Método | URI |
|---|---|
| GET/PUT | `/api/booking/settings` |
| GET | `/api/booking/slug-check` |
| CRUD | `/api/booking/services`, `/api/booking/professionals` |
| CRUD | `/api/booking/professionals/{id}/schedules` |
| CRUD | `/api/booking/professionals/{id}/exceptions` |
| GET | `/api/booking/appointments` |

## Reglas de Negocio Clave

- `createAppointment` usa **transacción DB con `lockForUpdate`** para evitar race conditions
- Notificaciones se envían vía `DB::afterCommit` (fallos se loggean, no se lanzan)
- Slots se generan con granularidad de **15 minutos**
- Solapamiento: cita nueva NO debe solaparse con citas existentes cuyo status NO sea `canceled`/`cancelled`
- `confirmation_token` es UUID generado con `Str::uuid()`

## Tests

Los tests viven DENTRO del módulo para respetar el bounded context.

```
modules/Booking/tests/
├── Feature/
│   ├── PublicBookingFlowTest.php        — Flujo público HTTP (15 tests)
│   ├── BookingSettingsAdminTest.php     — CRUD admin HTTP (11 tests)
│   └── AvailabilityEngineTest.php       — Motor de disponibilidad (9 tests)
└── Unit/
    └── PublicBookingServiceTest.php     — Servicio de dominio (12 tests)
```

**Total: ~51 tests**

### Ejecución

```bash
# Solo booking (bounded context aislado)
php artisan test --testsuite=Booking

# Tests de unidad del dominio nada más
php artisan test --testsuite=Booking --filter=Unit

# Tests HTTP del booking
php artisan test --testsuite=Booking --filter=Feature

# Todos los tests del proyecto
php artisan test

# Test específico
php artisan test modules/Booking/tests/Unit/PublicBookingServiceTest.php
```

### Cobertura por archivo

| Archivo | Tests | Cubre |
|---|---|---|
| `PublicBookingFlowTest` | 15 | Happy path, 404, disponibilidad, slots, crear cita, solapamiento, cancelar (token válido e inválido), página inactiva, servicio inactivo, profesional sin online, fecha pasada, horizonte, confirm token, paciente existente |
| `BookingSettingsAdminTest` | 11 | Settings default, create/update settings, CRUD servicios, CRUD profesionales, CRUD horarios, auth requerida, update/delete profesional, update/delete horario, CRUD excepciones, listar appointments, slug-check |
| `AvailabilityEngineTest` | 13 | Slots disponibles, booked exclusion, blocked dates, sin horario, max horizon, partial blocked range, multi-profesional, canceled ignored, granularidad 15min, fallback user_schedule, preferencia booking sobre user, fallback user_schedule_exception |
| `PublicBookingServiceTest` | 12 | Resolve page, page not found, page inactive, create patient, existing patient, inactive service, professional no online, past date, beyond horizon, cancel past, find by token, invalid token |

## Errores comunes

- **Slug capture**: Las rutas admin y específicas deben ir antes de `/{slug}` en `Routes/api.php`
- **allow_online_booking**: Profesional debe tener este flag en `true` para aparecer en booking público
- **Horizonte**: `max_horizon_days` en `BookingPage` controla cuántos días hacia adelante se puede reservar
- **Notificaciones**: Se envían post-commit; si el worker de colas no está activo, no se enviarán
- **Rate limiting**: Rutas públicas usan `throttle:30,1` — 30 requests por minuto
- **Soft deletes**: `Appointment` usa `SoftDeletes`; las queries de disponibilidad filtran por status, no por deleted_at
