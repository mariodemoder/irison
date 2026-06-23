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

## Overlap Validation — One Source of Truth
- Keep overlap validation ONLY in `AppointmentService` (via `CheckAvailability`)
- Remove from FormRequest (`ValidateSlotAvailability`) to avoid duplicate 422 shapes
- Test `test_reject_overlapping_appointment` asserts `assertJsonPath('error', '...')` (DomainException), not `errors.end_time`

## Helper: Use `currentClinicId()`
From `app/helpers.php`. NOT `clinic()` — that does not exist and throws fatal.

## Availability Service
- `CheckAvailability::validate()` → `['valid' => bool, 'errors' => []]`
- `CheckAvailability::check()` → `'disponible'` or `'ocupado'` (simpler, no patient check)
- Both use full Carbon datetimes; pass combined `date+time` objects, never bare `H:i` strings
