<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeController
{
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

        $payload = [
            'user' => $user,
            'clinic' => $clinic,
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
        ]);

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

        return response()->json([
            'user' => $user->fresh(),
            'clinic' => $clinic->fresh(),
            'message' => 'Datos actualizados',
        ]);
    }
}
