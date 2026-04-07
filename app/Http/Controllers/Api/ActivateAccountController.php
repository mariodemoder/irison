<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ActivateAccountController extends Controller
{
    public function __invoke(Request $request, User $user)
    {
        $frontendUrl = rtrim((string) env('APP_FRONTEND_URL', 'http://localhost:5173'), '/');

        if (! $request->hasValidSignature()) {
            return redirect()->to($frontendUrl . '/login?activation=invalid');
        }

        $alreadyVerified = $user->email_verified_at !== null;

        if (! $alreadyVerified) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        $clinic = $user->clinic;
        if ($clinic) {
            $current = $clinic->saasSubscriptions()->orderByDesc('id')->first();

            $mustStartTrial = true;
            if ($current && $current->trial_ends_at instanceof Carbon && $current->trial_ends_at->isFuture()) {
                $mustStartTrial = false;
            }

            if ($mustStartTrial) {
                $trialEnds = now()->addDays(30);

                if (! $current) {
                    Subscription::create([
                        'clinic_id' => $clinic->id,
                        'status' => 'trial',
                        'trial_ends_at' => $trialEnds,
                        'current_period_end' => null,
                    ]);
                } else {
                    $current->status = 'trial';
                    $current->trial_ends_at = $trialEnds;
                    $current->current_period_end = null;
                    $current->save();
                }

                $clinic->trial_ends_at = $trialEnds;
                $clinic->save();
            }
        }

        $query = $alreadyVerified ? 'already' : 'success';
        return redirect()->to($frontendUrl . '/login?activation=' . $query);
    }
}
