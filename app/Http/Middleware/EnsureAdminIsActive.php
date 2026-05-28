<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminUser = $request->user('admin');

        if (! $adminUser || ! $adminUser->is_active) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('backoffice.login')
                ->withErrors(['email' => 'Tu cuenta interna está inactiva.']);
        }

        return $next($request);
    }
}
