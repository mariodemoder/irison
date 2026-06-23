# Billing Skill

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
