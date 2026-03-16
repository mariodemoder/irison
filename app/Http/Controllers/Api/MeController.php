<?php

namespace App\Http\Controllers\Api;

use App\Models\BillingPayment;
use App\Services\Counters\CounterService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class MeController
{
    public function __construct(private readonly CounterService $counterService)
    {
    }

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $clinic = $user ? $user->clinic : null;

        $status = 'blocked';
        if ($clinic) {
            $status = match (true) {
                $clinic->isSubscribed() => 'active',
                $clinic->isTrialActive() => 'trial',
                default => 'blocked',
            };
        }

        $trialEnds = null;
        if ($clinic) {
            $sub = $clinic->currentSubscription();
            $trialEnds = $sub ? $sub->trial_ends_at : null;
        }

        if ($clinic) {
            $clinic->load('subscriptions');
        }

        $subscriptionPayments = [];
        if ($clinic && $status === 'active') {
            $subscriptionPayments = BillingPayment::query()
                ->where('clinic_id', (int) $clinic->id)
                ->whereIn('status', ['paid', 'completed'])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'counter', 'amount', 'currency', 'status', 'created_at'])
                ->map(function (BillingPayment $payment) {
                    return [
                        'id' => $payment->id,
                        'counter' => $payment->counter,
                        'amount' => (int) $payment->amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'created_at' => $payment->created_at,
                    ];
                })
                ->values()
                ->toArray();
        }

        $payload = [
            'user' => $user,
            'clinic' => $clinic,
            'counters' => $clinic ? $this->counterService->getProfileCounters((int) $clinic->id) : [],
            'subscription_payments' => $subscriptionPayments,
            'status' => $status,
            'trial_ends_at' => $trialEnds,
        ];

        if ($status === 'blocked') {
            $payload['code'] = 'SUBSCRIPTION_REQUIRED';
            $payload['message'] = 'Tu periodo de prueba ha finalizado';
        }

        return response()->json($payload);

    }

    public function update(Request $request)
    {
        $user = $request->user();
        $clinic = $user ? $user->clinic : null;

        if (!$user || !$clinic) {
            return response()->json([
                'message' => 'Usuario o clínica no disponible',
            ], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'clinic' => ['nullable', 'array'],
            'clinic.name' => ['nullable', 'string', 'max:255'],
            'clinic.nif' => ['nullable', 'string', 'max:50'],
            'clinic.address' => ['nullable', 'string', 'max:255'],
            'clinic.locality' => ['nullable', 'string', 'max:120'],
            'clinic.province' => ['nullable', 'string', 'max:120'],
            'clinic.country' => ['nullable', 'string', 'max:120'],
            'clinic.zip' => ['nullable', 'string', 'max:20'],
            'counters' => ['nullable', 'array'],
            'counters.*.table_type' => ['required', Rule::in(CounterService::TABLE_TYPES)],
            'counters.*.prefix' => ['required', 'string', 'min:1', 'max:4', 'regex:/^[A-Za-z0-9]+$/'],
            'counters.*.last_number' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $user, $clinic) {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            $clinicPayload = $data['clinic'] ?? [];
            $clinic->update([
                'name' => array_key_exists('name', $clinicPayload) ? $clinicPayload['name'] : $clinic->name,
                'nif' => $clinicPayload['nif'] ?? null,
                'address' => $clinicPayload['address'] ?? null,
                'locality' => $clinicPayload['locality'] ?? null,
                'province' => $clinicPayload['province'] ?? null,
                'country' => $clinicPayload['country'] ?? null,
                'zip' => $clinicPayload['zip'] ?? null,
            ]);

            if (!empty($data['counters']) && is_array($data['counters'])) {
                $this->counterService->upsertClinicCounters((int) $clinic->id, $data['counters']);
            }
        }, 3);

        return response()->json([
            'user' => $user->fresh(),
            'clinic' => $clinic->fresh(),
            'counters' => $this->counterService->getProfileCounters((int) $clinic->id),
            'message' => 'Datos actualizados',
        ]);
    }
}
