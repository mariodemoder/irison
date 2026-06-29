# Billing Skill — Planes y Suscripciones

## Planes disponibles (`Clinic::PLAN_USER_LIMITS`)

Definidos en `app/Models/Clinic.php`:
| Plan | Máx usuarios | Stripe Price ID |
|------|-------------|-----------------|
| `basic` | 3 | `STRIPE_PRICE_ID` (env) |
| `pro` | 6 | `STRIPE_PRICE_ID` (env) |
| `enterprise` | 10 | `STRIPE_PRICE_ID` (env) |

Todos los planes usan el mismo `STRIPE_PRICE_ID` de entorno; la diferenciación es por `plan` y `max_users` en DB.

## Restricción de usuarios por plan

- `clinics.max_users` default: 3 (basic)
- Al cambiar plan via backoffice → `max_users` se actualiza automáticamente desde `Clinic::PLAN_USER_LIMITS`
- Al registrar clínica → se setea `plan=basic`, `max_users=3`
- Límite controlado en: `app/Services/Team/TeamUserService.php:87-90`
- Frontend muestra "X / Y usuarios" en `resources/js/views/team/Team.vue`

## Stripe Error Handling
- Unreachable Stripe → backend returns `503` with `code=STRIPE_UNREACHABLE` from `app/Http/Controllers/BillingController.php`
- UI fallback in `resources/js/views/BillingRequired.vue` (local activation in dev only)
- Local fallback: `POST /api/subscribe/fake` (`app/Http/Controllers/Api/FakeSubscribeController.php`)

## Stripe Customer Sync
Mandatory for Backoffice visibility. Persist on clinic:
- `clinics.stripe_id`
- `clinics.stripe_customer_id`

Write points (must keep synced):
- `app/Http/Controllers/BillingController.php` — `confirmCheckout`
- `app/Http/Controllers/Api/StripeWebhookController.php` — `checkout.session.completed`

## `clinic.subscribed_at` — Must Set on Activation
Set to now on:
- `POST /api/subscribe` (`SubscribeController.php`)
- `POST /api/subscribe/fake` (`FakeSubscribeController.php`)
- `POST /api/billing/confirm` (`BillingController.php`)
- Stripe webhook `checkout.session.completed` (`StripeWebhookController.php`)

Webhook hardening: support fallback by `metadata.clinic_id` and by Stripe customer ID (not just `customer_email`).

## Pre-Payment Data Gate (Clinic Tab)
Before activating paid plan, require:
- Valid Spanish tax ID (DNI/NIE/CIF)
- Non-empty clinic address

Frontend guard: `resources/js/views/Configuration.vue` (disabled buttons + toast + redirect to Clinic tab).

## BillingRequired Copy — State-Aware
- Trial expired (`blocked` / `trial_read_only`): show urgency copy
- Trial active: positive onboarding copy + days left if available
