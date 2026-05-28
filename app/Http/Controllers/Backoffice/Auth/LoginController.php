<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('backoffice.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $adminUser = AdminUser::query()->where('email', $credentials['email'])->first();

        if (! $adminUser || ! $adminUser->is_active || ! Hash::check($credentials['password'], $adminUser->password)) {
            Log::warning('auth.admin.login.failed', [
                'event' => 'auth.admin.login',
                'result' => 'failed',
                'reason' => 'invalid_credentials_or_inactive',
                'admin_user_id' => $adminUser?->id,
                'role' => $adminUser?->role,
                'email_domain' => $this->extractEmailDomain((string) ($credentials['email'] ?? '')),
                'ip' => $request->ip(),
                'user_agent' => $this->trimUserAgent((string) $request->userAgent()),
            ]);

            throw ValidationException::withMessages([
                'email' => 'Credenciales inválidas o cuenta inactiva.',
            ]);
        }

        Auth::guard('admin')->login($adminUser, (bool) ($credentials['remember'] ?? false));
        $request->session()->regenerate();

        $adminUser->forceFill(['last_login_at' => now()])->save();

        Log::info('auth.admin.login.success', [
            'event' => 'auth.admin.login',
            'result' => 'success',
            'admin_user_id' => $adminUser->id,
            'role' => $adminUser->role,
            'ip' => $request->ip(),
            'user_agent' => $this->trimUserAgent((string) $request->userAgent()),
        ]);

        return redirect()->intended(route('backoffice.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $impersonation = (array) $request->session()->get('backoffice_impersonation', []);
        $tokenId = (int) ($impersonation['token_id'] ?? 0);
        if ($tokenId > 0) {
            \Illuminate\Support\Facades\DB::table('personal_access_tokens')->where('id', $tokenId)->delete();
        }

        $request->session()->forget('backoffice_impersonation');

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('backoffice.login');
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
}
