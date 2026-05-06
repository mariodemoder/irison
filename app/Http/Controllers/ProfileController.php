<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $clinic = null;
        $status = 'blocked';
        $trial_ends_at = null;

        if ($user->clinic) {
            $clinic = $user->clinic;
            $trial_ends_at = $clinic->trial_ends_at ?? null;
                  $clinicStatus = strtolower(trim((string) ($clinic->subscription_status ?? 'inactive')));
                  if ($clinicStatus === 'active') {
                      $status = 'active';
                  } elseif ($clinicStatus === 'trial' && $clinic->isTrialActive()) {
                      $status = 'trial';
                  } else {
                      $status = 'blocked';
                  }
        }

        return view('profile.edit', [
            'user' => $user,
            'clinic' => $clinic,
            'status' => $status,
            'trial_ends_at' => $trial_ends_at,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Desactivar la clínica asociada 
        if ($user->clinic_id) {
                \App\Models\Clinic::where('id', $user->clinic_id) ->update(['is_active' => 0]); 
                }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
