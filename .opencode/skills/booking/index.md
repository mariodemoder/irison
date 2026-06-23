# Online Booking Skill

## Models
- `Booking/BookingPage` — Public booking page per clinic. Unique slug.
- `Booking/BookingService` — Online services (duration, price, active)
- `Booking/BookingProfessional` — Pivot User ↔ online booking. `allow_online_booking` controls visibility
- `Booking/ProfessionalSchedule` — Weekly schedule per pro (day_of_week 1-7, ISO)
- `Booking/ScheduleException` — Day-level or time-range blocks per pro

## Availability Engine (`app/Services/Booking/AvailabilityEngine.php`)
- `getAvailableDates()` — Days with potential availability for a month
- `getAvailableSlots()` — Generates `duration_minutes` slots at 15-min intervals within schedule, subtracting existing appointments + exceptions
- Respects `max_horizon_days` from `BookingPage`

## Notifications
- `Booking/BookingConfirmation` — Email to patient with summary + cancel link
- `Booking/NewOnlineBooking` — Email to clinic owners with patient data

## Routes: Public vs Admin
- Admin routes (`/api/booking/settings`, `/api/booking/services/*`, etc.) must come BEFORE `GET /api/booking/{slug}` to avoid slug capture
- See `routes/api.php` for exact order
- Admin booking routes use middleware `['auth:sanctum', 'clinic']` (no `check.subscription`) — config is accessible even during trial or blocked state

## Tests
- `tests/Feature/Booking/` — 19 tests (engine, public flow, admin CRUD)
- Run: `php artisan test --filter=Booking`

## Deploy Notes
- 6 new tables + alter appointments
- Queue notifications use same `database` driver; ensure worker active
- No new scheduler tasks
- Rate limiting: `throttle:30,1` on public booking routes
- Logging: event `booking.created` with `event`, `clinic_id`, `result`
- Post-deploy: access `/booking/test-slug`, create online appointment, verify email
