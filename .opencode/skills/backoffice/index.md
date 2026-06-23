# Backoffice Skill

## Stripe Customer Sync (Mandatory)
Backoffice invoice listing depends on clinic Stripe customer IDs.
Persist on clinic in both fields:
- `clinics.stripe_id`
- `clinics.stripe_customer_id`

Required write points:
- `BillingController::confirmCheckout`
- `StripeWebhookController::checkout.session.completed`

## Invoice Customer Resolution (Resilient)
In `ClinicController::loadStripeInvoices`, resolve in order:
1. `clinic.stripe_id`
2. `clinic.stripe_customer_id`
3. Latest `subscriptions.stripe_customer_id`
4. Fallback: Stripe lookup by clinic email

Merge invoices across all resolved customer IDs and dedupe by Stripe invoice ID.

## `clinic.subscribed_at` Activation Points
Set to now on:
- `POST /api/subscribe` (`SubscribeController`)
- `POST /api/subscribe/fake` (`FakeSubscribeController`)
- `POST /api/billing/confirm` (`BillingController`)
- `checkout.session.completed` webhook

Webhook hardening: fallback by `metadata.clinic_id` and Stripe customer ID (not just `customer_email`).

## Pre-Payment Gate
Before activating paid plan:
- Valid Spanish tax ID (DNI/NIE/CIF)
- Non-empty clinic address

Frontend guard: `Configuration.vue` (disabled buttons + toast + redirect to Clinic tab).
