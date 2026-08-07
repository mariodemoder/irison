<?php

namespace Modules\Subscriptions\Infrastructure\Payment;

use Modules\Subscriptions\Domain\Contracts\PaymentProviderInterface;

class Resolver
{
    public static function resolve(): PaymentProviderInterface
    {
        $provider = config('billing.provider', 'fake');
        return match ($provider) {
            'fake'   => new FakePaymentProvider(),
            'stripe' => new StripePaymentProvider(),
            default  => new FakePaymentProvider(),
        };
    }

    /**
     * Resolve the provider that owns an existing subscription, so cancellations
     * and other provider-specific operations hit the right service even when
     * the configured provider changed after the subscription was created.
     *
     * @param  string|null  $subscriptionProvider  value of clinics.subscription_provider
     * @param  string|null  $subscriptionReference e.g. Stripe subscription id (sub_...)
     */
    public static function resolveForCancellation(?string $subscriptionProvider, ?string $subscriptionReference): PaymentProviderInterface
    {
        $providerName = strtolower(trim((string) $subscriptionProvider));
        $reference = trim((string) $subscriptionReference);

        $looksLikeStripe = $providerName === 'stripe' || ($reference !== '' && str_starts_with($reference, 'sub_'));

        if ($looksLikeStripe) {
            return new StripePaymentProvider();
        }

        return self::resolve();
    }
}
