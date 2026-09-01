<?php

declare(strict_types=1);

namespace Modules\PatientPortal\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\PatientPortal\Infrastructure\Persistence\PatientPortalSettings;

/**
 * Configuración staff del Portal del Paciente.
 * Endpoint: /patient-portal/settings, /patient-portal/slug-check
 * Autorización: owner / admin / manager
 */
class PatientPortalSettingsController extends Controller
{
    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if ($user->role !== 'owner' && ! in_array($user->profile?->slug, ['admin', 'manager'])) {
            abort(403, 'No tienes permisos para configurar el portal del paciente');
        }
    }

    /**
     * GET /patient-portal/settings
     * Devuelve el slug actual de la clínica + sugerencia si es null,
     * más la configuración del portal (estado, horizonte, política de cancelación).
     */
    public function show(): JsonResponse
    {
        $this->authorizeAccess();

        /** @var Clinic $clinic */
        $clinic = Auth::user()->clinic;

        $settings = PatientPortalSettings::forClinic($clinic->id);

        return response()->json(array_merge([
            'slug' => $clinic->slug,
            'suggested_slug' => $clinic->slug ? null : Str::slug($clinic->name),
        ], [
            'is_active' => $settings->is_active,
            'max_horizon_days' => $settings->max_horizon_days,
            'cancellation_hours' => $settings->cancellation_hours,
        ]));
    }

    /**
     * GET /patient-portal/slug-check?slug=xxx
     * Comprueba disponibilidad del slug en la tabla clinics (ignorando la propia).
     */
    public function checkSlug(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $request->validate([
            'slug' => 'required|string|max:120',
        ]);

        $slug = $request->input('slug');
        $clinicId = Auth::user()->clinic_id;

        $exists = Clinic::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('id', '!=', $clinicId)
            ->exists();

        return response()->json(['available' => ! $exists]);
    }

    /**
     * PUT /patient-portal/settings
     * Actualiza el slug de la clínica (identificador del portal del paciente)
     * y la configuración del portal (estado, horizonte, política de cancelación).
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $clinicId = Auth::user()->clinic_id;

        $validated = $request->validate([
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('clinics', 'slug')->ignore($clinicId),
            ],
            'is_active' => 'nullable|boolean',
            'max_horizon_days' => 'nullable|integer|min:1|max:365',
            'cancellation_hours' => 'nullable|integer|min:1|max:720',
        ]);

        /** @var Clinic $clinic */
        $clinic = Auth::user()->clinic;
        $clinic->update(['slug' => $validated['slug']]);

        $settings = PatientPortalSettings::forClinic($clinicId, persist: true);
        $settings->update([
            'is_active' => $this->filledOr('is_active', $validated, $settings->is_active),
            'max_horizon_days' => $this->filledOr('max_horizon_days', $validated, $settings->max_horizon_days),
            'cancellation_hours' => $this->filledOr('cancellation_hours', $validated, $settings->cancellation_hours),
        ]);

        return response()->json([
            'message' => 'Configuración del portal guardada.',
            'slug' => $clinic->fresh()->slug,
            'is_active' => $settings->is_active,
            'max_horizon_days' => $settings->max_horizon_days,
            'cancellation_hours' => $settings->cancellation_hours,
        ]);
    }

    /**
     * Devuelve el valor validado si está presente y no es null; si no, conserva
     * el valor actual de la configuración.
     */
    private function filledOr(string $key, array $validated, mixed $current): mixed
    {
        return array_key_exists($key, $validated) && $validated[$key] !== null
            ? $validated[$key]
            : $current;
    }
}
