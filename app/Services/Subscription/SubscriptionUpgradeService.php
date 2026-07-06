<?php

namespace App\Services\Subscription;

use App\Events\CheckoutCreated;
use App\Events\PaymentCompleted as PaymentCompletedEvent;
use App\Events\SubscriptionUpgraded as SubscriptionUpgradedEvent;
use App\Models\BillingPayment;
use App\Models\Clinic;
use App\Models\SubscriptionRequest;
use App\Services\PaymentProvider\Resolver;

class SubscriptionUpgradeService
{
    public function __construct(
        private readonly SubscriptionRequestService $requestService,
        private readonly StripeCheckoutService $checkoutService,
        private readonly Resolver $paymentProviderResolver,
    ) {}

    public function approveAndGenerateCheckout(
        SubscriptionRequest $request,
        ?int $reviewedBy = null,
        ?string $reviewerComments = null,
    ): void {
        // Update request status and reviewer info
        $this->requestService->approveRequest($request, $reviewedBy, $reviewerComments);

        // Regla de negocio: si ya es plan basic pagado (no trial), completar upgrade automaticamente.
        if ($this->shouldAutoCompleteForPaidBasic($request)) {
            $payment = BillingPayment::create([
                'clinic_id' => $request->clinic_id,
                'amount' => $this->getPlanAmount($request->requested_plan),
                'currency' => 'EUR',
                'status' => 'paid',
                'provider' => 'stripe',
                'provider_ref' => 'auto_upgrade_' . $request->id,
                'method' => 'automatic_upgrade',
            ]);

            $this->handlePaymentCompleted($request, [
                'provider' => 'stripe',
                'payment_id' => $payment->id,
                'mode' => 'auto',
            ]);

            return;
        }

        // Create Stripe Checkout session
        $checkoutData = $this->createCheckoutForUpgrade($request);

        // Save checkout info
        $this->requestService->generateCheckoutUrl($request, $checkoutData);

        // Dispatch event for notifications
        event(new CheckoutCreated($request, $checkoutData));
    }

    public function handlePaymentCompleted(
        SubscriptionRequest $request,
        array $paymentData,
    ): void {
        if ($request->status === 'completed') {
            return;
        }

        $this->requestService->markAsPaid($request);
        $this->upgradeClinic($request);
        $this->requestService->completeSubscription($request);

        event(new PaymentCompletedEvent($request, $paymentData));
        event(new SubscriptionUpgradedEvent($request));
    }

    public function upgradeClinic(SubscriptionRequest $request): void
    {
        $clinic = $request->clinic;
        $plan = $request->requested_plan;

        $clinic->plan = $plan;
        $clinic->max_users = Clinic::PLAN_USER_LIMITS[$plan] ?? $clinic->max_users;
        $clinic->save();
    }

    private function createCheckoutForUpgrade(SubscriptionRequest $request): array
    {
        $clinic = $request->clinic;

        $checkoutData = $this->checkoutService->createCheckout([
            'payment_id' => null,
            'amount' => $this->getPlanAmount($request->requested_plan),
            'currency' => 'EUR',
            'clinic_id' => $clinic->id,
            'email' => $clinic->email,
            'plan' => $request->requested_plan,
            'stripe_product_id' => config('services.stripe.upgrade_products.' . $request->requested_plan),
            'metadata' => [
                'clinic_id' => $clinic->id,
                'subscription_request_id' => $request->id,
                'plan' => $request->requested_plan,
            ],
        ]);

        return $checkoutData;
    }

    private function getPlanAmount(string $plan): int
    {
        $pricing = config('pricing', []);
        $planConfig = $pricing[$plan] ?? [];
        return $planConfig['price'] ?? 2900;
    }

    private function shouldAutoCompleteForPaidBasic(SubscriptionRequest $request): bool
    {
        $clinic = $request->clinic;

        return $request->current_plan === 'basic'
            && $clinic
            && $clinic->isSubscribed()
            && ! $clinic->isTrialActive();
    }
}