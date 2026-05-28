<?php

namespace App\Http\Controllers\API;

use App\Mail\AccountActivationMail;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Clinic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class RegisterController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(
            [
                'name' => ['required', 'string', 'max:255'],
                'clinic_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users'],
                'password' => ['required', 'min:8'],
            ],
            [
                'email.unique' => 'El email ya está en uso.',
            ]
        );

        $clinic = Clinic::create([
            'name' => $data['clinic_name'],
            'legal_name' => $data['clinic_name'],
            'email' => $data['email'],
            'trial_ends_at' => now()->addDays(30),
            'subscription_status' => 'trial',
            'status' => 'trial',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => $data['name'],
            'email' => $data['email'],
            // The User model casts 'password' => 'hashed', so avoid double hashing here.
            'password' => $data['password'],
        ]);

        $activationUrl = URL::temporarySignedRoute(
            'api.register.activate',
            now()->addHours(24),
            ['user' => $user->id],
            ['url' => config('app.url')]
        );

        Mail::to($user->email)->send(new AccountActivationMail($user, $activationUrl));

        return response()->json([
            'message' => 'Cuenta creada. Revisa tu correo para activar el periodo de prueba.',
        ], 201);
    }
}
