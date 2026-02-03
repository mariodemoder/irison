<?php

namespace App\Payment;

class ProviderResolver
{
    public static function resolve(): PaymentProviderInterface
    {
        $provider = config('billing.provider', 'fake');

        return match ($provider) {
            'fake' => new FakePaymentProvider(),
            default => new FakePaymentProvider(),
        };
    }
}
