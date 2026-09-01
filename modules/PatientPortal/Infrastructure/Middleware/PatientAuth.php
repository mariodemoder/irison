<?php

namespace Modules\PatientPortal\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PatientAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !($user instanceof \App\Models\Patient)) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Su cuenta no está activa. Contacte con la clínica.'], 403);
        }

        // Las clínicas sin slug de portal quedan fuera de todo el circuito del
        // portal del paciente (login, forgot, reset y endpoints autenticados).
        if (empty($user->clinic?->slug)) {
            return response()->json(['message' => 'El portal del paciente no está disponible para esta clínica.'], 403);
        }

        // Interruptor maestro: si el portal está desactivado a nivel de clínica,
        // se bloquea el acceso autenticado (defense-in-depth).
        if (! \Modules\PatientPortal\Infrastructure\Persistence\PatientPortalSettings::forClinic($user->clinic_id)->is_active) {
            return response()->json(['message' => 'El portal del paciente no está disponible para esta clínica.'], 403);
        }

        return $next($request);
    }
}
