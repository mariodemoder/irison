<?php

namespace App\Services\PaymentProvider;

interface PaymentProviderInterface
{
    /**
     * Create a checkout and return data needed by frontend.
     * @param array $data
     * @return array
     */
    public function createCheckout(array $data): array;

    /**
     * Handle webhook payload from provider.
     * @param array $payload
     * @return void
     */
    public function handleWebhook(array $payload): void;

    /**
     * Cancel an existing subscription in the payment provider.
     *
     * @param array $data
     * @return void
     */
    public function cancelSubscription(array $data): void;

    /**
     * Human-friendly provider name key
     * @return string
     */
    public function getName(): string;
}
