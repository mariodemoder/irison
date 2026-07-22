<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\PasswordRecoveryLimiter;
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
    public function __construct(private readonly PasswordRecoveryLimiter $passwordRecoveryLimiter) {}

    public function sendResetLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $this->passwordRecoveryLimiter->normalizeEmail((string) $data['email']);

        if (! $this->passwordRecoveryLimiter->canSend($email)) {
            return response()->json([
                'code' => 'PASSWORD_RESET_LIMIT_REACHED',
                'message' => 'Pongase en contacto con el equipo tecnico de Irison.',
            ], 429);
        }

        $status = Password::sendResetLink([
            'email' => $email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->passwordRecoveryLimiter->markSent($email);
        }

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

                if (! $user->email_verified_at) {
                    $attributes['email_verified_at'] = now();
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
