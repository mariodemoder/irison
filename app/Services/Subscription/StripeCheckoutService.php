<?php

namespace App\Services\Subscription;

use App\Services\PaymentProvider\Resolver;

class StripeCheckoutService
{
    public function __construct(
        private readonly Resolver $paymentProviderResolver,
    ) {}

    public function createCheckout(array $data): array
    {
        $provider = $this->paymentProviderResolver->resolve();
        $checkout = $provider->createCheckout($data);

        return [
            'session_id' => $checkout['provider_ref'],
            'url' => $checkout['url'] ?? $checkout['checkout_url'],
            'status' => $checkout['status'] ?? 'pending',
        ];
    }
}