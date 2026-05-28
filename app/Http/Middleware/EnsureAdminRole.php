<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $adminUser = $request->user('admin');

        if (! $adminUser) {
            abort(403, 'No autorizado.');
        }

        if ($adminUser->role === AdminUser::ROLE_SUPER_ADMIN) {
            return $next($request);
        }

        if (empty($roles) || ! in_array($adminUser->role, $roles, true)) {
            abort(403, 'No tienes permisos para esta acción.');
        }

        return $next($request);
    }
}
