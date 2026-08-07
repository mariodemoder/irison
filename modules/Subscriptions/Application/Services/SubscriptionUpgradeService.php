<?php

namespace Modules\Subscriptions\Application\Services;

use App\Models\BillingPayment;
use App\Models\Clinic;
use App\Models\SubscriptionRequest;
use Modules\Subscriptions\Domain\Events\CheckoutCreated;
use Modules\Subscriptions\Domain\Events\PaymentCompleted as PaymentCompletedEvent;
use Modules\Subscriptions\Domain\Events\SubscriptionUpgraded as SubscriptionUpgradedEvent;
use Modules\Subscriptions\Infrastructure\Payment\Resolver;

class SubscriptionUpgradeService
{
    public function __construct(
        private readonly SubscriptionRequestService $requestService,
        private readonly Resolver $paymentProviderResolver,
    ) {}

    public function approveAndGenerateCheckout(
        SubscriptionRequest $request,
        ?int $reviewedBy = null,
        ?string $reviewerComments = null,
    ): array {
        $this->requestService->approveRequest($request, $reviewedBy, $reviewerComments);

        $provider = $this->paymentProviderResolver->resolve();
        $clinic = $request->clinic;
        $subscription = $clinic->currentSubscription();

        $result = $provider->upgradeSubscription([
            'clinic' => $clinic,
            'current_plan' => $request->current_plan,
            'new_plan' => $request->requested_plan,
            'subscription_reference' => $subscription?->stripe_subscription_id,
            'metadata' => [
                'clinic_id' => $clinic->id,
                'subscription_request_id' => $request->id,
                'plan' => $request->requested_plan,
            ],
        ]);

        if (! $result['success']) {
            throw new \RuntimeException('El proveedor de pago no pudo procesar el upgrade');
        }

        match ($result['action']) {
            'upgraded' => $this->handleUpgraded($request, $result, $provider),
            'checkout_required' => $this->handleCheckoutRequired($request, $result),
            default => throw new \RuntimeException('Acción de upgrade desconocida: '.($result['action'] ?? 'null')),
        };

        return $result;
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
        $clinic->status = 'active';
        $clinic->churned_at = null;
        $clinic->save();
    }

    public static function getPlanPrice(string $plan): int
    {
        $pricing = config('pricing', []);
        $planConfig = $pricing[$plan] ?? [];

        return (int) ($planConfig['price'] ?? 2900);
    }

    private function handleUpgraded(SubscriptionRequest $request, array $result, $provider): void
    {
        BillingPayment::create([
            'clinic_id' => $request->clinic_id,
            'amount' => $result['amount_charged'],
            'currency' => 'EUR',
            'status' => 'paid',
            'provider' => $provider->getName(),
            'provider_ref' => $result['provider_ref'] ?? ('upgrade_'.$request->id),
            'method' => 'prorated_upgrade',
        ]);

        $this->handlePaymentCompleted($request, [
            'provider' => $provider->getName(),
            'payment_id' => null,
            'mode' => 'prorated_upgrade',
            'amount_charged' => $result['amount_charged'],
            'invoice_id' => $result['invoice_id'] ?? null,
        ]);
    }

    private function handleCheckoutRequired(SubscriptionRequest $request, array $result): void
    {
        $this->requestService->generateCheckoutUrl($request, [
            'session_id' => $result['provider_ref'],
            'url' => $result['checkout_url'],
        ]);

        event(new CheckoutCreated($request, $result));
    }
}
