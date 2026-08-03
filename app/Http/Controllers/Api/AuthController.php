<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Clinic;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Services\Auth\PasswordRecoveryLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly PasswordRecoveryLimiter $passwordRecoveryLimiter)
    {
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Log::warning('auth.login.failed', [
                'event' => 'auth.login',
                'result' => 'failed',
                'reason' => 'invalid_credentials',
                'user_id' => $user?->id,
                'clinic_id' => $user?->clinic_id,
                'email_domain' => $this->extractEmailDomain((string) ($data['email'] ?? '')),
                'ip' => $request->ip(),
                'user_agent' => $this->trimUserAgent((string) $request->userAgent()),
            ]);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales no son válidas.',
            ]);
        }

        if (! $user->email_verified_at) {
            Log::warning('auth.login.failed', [
                'event' => 'auth.login',
                'result' => 'failed',
                'reason' => 'email_not_verified',
                'user_id' => $user->id,
                'clinic_id' => $user->clinic_id,
                'ip' => $request->ip(),
                'user_agent' => $this->trimUserAgent((string) $request->userAgent()),
            ]);

            return response()->json([
                'message' => 'Debes activar tu cuenta desde el correo antes de iniciar sesión.',
            ], 403);
        }

        if ($user->clinic && $user->clinic->isSuspended()) {
            Log::warning('auth.login.failed', [
                'event' => 'auth.login',
                'result' => 'failed',
                'reason' => 'clinic_suspended',
                'user_id' => $user->id,
                'clinic_id' => $user->clinic_id,
                'ip' => $request->ip(),
                'user_agent' => $this->trimUserAgent((string) $request->userAgent()),
            ]);

            return response()->json([
                'message' => 'Por el momento tu cuenta está suspendida. Contacta con Irison para más información.',
                'code' => 'CLINIC_SUSPENDED',
            ], 403);
        }

        $this->passwordRecoveryLimiter->reset((string) $user->email);

        $token = $user->createToken('spa')->plainTextToken;
        $user->forceFill(['last_login_at' => now()])->save();
        Clinic::withoutGlobalScopes()->where('id', $user->clinic_id)->update(['last_activity_at' => now()]);

        ActivityLogger::log(
            tenantId: (int) $user->clinic_id,
            userId: (int) $user->id,
            event: 'login',
            description: 'Inicio de sesion exitoso',
            metadata: [
                'channel' => 'spa',
            ],
            ip: $request->ip(),
        );

        $this->pruneLoginLogs((int) $user->clinic_id, (int) $user->id);

        Log::info('auth.login.success', [
            'event' => 'auth.login',
            'result' => 'success',
            'user_id' => $user->id,
            'clinic_id' => $user->clinic_id,
            'ip' => $request->ip(),
            'user_agent' => $this->trimUserAgent((string) $request->userAgent()),
        ]);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    private function extractEmailDomain(string $email): ?string
    {
        $normalized = trim(strtolower($email));
        if ($normalized === '' || ! str_contains($normalized, '@')) {
            return null;
        }

        return substr($normalized, strpos($normalized, '@') + 1) ?: null;
    }

    private function trimUserAgent(string $userAgent): string
    {
        $trimmed = trim($userAgent);
        if ($trimmed === '') {
            return 'unknown';
        }

        return mb_substr($trimmed, 0, 180);
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

    private function pruneLoginLogs(int $tenantId, int $userId): void
    {
        try {
            $recentIds = ActivityLog::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('event', 'login')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->take(3)
                ->pluck('id');

            ActivityLog::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('event', 'login')
                ->whereNotIn('id', $recentIds)
                ->delete();
        } catch (\Throwable) {
            // La poda nunca debe romper el flujo de login.
        }
    }
}
