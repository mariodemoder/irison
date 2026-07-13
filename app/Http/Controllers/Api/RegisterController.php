<?php

namespace App\Http\Controllers\API;

use App\Mail\AccountActivationMail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use App\Models\Clinic;
use App\Models\Profile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request)
    {
        $data = $request->validated();

        $clinic = Clinic::create([
            'name' => $data['clinic_name'],
            'legal_name' => $data['clinic_name'],
            'email' => $data['email'],
            'nif' => $data['nif'],
            'zip' => $data['zip'],
            'phone' => $data['phone'],
            'plan' => 'basic',
            'max_users' => Clinic::PLAN_USER_LIMITS['basic'],
            'trial_ends_at' => now()->addDays(30),
            'subscription_status' => 'trial',
            'status' => 'trial',
        ]);

        $adminProfile = Profile::where('slug', 'admin')->first();

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => $data['name'],
            'email' => $data['email'],
            // The User model casts 'password' => 'hashed', so avoid double hashing here.
            'password' => $data['password'],
            'profile_id' => $adminProfile?->id,
        ]);

        $activationUrl = URL::temporarySignedRoute(
            'api.register.activate',
            now()->addHours(24),
            ['user' => $user->id],
            ['url' => config('app.url')]
        );

        Mail::to($user->email)->queue(new AccountActivationMail($user, $activationUrl));

        return response()->json([
            'message' => 'Cuenta creada. Revisa tu correo para activar el periodo de prueba.',
        ], 201);
    }
}
