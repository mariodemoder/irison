---
name: appointments
description: Use when working on appointments: Form.vue, date/time payload normalization, slot granularity 15min, availability/overlap checks, bonus/credit consumption, AppointmentService. Debug agenda issues here.
---

# Appointments Skill

## Core Files
- Form UI: `resources/js/views/appointments/Form.vue`
- Date/time helpers: `resources/js/shared/appointmentHelpers.js`
- Slot granularity: 15 minutes
- API: `app/Http/Controllers/Api/AppointmentController.php`
- Service: `app/Services/Appointments/AppointmentService.php`
- Tenant isolation: via `clinic_id` + policies/scopes
- When changing availability/overlap: run `tests/Feature/AppointmentAvailabilityTest.php`

## Date/Time Payload Normalization
- Vue sends `start_time`/`end_time` as `YYYY-MM-DDTHH:mm` (datetime-local input)
- `StoreAppointmentRequest` / `UpdateAppointmentRequest` use `prepareForValidation()` to split into `date` (Y-m-d) + `start_time`/`end_time` (H:i)
- `AppointmentService::create()` recombines to full datetime (`Y-m-d H:i:s`) before `Appointment::create()`
- `date` is `unset()` from `$data` after combining

## ValidateDateAfterNow Rule
- Do NOT use `Carbon::parse($value)->isPast()` — treats today 00:00 as past
- Correct: `Carbon::parse($value)->startOfDay()->lt(Carbon::today())` — rejects only dates strictly before today

## Required Fields in StoreAppointmentRequest
Controller uses `$request->validated()`; missing fields are silently stripped.
Required: `price`, `payment_type`, `status`, `use_bonus_id`, `bonus_notes`, `apply_credit`, `apply_credit_mode`, `apply_credit_amount`, `use_credit_payment_id`, `use_credit_amount`
Missing `price` → DomainException `"Debes indicar el precio de la sesión"`

## Overlap Validation — Dual Path

There are TWO independent overlap checks — both must be kept in sync:

### 1. `ValidateSlotAvailability` rule (FormRequest layer)
- File: `app/Rules/ValidateSlotAvailability.php`
- Used in: `UpdateAppointmentRequest` on `end_time` field (not in `StoreAppointmentRequest`)
- Fails immediately (422) with "El horario {value} no está disponible..."
- Filters by `professional_id` when provided (nullable int); when null → `WHERE professional_id IS NULL`
- Pass `professionalId` from request: `$this->input('professional_id') !== null ? (int) ... : null`

### 2. `CheckAvailability` service (Service layer)
- File: `app/Services/Availability/CheckAvailability.php`
- Used in: `AppointmentService::checkAvailability()` (both create and update)
- Throws DomainException with "La franja horaria se solapa con otra cita."
- Filters by `professional_id` when provided (int); when null → `WHERE professional_id IS NULL`

### Per-professional rule
- Appointments with **different** professionals do NOT conflict (can overlap freely)
- Appointments with **no** professional assigned only conflict with other unassigned appointments
- Frontend overlap check (`findOverlaps` in `appointmentHelpers.js`) sends `no_professional=1` when no professional selected
- Backend `AppointmentService::list()` handles `no_professional` → `whereNull('professional_id')`

### Files to update together when changing overlap behavior:
- `app/Rules/ValidateSlotAvailability.php` — FormRequest 422 path
- `app/Services/Availability/CheckAvailability.php` — Service DomainException path
- `app/Services/Appointments/AppointmentService.php` — `list()` and `checkAvailability()`
- `resources/js/shared/appointmentHelpers.js` — `findOverlaps()` frontend check

## Helper: Use `currentClinicId()`
From `app/helpers.php`. NOT `clinic()` — that does not exist and throws fatal.

## Availability Service
- `CheckAvailability::validate()` → `['valid' => bool, 'errors' => []]`
- `CheckAvailability::check()` → `'disponible'` or `'ocupado'` (simpler, no patient check)
- Both use full Carbon datetimes; pass combined `date+time` objects, never bare `H:i` strings
