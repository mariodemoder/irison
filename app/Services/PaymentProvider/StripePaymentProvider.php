<?php

namespace App\Services\PaymentProvider;

use Stripe\Stripe;
use Stripe\StripeClient;

class StripePaymentProvider implements PaymentProviderInterface
{
    private StripeClient $stripe;

    public function __construct()
    {
        $caBundlePath = config('services.stripe.ca_bundle')
            ?: ini_get('curl.cainfo')
            ?: base_path('vendor/stripe/stripe-php/data/ca-certificates.crt');

        if (is_string($caBundlePath) && $caBundlePath !== '' && is_file($caBundlePath)) {
            $normalizedPath = str_replace('\\', '/', $caBundlePath);
            Stripe::setCABundlePath($normalizedPath);

            // Compatibilidad extra para runtimes en Windows y procesos CLI
            putenv('SSL_CERT_FILE=' . $normalizedPath);
            putenv('CURL_CA_BUNDLE=' . $normalizedPath);
        }

        // Solo para pruebas locales si existe inspeccion TLS en red corporativa/antivirus
        if (! config('services.stripe.verify_ssl', true)) {
            Stripe::setVerifySslCerts(false);
        }

        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createCheckout(array $data): array
    {
        $successUrl = config('app.url') . '/billing/required?checkout=success&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl  = config('app.url') . '/billing/required?checkout=cancel';
        $priceId = $this->resolvePriceIdForCheckout($data);

        $params = [
            'mode'       => 'subscription',
            'line_items' => [[
                'price'    => $priceId,
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
            'metadata'    => [
                'payment_id' => $data['payment_id'] ?? null,
                'clinic_id'  => $data['clinic_id']  ?? null,
            ],
        ];

        // Rellenar email del cliente para que el webhook pueda localizar la clínica
        if (! empty($data['email'])) {
            $params['customer_email'] = $data['email'];
        }

        $session = $this->stripe->checkout->sessions->create($params);

        return [
            'checkout_url' => $session->url,
            'provider_ref' => $session->id,
        ];
    }

    public function handleWebhook(array $payload): void
    {
        // Los eventos de Stripe se procesan en StripeWebhookController (/api/stripe/webhook)
        // Este método existe para cumplir la interfaz; no se usa con este provider.
    }
    
    public function cancelSubscription(array $data): void
    {
        $subscriptionId = (string) ($data['stripe_subscription_id'] ?? '');
        
        if ($subscriptionId === '') {
            throw new \InvalidArgumentException('No hay una suscripción de Stripe para cancelar');
        }
        
        $this->stripe->subscriptions->cancel($subscriptionId, []);
    }

    public function getName(): string
    {
        return 'stripe';
    }

    private function resolvePriceIdForCheckout(array $data): string
    {
        $explicitPriceId = trim((string) ($data['price_id'] ?? ''));
        if ($explicitPriceId !== '') {
            return $explicitPriceId;
        }

        $productId = trim((string) ($data['stripe_product_id'] ?? ''));
        if ($productId !== '') {
            $resolved = $this->resolvePriceIdFromProduct($productId);
            if ($resolved !== null) {
                return $resolved;
            }

            throw new \RuntimeException('No se pudo resolver un price activo para el producto Stripe: ' . $productId);
        }

        $defaultPriceId = trim((string) config('services.stripe.price_id'));
        if ($defaultPriceId === '') {
            throw new \RuntimeException('No hay STRIPE_PRICE_ID configurado para crear el checkout');
        }

        return $defaultPriceId;
    }

    private function resolvePriceIdFromProduct(string $productId): ?string
    {
        $product = $this->stripe->products->retrieve($productId, []);

        $defaultPrice = $product->default_price ?? null;
        if (is_string($defaultPrice) && trim($defaultPrice) !== '') {
            return $defaultPrice;
        }

        if (is_object($defaultPrice) && ! empty($defaultPrice->id)) {
            return (string) $defaultPrice->id;
        }

        $prices = $this->stripe->prices->all([
            'product' => $productId,
            'active' => true,
            'limit' => 10,
        ]);

        foreach ($prices->data as $price) {
            if (! empty($price->recurring) && ! empty($price->id)) {
                return (string) $price->id;
            }
        }

        return null;
    }
}
