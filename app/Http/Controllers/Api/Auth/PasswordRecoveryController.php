<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class PasswordRecoveryController extends Controller
{
    public function sendResetLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink([
            'email' => $data['email'],
        ]);

        // Respuesta neutral para no exponer si el email existe en el sistema.
        return response()->json([
            'message' => 'Si el email existe, te hemos enviado instrucciones para recuperar tu contrasena.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $data,
            function (User $user) use ($data): void {
                $attributes = [
                    'password' => Hash::make($data['password']),
                ];

                if (Schema::hasColumn($user->getTable(), 'remember_token')) {
                    $attributes['remember_token'] = Str::random(60);
                }

                $user->forceFill($attributes)->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        return response()->json([
            'message' => 'Contrasena actualizada correctamente. Ya puedes iniciar sesion.',
        ]);
    }
}
