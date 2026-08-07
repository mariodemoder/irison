<?php

namespace Modules\Subscriptions\Infrastructure\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Subscriptions\Domain\Contracts\PaymentProviderInterface;
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
            putenv('SSL_CERT_FILE='.$normalizedPath);
            putenv('CURL_CA_BUNDLE='.$normalizedPath);
        }

        // Solo para pruebas locales si existe inspeccion TLS en red corporativa/antivirus
        if (! config('services.stripe.verify_ssl', true)) {
            Stripe::setVerifySslCerts(false);
        }

        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createCheckout(array $data): array
    {
        $successUrl = $data['success_url']
            ?? (config('app.url').'/billing/required?checkout=success&session_id={CHECKOUT_SESSION_ID}');
        $cancelUrl = config('app.url').'/billing/required?checkout=cancel';
        $priceId = $this->resolvePriceIdForCheckout($data);

        $params = [
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'payment_id' => $data['payment_id'] ?? null,
                'clinic_id' => $data['clinic_id'] ?? null,
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

    public function confirmCheckout(array $data): array
    {
        $sessionId = (string) ($data['session_id'] ?? '');
        if ($sessionId === '') {
            return ['status' => 'pending', 'message' => 'No se encontro una sesion pendiente para validar'];
        }

        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId, []);
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo validar la sesion de pago: '.$e->getMessage(), 0, $e);
        }

        $isPaid = (($session->payment_status ?? null) === 'paid')
            || (($session->status ?? null) === 'complete');

        if (! $isPaid) {
            return [
                'status' => 'pending',
                'message' => 'El pago todavia no figura como completado en el proveedor',
            ];
        }

        $resolvedMethod = 'transaction';
        $methodTypes = $session->payment_method_types ?? [];
        if (is_array($methodTypes) && ! empty($methodTypes[0])) {
            $resolvedMethod = strtolower((string) $methodTypes[0]);
        }

        return [
            'status' => 'paid',
            'payment_method' => $resolvedMethod,
            'customer' => $session->customer ?? null,
            'subscription' => $session->subscription ?? null,
            'payment_id' => $session->metadata->payment_id ?? null,
            'invoice_id' => ! empty($session->invoice) ? (string) $session->invoice : null,
            'invoice_url' => $this->resolveInvoiceUrl(! empty($session->invoice) ? (string) $session->invoice : null),
        ];
    }

    public function handleWebhook(Request $request): void
    {
        app(StripeWebhookHandler::class)->handleRequest($request);
    }

    public function resolveInvoiceUrl(?string $invoiceId): ?string
    {
        if (empty($invoiceId)) {
            return null;
        }

        try {
            $invoice = $this->stripe->invoices->retrieve($invoiceId);

            return $invoice->hosted_invoice_url ?? null;
        } catch (\Throwable $e) {
            Log::warning('stripe.resolve_invoice_url_failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function listInvoices(array $data): array
    {
        $customerId = (string) ($data['customer_id'] ?? $data['stripe_id'] ?? '');
        if ($customerId === '') {
            return [];
        }

        try {
            $invoices = $this->stripe->invoices->all([
                'customer' => $customerId,
                'limit' => (int) ($data['limit'] ?? 10),
            ]);
        } catch (\Throwable $e) {
            Log::warning('stripe.list_invoices_failed', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $result = [];
        foreach ($invoices->data ?? [] as $invoice) {
            $result[] = [
                'id' => (string) ($invoice->id ?? ''),
                'status' => (string) ($invoice->status ?? ''),
                'amount_due' => (int) ($invoice->amount_due ?? 0),
                'currency' => strtoupper((string) ($invoice->currency ?? 'EUR')),
                'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                'created' => (int) ($invoice->created ?? 0),
            ];
        }

        return $result;
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
                'price_id' => $this->resolvePriceIdForPlan($newPlan),
                'metadata' => $data['metadata'] ?? [],
                'success_url' => config('app.url').'/settings/subscription?upgraded=1&session_id={CHECKOUT_SESSION_ID}',
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
        $invoiceUrl = null;
        $latestInvoiceId = $updatedSub->latest_invoice ?? null;
        if ($latestInvoiceId) {
            try {
                $invoice = $this->stripe->invoices->retrieve($latestInvoiceId);
                $amountCharged = (int) ($invoice->amount_due ?? 0);
                $invoiceId = $invoice->id;
                $invoiceUrl = $invoice->hosted_invoice_url ?? null;
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
            'invoice_url' => $invoiceUrl,
        ];
    }

    private function manualPreview(string $currentPlan, string $newPlan, int $currentPrice, int $newPrice, string $currency, $clinic): array
    {
        $subscription = $clinic->currentSubscription();
        $isTrial = in_array($subscription?->status ?? '', ['trial', 'trial_read_only'], true);

        // Trial: nunca pagó, no hay crédito. Cobra el precio completo del nuevo plan.
        if ($isTrial) {
            return [
                'success' => true,
                'has_existing_subscription' => false,
                'current_plan' => ['name' => ucfirst($currentPlan), 'amount' => 0],
                'new_plan' => ['name' => ucfirst($newPlan), 'amount' => $newPrice * 100],
                'credit_for_unused_days' => 0,
                'prorated_new_plan_cost' => $newPrice * 100,
                'amount_due_now' => $newPrice * 100,
                'next_billing_date' => now()->copy()->addMonth()->toIso8601String(),
                'next_billing_amount' => $newPrice * 100,
                'currency' => $currency,
                'details' => [
                    ['description' => 'Plan '.ucfirst($newPlan).' (precio completo)', 'amount' => $newPrice * 100],
                    ['description' => 'Total a pagar hoy', 'amount' => $newPrice * 100],
                ],
            ];
        }

        // Basic paid → PRO: cálculo normal con prorrateo
        $periodEnd = $subscription?->current_period_end ?? now()->addMonth();
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
                ['description' => 'Crédito por días no usados de '.ucfirst($currentPlan), 'amount' => -$credit],
                ['description' => 'Nuevo plan '.ucfirst($newPlan).' (precio completo)', 'amount' => $newPrice * 100],
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
        $productId = config('services.stripe.upgrade_products.'.$plan);
        if ($productId) {
            $resolved = $this->resolvePriceIdFromProduct($productId);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Solo usar el price_id por defecto (Basic) si el plan es basic
        if ($plan === 'basic') {
            $defaultPriceId = trim((string) config('services.stripe.price_id'));
            if ($defaultPriceId !== '') {
                return $defaultPriceId;
            }
        }

        throw new \RuntimeException('No se pudo resolver un price ID para el plan: '.$plan.'. Verifica que el producto tenga un default_price configurado en Stripe.');
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

            throw new \RuntimeException('No se pudo resolver un price activo para el producto Stripe: '.$productId);
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
