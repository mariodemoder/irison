<?php

namespace App\Payment;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class FakePaymentProvider implements PaymentProviderInterface
{
    public function createCheckout(array $data): array
    {
        $ref = 'fake_' . Str::random(12);

        // Dev: apuntamos a una ruta que simula el final del checkout
        $checkoutUrl = URL::to('/billing/fake/complete?ref=' . $ref);

        return [
            'checkout_url' => $checkoutUrl,
            'provider_ref' => $ref,
        ];
    }

    public function handleWebhook(array $payload): void
    {
        // Para fake, no-op; el controlador webhook realizará la lógica
    }

    public function getName(): string
    {
        return 'fake';
    }
}
