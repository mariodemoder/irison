---
name: qa
description: Use ONLY on demand (`con tests`) or when the approved plan defines a "complete flow": focused feature tests, module regression, HTTP contract validation, risk hardening. Load before running php artisan test or validating behavior.
---

# QA Skill

## Delegation Rule
La ejecución de QA la hace `build` cargando esta skill. **Solo bajo demanda** (`con tests`) o si el plan/spec aprobado define un **"complete flow"** que lo incluya; nunca automático. Usarla cuando se necesite:
- Focused feature tests
- Module-specific regression
- HTTP contract validation
- Risk scenario hardening

## Testing Patterns
- Full test suite: `php artisan test`
- Specific filter: `php artisan test --filter=<name>`
- Booking tests: `php artisan test --filter=Booking`
- Billing lifecycle: `tests/Feature/BillingLifecycleTest.php`
- Appointment availability: `tests/Feature/AppointmentAvailabilityTest.php`
- Check `phpunit.xml` for SQLite in-memory defaults
