<?php

namespace Modules\Subscriptions\Domain\Contracts;

use Illuminate\Http\Request;

interface PaymentProviderInterface
{
    /**
     * Create a checkout and return data needed by frontend.
     */
    public function createCheckout(array $data): array;

    /**
     * Confirm a checkout/payment session and mark it paid when applicable.
     *
     * @param  array  $data  {
     *                       session_id: ?string
     *                       clinic?: Clinic
     *                       }
     * @return array {
     *               status: 'paid'|'pending'
     *               message?: string
     *               payment_method?: ?string
     *               customer?: ?string
     *               subscription?: ?string
     *               invoice_id?: ?string
     *               invoice_url?: ?string
     *               }
     */
    public function confirmCheckout(array $data): array;

    /**
     * Handle webhook payload from provider.
     * Throws InvalidWebhookSignatureException when signature verification fails.
     */
    public function handleWebhook(Request $request): void;

    /**
     * Cancel an existing subscription in the payment provider.
     */
    public function cancelSubscription(array $data): void;

    /**
     * Human-friendly provider name key
     */
    public function getName(): string;

    /**
     * Resolve the hosted invoice URL for a given provider invoice id.
     */
    public function resolveInvoiceUrl(?string $invoiceId): ?string;

    /**
     * List recent invoices for a clinic.
     *
     * @return array<int, array{id: string, status: string, amount_due: int, currency: string, hosted_invoice_url: ?string, created: int}>
     */
    public function listInvoices(array $data): array;

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
