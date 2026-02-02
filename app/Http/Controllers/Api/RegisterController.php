<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Clinic;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisterController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'clinic_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'min:8'],
            'trial_ends_at' => ['nullable', 'datetime'],
            
        ]);

        // Define trial end date once and store it on the subscription.
        $trialEnds = Carbon::now()->addDays(30);

        $clinic = Clinic::create([
            'name' => $data['clinic_name'],
            'legal_name' => $data['clinic_name'],
            'email' => $data['email'],
            'trial_ends_at' => $trialEnds,
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Create initial trial subscription for the clinic
        Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'trial',
            'trial_ends_at' => $trialEnds,
            'current_period_end' => null,
        ]);

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }
}
