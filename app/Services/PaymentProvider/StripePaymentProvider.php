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

    public function previewUpgrade(array $data): array
    {
        $clinic = $data['clinic'];
        $currentPlan = $data['current_plan'];
        $newPlan = $data['new_plan'];
        $subscriptionId = $data['subscription_reference'] ?? null;

        $currentPlanPrice = $this->getPlanPrice($currentPlan);
        $newPlanPrice = $this->getPlanPrice($newPlan);
        $currency = 'EUR';

        if ($subscriptionId && $clinic->stripe_id) {
            try {
                $stripeSub = $this->stripe->subscriptions->retrieve($subscriptionId);
                $subscriptionItemId = $stripeSub->items->data[0]->id;
                $newPriceId = $this->resolvePriceIdForPlan($newPlan);

                $upcoming = $this->stripe->invoices->upcoming([
                    'customer' => $clinic->stripe_id,
                    'subscription' => $subscriptionId,
                    'subscription_items' => [[
                        'id' => $subscriptionItemId,
                        'price' => $newPriceId,
                    ]],
                    'subscription_proration_behavior' => 'always_invoice',
                    'subscription_billing_cycle_anchor' => 'now',
                ]);

                $amountDue = (int) ($upcoming->amount_due ?? 0);
                $nextBillingDate = isset($upcoming->next_payment_attempt)
                    ? date('c', $upcoming->next_payment_attempt)
                    : null;

                $details = [];
                foreach ($upcoming->lines->data ?? [] as $line) {
                    $details[] = [
                        'description' => $line->description ?? $line->plan?->nickname ?? 'Line item',
                        'amount' => (int) ($line->amount ?? 0),
                    ];
                }

                $creditAmount = 0;
                $proratedNewCost = 0;
                foreach ($details as $d) {
                    if ($d['amount'] < 0) {
                        $creditAmount += abs($d['amount']);
                    } elseif ($d['amount'] > 0) {
                        $proratedNewCost += $d['amount'];
                    }
                }

                return [
                    'success' => true,
                    'has_existing_subscription' => true,
                    'current_plan' => ['name' => ucfirst($currentPlan), 'amount' => $currentPlanPrice],
                    'new_plan' => ['name' => ucfirst($newPlan), 'amount' => $newPlanPrice],
                    'credit_for_unused_days' => $creditAmount,
                    'prorated_new_plan_cost' => $proratedNewCost,
                    'amount_due_now' => $amountDue,
                    'next_billing_date' => $nextBillingDate,
                    'next_billing_amount' => $newPlanPrice,
                    'currency' => $currency,
                    'details' => $details,
                ];
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('stripe.preview_upgrade.fallback', [
                    'clinic_id' => $clinic->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->manualPreview($currentPlan, $newPlan, $currentPlanPrice, $newPlanPrice, $currency, $clinic);
    }

    public function upgradeSubscription(array $data): array
    {
        $clinic = $data['clinic'];
        $newPlan = $data['new_plan'];
        $subscriptionId = $data['subscription_reference'] ?? null;

        if (! $subscriptionId) {
            $checkout = $this->createCheckout([
                'payment_id' => null,
                'amount' => $this->getPlanPrice($newPlan),
                'currency' => 'EUR',
                'clinic_id' => $clinic->id,
                'email' => $clinic->email,
                'plan' => $newPlan,
                'stripe_product_id' => config('services.stripe.upgrade_products.' . $newPlan),
                'metadata' => $data['metadata'] ?? [],
            ]);

            return [
                'success' => true,
                'action' => 'checkout_required',
                'amount_charged' => 0,
                'checkout_url' => $checkout['checkout_url'],
                'provider_ref' => $checkout['provider_ref'],
                'invoice_id' => null,
            ];
        }

        $priceId = $this->resolvePriceIdForPlan($newPlan);
        $stripeSub = $this->stripe->subscriptions->retrieve($subscriptionId);
        $subscriptionItemId = $stripeSub->items->data[0]->id;

        $updatedSub = $this->stripe->subscriptions->update($subscriptionId, [
            'items' => [[
                'id' => $subscriptionItemId,
                'price' => $priceId,
            ]],
            'proration_behavior' => 'always_invoice',
            'billing_cycle_anchor' => 'now',
            'metadata' => array_merge($data['metadata'] ?? [], [
                'upgrade_from' => $data['current_plan'] ?? '',
                'upgrade_to' => $newPlan,
            ]),
        ]);

        $amountCharged = 0;
        $invoiceId = null;
        $latestInvoiceId = $updatedSub->latest_invoice ?? null;
        if ($latestInvoiceId) {
            try {
                $invoice = $this->stripe->invoices->retrieve($latestInvoiceId);
                $amountCharged = (int) ($invoice->amount_due ?? 0);
                $invoiceId = $invoice->id;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('stripe.upgrade.invoice_retrieve_failed', [
                    'invoice_id' => $latestInvoiceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'action' => 'upgraded',
            'amount_charged' => $amountCharged,
            'checkout_url' => null,
            'provider_ref' => $subscriptionId,
            'invoice_id' => $invoiceId,
        ];
    }

    private function manualPreview(string $currentPlan, string $newPlan, int $currentPrice, int $newPrice, string $currency, $clinic): array
    {
        $subscription = $clinic->currentSubscription();
        $periodEnd = $subscription?->current_period_end ?? $clinic->trial_ends_at ?? now()->addMonth();
        $now = now();

        $totalDays = (int) $now->diffInDays($periodEnd) + 1;
        $daysRemaining = max((int) $now->diffInDays($periodEnd), 0);
        $dayFraction = $totalDays > 0 ? $daysRemaining / $totalDays : 0;

        $credit = (int) round($currentPrice * $dayFraction * 100);
        $amountDue = max($newPrice * 100 - $credit, 0);

        return [
            'success' => true,
            'has_existing_subscription' => false,
            'current_plan' => ['name' => ucfirst($currentPlan), 'amount' => $currentPrice * 100],
            'new_plan' => ['name' => ucfirst($newPlan), 'amount' => $newPrice * 100],
            'credit_for_unused_days' => $credit,
            'prorated_new_plan_cost' => $newPrice * 100,
            'amount_due_now' => $amountDue,
            'next_billing_date' => $now->copy()->addMonth()->toIso8601String(),
            'next_billing_amount' => $newPrice * 100,
            'currency' => $currency,
            'details' => [
                ['description' => 'Crédito por días no usados de ' . ucfirst($currentPlan), 'amount' => -$credit],
                ['description' => 'Nuevo plan ' . ucfirst($newPlan) . ' (precio completo)', 'amount' => $newPrice * 100],
                ['description' => 'Total a pagar hoy', 'amount' => $amountDue],
            ],
        ];
    }

    private function getPlanPrice(string $plan): int
    {
        $pricing = config('pricing', []);
        $planConfig = $pricing[$plan] ?? [];
        return (int) ($planConfig['price'] ?? 2900);
    }

    private function resolvePriceIdForPlan(string $plan): string
    {
        $productId = config('services.stripe.upgrade_products.' . $plan);
        if ($productId) {
            $resolved = $this->resolvePriceIdFromProduct($productId);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $defaultPriceId = trim((string) config('services.stripe.price_id'));
        if ($defaultPriceId !== '') {
            return $defaultPriceId;
        }

        throw new \RuntimeException('No se pudo resolver un price ID para el plan: ' . $plan);
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
