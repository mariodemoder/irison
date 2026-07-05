<?php

namespace App\Services\Subscription;

use App\Events\CheckoutCreated;
use App\Events\PaymentCompleted as PaymentCompletedEvent;
use App\Events\SubscriptionUpgraded as SubscriptionUpgradedEvent;
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
        $this->requestService->markAsPaid($request);
        $this->requestService->completeSubscription($request);
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
}