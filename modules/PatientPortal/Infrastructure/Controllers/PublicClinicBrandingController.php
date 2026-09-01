<?php

declare(strict_types=1);

namespace Modules\PatientPortal\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;

/**
 * Branding público de una clínica para las páginas guest del Portal del
 * Paciente (login, forgot/reset). Se resuelve por Clinic.slug porque esas
 * páginas aún no tienen sesión ni contexto multi-tenant.
 */
class PublicClinicBrandingController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $clinic = Clinic::withoutGlobalScopes()
            ->where('slug', $slug)
            ->first();

        if (! $clinic) {
            return response()->json(['message' => 'Clínica no encontrada.'], 404);
        }

        return response()->json([
            'name' => $clinic->name,
            'slug' => $clinic->slug,
            'logo_url' => $clinic->usesClinicBranding() && $clinic->hasClinicLogo()
                ? $clinic->clinicLogoUrl()
                : null,
        ]);
    }
}