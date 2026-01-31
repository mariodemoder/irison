<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class FakeSubscribeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->clinic) {
            return response()->json(['message' => 'No clinic assigned'], 403);
        }

        $clinic = $user->clinic;

        // Asignar directamente para evitar restricciones de fillable
        $clinic->subscribed_at = Carbon::now();
        $clinic->subscription_provider = 'fake';
        $clinic->subscription_reference = 'fake-' . uniqid();
        $clinic->save();

        // Refrescar modelo y devolver estado actualizado
        $clinic->refresh();

        $active = $clinic->isTrialActive() || $clinic->isSubscribed();

        return response()->json([
            'status' => 'ok',
            'clinic' => $clinic,
            'trial_active' => $active,
        ]);
    }
}
