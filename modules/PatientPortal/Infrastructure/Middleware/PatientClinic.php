<?php

namespace Modules\PatientPortal\Infrastructure\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PatientClinic
{
    public function handle(Request $request, Closure $next): Response
    {
        $patient = $request->user();

        if (!$patient || !$patient->clinic_id) {
            return response()->json(['message' => 'Paciente sin clínica asignada.'], 403);
        }

        // Set active clinic context for tenant isolation
        app()->instance('activeClinicId', $patient->clinic_id);

        return $next($request);
    }
}
