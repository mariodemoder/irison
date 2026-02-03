<?php

namespace App\Services\PaymentProvider;

class Resolver
{
    public static function resolve(): PaymentProviderInterface
    {
        $provider = config('billing.provider', 'fake');
        return match ($provider) {
            'fake' => new FakePaymentProvider(),
            // 'stripe' => new StripePaymentProvider(),
            default => new FakePaymentProvider(),
        };
    }
}
