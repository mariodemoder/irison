<?php

namespace Modules\PatientPortal\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PatientPortal\Application\Services\PatientAuthService;

class PatientAuthController extends Controller
{
    public function __construct(
        private PatientAuthService $authService
    ) {}

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'clinic' => 'nullable|string|max:160',
        ]);

        try {
            $result = $this->authService->login(
                $request->email,
                $request->password,
                $request->ip(),
                $request->userAgent(),
                $request->input('clinic')
            );

            return response()->json($result);
        } catch (\Exception $e) {
            $status = str_contains($e->getMessage(), 'no está activa') ? 403 : 401;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    public function me(Request $request)
    {
        return response()->json($this->authService->me($request->user()));
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'clinic' => 'nullable|string|max:160',
        ]);

        $this->authService->forgotPassword($request->email, $request->input('clinic'));

        // Always return neutral response
        return response()->json([
            'message' => 'Si el email existe, le hemos enviado instrucciones para restablecer su contraseña.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
            'clinic' => 'nullable|string|max:160',
        ]);

        try {
            $this->authService->resetPassword(
                $request->token,
                $request->email,
                $request->password,
                $request->input('clinic')
            );

            return response()->json(['message' => 'Contraseña actualizada correctamente. Ya puede iniciar sesión.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
