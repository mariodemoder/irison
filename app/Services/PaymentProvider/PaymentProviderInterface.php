<?php

namespace App\Services\PaymentProvider;

interface PaymentProviderInterface
{
    /**
     * Create a checkout and return data needed by frontend.
     */
    public function createCheckout(array $data): array;

    /**
     * Handle webhook payload from provider.
     */
    public function handleWebhook(array $payload): void;

    /**
     * Cancel an existing subscription in the payment provider.
     */
    public function cancelSubscription(array $data): void;

    /**
     * Human-friendly provider name key
     */
    public function getName(): string;

    /**
     * Preview the prorated invoice for an upgrade without applying changes.
     *
     * @param  array  $data  {
     *                       clinic: Clinic
     *                       current_plan: string
     *                       new_plan: string
     *                       subscription_reference: ?string
     *                       }
     * @return array {
     *               success: bool
     *               has_existing_subscription: bool
     *               current_plan: array{name: string, amount: int}
     *               new_plan: array{name: string, amount: int}
     *               credit_for_unused_days: int
     *               prorated_new_plan_cost: int
     *               amount_due_now: int
     *               next_billing_date: ?string
     *               next_billing_amount: int
     *               currency: string
     *               details: array<int, array{description: string, amount: int}>
     *               }
     */
    public function previewUpgrade(array $data): array;

    /**
     * Upgrade an existing subscription to a new plan.
     * The provider handles proration internally.
     *
     * @param  array  $data  {
     *                       clinic: Clinic
     *                       current_plan: string
     *                       new_plan: string
     *                       subscription_reference: ?string
     *                       metadata?: array
     *                       }
     * @return array {
     *               success: bool
     *               action: 'upgraded'|'checkout_required'
     *               amount_charged: int
     *               checkout_url: ?string
     *               provider_ref: ?string
     *               invoice_id: ?string
     *               invoice_url: ?string
     *               }
     */
    public function upgradeSubscription(array $data): array;
}
