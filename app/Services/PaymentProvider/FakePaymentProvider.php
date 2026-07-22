<?php

namespace App\Services\PaymentProvider;

class FakePaymentProvider implements PaymentProviderInterface
{
    public function createCheckout(array $data): array
    {
        return [
            'checkout_url' => route('billing.fake.success'),
            'provider_ref' => 'fake_'.uniqid(),
        ];
    }

    public function handleWebhook(array $payload): void
    {
        // Simulate confirming a payment; in real provider we'd verify signatures
        // This could dispatch events or update Payment models
    }

    public function cancelSubscription(array $data): void
    {
        // No external cancellation is needed for fake provider.
    }

    public function getName(): string
    {
        return 'fake';
    }

    public function previewUpgrade(array $data): array
    {
        $currentPlan = $data['current_plan'];
        $newPlan = $data['new_plan'];
        $clinic = $data['clinic'];

        $currentPrice = $this->getPlanPrice($currentPlan);
        $newPrice = $this->getPlanPrice($newPlan);
        $currency = 'EUR';

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

    public function upgradeSubscription(array $data): array
    {
        $clinic = $data['clinic'] ?? null;
        $isPaidActive = $clinic && $clinic->isSubscribed() && ! $clinic->isTrialActive();

        if (! $isPaidActive) {
            $checkout = $this->createCheckout([
                'amount' => $this->getPlanPrice($data['new_plan'] ?? 'basic'),
                'currency' => 'EUR',
                'clinic_id' => $clinic?->id,
            ]);

            return [
                'success' => true,
                'action' => 'checkout_required',
                'amount_charged' => 0,
                'checkout_url' => $checkout['checkout_url'],
                'provider_ref' => $checkout['provider_ref'],
                'invoice_id' => null,
                'invoice_url' => null,
            ];
        }

        return [
            'success' => true,
            'action' => 'upgraded',
            'amount_charged' => 0,
            'checkout_url' => null,
            'provider_ref' => 'fake_upgrade_'.uniqid(),
            'invoice_id' => null,
            'invoice_url' => null,
        ];
    }

    private function getPlanPrice(string $plan): int
    {
        $pricing = config('pricing', []);
        $planConfig = $pricing[$plan] ?? [];

        return (int) ($planConfig['price'] ?? 2900);
    }
}
