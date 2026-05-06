<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son válidas.',
            ]);
        }

        if (! $user->email_verified_at) {
            return response()->json([
                'message' => 'Debes activar tu cuenta desde el correo antes de iniciar sesión.',
            ], 403);
        }

        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            // Revoke current access token (SPA single token)
            $token = $request->bearerToken();
            try {
                if ($request->user()->currentAccessToken()) {
                    $request->user()->currentAccessToken()->delete();
                }
            } catch (\Exception $e) {
                // fallback: delete all tokens
                try {
                    $user->tokens()->delete();
                } catch (\Exception $e) {
                    // ignore
                }
            }
        }

        return response()->json(['message' => 'Logged out'], 200);
    }
}
