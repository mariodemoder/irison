<?php

namespace App\Payment;

interface PaymentProviderInterface
{
    /**
     * Crear un checkout y devolver datos (no HTML)
     * @param array $data
     * @return array
     */
    public function createCheckout(array $data): array;

    /**
     * Manejar payload de webhook
     * @param array $payload
     * @return void
     */
    public function handleWebhook(array $payload): void;

    public function getName(): string;
}
