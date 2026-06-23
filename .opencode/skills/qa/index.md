# QA Skill

## Delegation Rule
Use IRISON QA (`.github/agents/irison-qa.agent.md`) when needing:
- Focused feature tests
- Module-specific regression
- HTTP contract validation
- Risk scenario hardening

Delegar ejecución y criterio de cobertura al agente QA.

## Testing Patterns
- Full test suite: `php artisan test`
- Specific filter: `php artisan test --filter=<name>`
- Booking tests: `php artisan test --filter=Booking`
- Billing lifecycle: `tests/Feature/BillingLifecycleTest.php`
- Appointment availability: `tests/Feature/AppointmentAvailabilityTest.php`
- Check `phpunit.xml` for SQLite in-memory defaults
