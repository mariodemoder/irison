<?php

namespace App\Http\Controllers\Api\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\BookingPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $page = BookingPage::where('clinic_id', currentClinicId())->first();

        return response()->json([
            'data' => $page ? [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'is_active' => $page->is_active,
                'max_horizon_days' => $page->max_horizon_days,
                'cancellation_hours' => $page->cancellation_hours,
            ] : [
                'slug' => null,
                'title' => 'Reserva tu cita',
                'is_active' => true,
                'max_horizon_days' => 60,
                'cancellation_hours' => 24,
            ],
        ]);
    }

    public function checkSlug(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => 'required|string|max:255',
        ]);

        $slug = $request->input('slug');
        $exists = BookingPage::where('slug', $slug)
            ->where('clinic_id', '!=', currentClinicId())
            ->exists();

        return response()->json(['available' => !$exists]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('booking_pages', 'slug')->where(fn($q) => $q->where('clinic_id', '!=', currentClinicId())),
            ],
            'title' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'max_horizon_days' => 'nullable|integer|min:1|max:365',
            'cancellation_hours' => 'nullable|integer|min:1|max:720',
        ]);

        if (! isset($validated['title']) || $validated['title'] === null) {
            $validated['title'] = 'Reserva tu cita';
        }

        $page = BookingPage::firstOrNew(['clinic_id' => currentClinicId()]);

        $page->clinic_id = currentClinicId();

        if (! $page->exists && ! isset($validated['slug'])) {
            $clinic = \App\Models\Clinic::find(currentClinicId());
            $validated['slug'] = $clinic ? \Illuminate\Support\Str::slug($clinic->name) : 'booking-' . currentClinicId();
        }

        $page->fill($validated);
        $page->save();

        return response()->json([
            'message' => 'Configuración guardada.',
            'data' => $page->fresh(),
        ]);
    }
}
