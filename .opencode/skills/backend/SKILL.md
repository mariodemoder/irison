---
name: backend
description: Use when working on backend logic: business logging conventions (events, context keys), soft deletes on patients/appointments/documents, DB pitfalls. Load for Laravel controllers/services/database work.
---

# Backend Skill

## Business Logging
- Use stable event names + context keys (`event`, `result`, `clinic_id`, `user_id`, resource IDs, `error_code`)
- Required diagnostic events: `auth.login.success|failed`, `subscription.failed`, `reminder.sent|failed`, `payment.created`
- `info` for successful events, `warning` for expected failures, `error` for exceptions
- Never log: passwords, tokens, card data, Stripe secrets, full credential payloads
- For email context, log only safe derivatives (domain), never full addresses

## Soft Deletes (Critical Records)
Scope: `patients`, `appointments`, `documents` (invoices/abonos).

- Daily views: exclude soft-deleted records
- Historical/audit: include via explicit `withTrashed`
- Billing/history: keep invoice history readable even if linked entity was soft-deleted
- `destroy` = soft delete. `forceDelete` is not part of normal product flows.

## Known Pitfalls
- Local dev expects PostgreSQL by default from `.env.example`
- On Windows/WAMP, ensure `pdo_pgsql` and `pgsql` in PHP CLI and Apache
- SQLite in-memory tests in `phpunit.xml`, but some legacy migrations may include MySQL-specific SQL
- Queue defaults to `database`; in production use process supervision and restart queues on deploy
