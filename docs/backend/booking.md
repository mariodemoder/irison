# Reserva Online — Booking Module

Bounded context `modules/Booking/` que gestiona la reserva pública de citas online.

## Flujo "Cualquier profesional"

El paciente puede seleccionar **"Cualquier profesional disponible"** al elegir el profesional. Este flujo:

### Agregación de horarios activos
1. `AvailabilityController` acepta `professional_id` **nullable**.
2. `AvailabilityEngine::resolveProfessionals(clinicId, null)` retorna todos los `BookingProfessional` con `allow_online_booking = true` de la clínica.
3. Para cada profesional se resuelven sus horarios: `ProfessionalSchedule` (específico de booking) primero; fallback a `UserSchedule` (`enabled = true`).
4. Las excepciones/bloqueos se resuelven igualmente: `ScheduleException` primero; fallback a `UserScheduleException` (soporta `end_date`).
5. Se generan slots de 15 min por cada profesional, y el resultado es la **unión** de todos ellos (con `professional_id` y `professional_name`).

### Creación de cita sin profesional
- El frontend envía `professional_id: null` al endpoint `POST /api/booking`.
- `PublicBookingService::createAppointment(..., null, ...)`:
  - Omite la validación de `BookingProfessional`.
  - Valida que el `start_time` del slot coincida con algún slot en la lista agregada (match por `start`).
  - La cita se crea con `professional_id = NULL`.

### Semántica de bloqueo
Una reserva sin profesional **bloquea el hueco para todos los profesionales**:

- `AvailabilityEngine::getExistingAppointments()` cuenta citas `professional_id = X **o NULL**` → una reserva sin asignar ocupa el slot para cualquier profesional en la página pública.
- `PublicBookingService` (check de solapamiento dentro de la transacción):
  - Cita con profesional concreto → comprueba contra `professional_id = X OR NULL`.
  - Cita sin profesional → comprueba contra `professional_id IS NULL` (dos reservas "cualquiera" no pueden compartir hueco).
- Resultado: una vez creada una reserva sin profesional, el hueco desaparece para todos en la disponibilidad pública. El admin lo asigna manualmente después.

### Visualización en la agenda
Las citas con `professional_id = NULL` se muestran bajo el nombre del **owner/admin** de la clínica en `AgendaDay`, `AgendaWeek` y `Show`, gracias a la convención existente `professional?.name || clinicOwnerName`.

### Notificaciones
- **Paciente** (`BookingConfirmation`): "Profesional por asignar" cuando no hay profesional.
- **Clínica** (`NewOnlineBooking`): se envía igualmente a los owners.

## Auto-bootstrap para Plan Basic

Al registrarse una nueva clínica (siempre plan basic), el sistema **auto-crea** la configuración mínima de booking:

1. **BookingProfessional** → el owner se convierte automáticamente en profesional con `allow_online_booking = true`.
2. **BookingPage** → página activa con slug generado (`Str::slug($clinic->name)` + sufijo si hay colisión), horizonte 60 días, cancelación 24h.
3. **UserSchedule + ProfessionalSchedule** → L-V 09:00-17:00 habilitados, S-D deshabilitados.

Resultado: el owner aparece inmediatamente en la página pública de booking con horario configurado.

### Sincronización de horarios (Team → Booking)

Cuando el owner modifica su horario en la página de Equipo (`TeamUserForm`), `TeamUserService::syncSchedules()` sincroniza también a `ProfessionalSchedule` si el usuario tiene `allow_online_booking = true`. Esto mantiene los dos sistemas alineados y evita el badge "Usando horario de Equipo" en BookingSettings.

### UI de configuración admin (`BookingSettings.vue`)

El tab "Reserva Online" de Servicios (`resources/js/views/settings/BookingSettings.vue`) tiene un header propio con el título **"Reserva Online"** (igual que el resto de pestañas), un botón de ayuda **"?"** (`BookingHelpModal.vue`) y, arriba a la derecha (visible en cualquier sub-pestaña siempre que exista slug), los dos botones **"Ver página pública ↗"** y **"Copiar enlace público"** hacia `{origin}/booking/{slug}`. Guía para clínicas: `docs/cliente/booking.md`.

## Tests

```bash
# Suite completa del módulo (73 tests)
php artisan test --testsuite=Booking

# Solo availability engine
php artisan test modules/Booking/tests/Feature/AvailabilityEngineTest.php

# Solo flujo público
php artisan test modules/Booking/tests/Feature/PublicBookingFlowTest.php

# Solo servicio de dominio
php artisan test modules/Booking/tests/Unit/PublicBookingServiceTest.php

# Solo auto-bootstrap
php artisan test modules/Booking/tests/Feature/BookingBootstrapTest.php

# Solo sync Team→Booking
php artisan test modules/Booking/tests/Feature/TeamScheduleSyncTest.php
```

### Tests cubiertos

| Archivo | Tests | Cubre |
|---|---|---|
| `BookingBootstrapTest` | 4 | Auto-create BookingProfessional, BookingPage, default schedules (UserSchedule + ProfessionalSchedule), slug collision handling |
| `TeamScheduleSyncTest` | 3 | syncSchedules creates ProfessionalSchedule for booking users, skips for non-booking users, clears old schedules on update |
| `AvailabilityEngineTest` | 14 | Slots disponibles, booked exclusion, blocked dates, sin horario, max horizon, partial blocked range, multi-profesional, canceled ignored, granularidad 15min, fallback user_schedule, preferencia booking sobre user, fallback user_schedule_exception, **NULL professional blocks all professionals** |
| `PublicBookingFlowTest` | 22 | Happy path, disponibilidad, slots, crear cita, solapamiento, cancelar, página/servicio inactiva, profesional sin online, fecha pasada, horizonte, confirm token, paciente existente, **cita sin profesional (any), solapamiento any, bloqueo por cita sin asignar, logo por plan** |
| `BookingSettingsAdminTest` | 15 | Settings default, create/update settings, CRUD servicios, CRUD profesionales, CRUD horarios, auth requerida, update/delete profesional, update/delete horario, CRUD excepciones, listar appointments, slug-check, schedule index, bulk update |
| `PublicBookingServiceTest` | 15 | Resolve page, page not found, page inactive, create patient, existing patient, inactive service, professional no online, past date, beyond horizon, cancel past, find by token, invalid token, **create without professional, slot validation with any-professional, past date with null professional** |
