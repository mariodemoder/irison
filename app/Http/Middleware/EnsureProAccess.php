<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Clinic;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe el acceso a funcionalidades PRO (rol de recepción, finanzas,
 * control de gastos, dashboard de beneficios) a clínicas con plan PRO/Enterprise.
 */
class EnsureProAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $clinic = $request->user()?->clinic;

        if (! $clinic || ! $clinic->hasProFeatures()) {
            abort(403, 'Esta funcionalidad está disponible en los planes PRO o Enterprise.');
        }

        return $next($request);
    }
}