<?php

namespace App\Services\PaymentProvider;

use Illuminate\Support\Facades\URL;

class FakePaymentProvider implements PaymentProviderInterface
{
    public function createCheckout(array $data): array
    {
        return [
            'checkout_url' => route('billing.fake.success'),
            'provider_ref' => 'fake_' . uniqid(),
        ];
    }

    public function handleWebhook(array $payload): void
    {
        // Simulate confirming a payment; in real provider we'd verify signatures
        // This could dispatch events or update Payment models
    }

    public function getName(): string
    {
        return 'fake';
    }
}
