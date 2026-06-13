<?php

namespace App\Http\Controllers\Api\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking\BookingPage;
use App\Services\Booking\AvailabilityEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(
        private AvailabilityEngine $availabilityEngine
    ) {}

    public function dates(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => 'required|string',
            'service_id' => 'required|integer',
            'professional_id' => 'nullable|integer',
            'month' => 'required|date_format:Y-m',
        ]);

        $page = BookingPage::where('slug', $request->slug)
            ->where('is_active', true)
            ->first();

        if (! $page) {
            return response()->json(['message' => 'Página de reserva no encontrada.'], 404);
        }

        $dates = $this->availabilityEngine->getAvailableDates(
            $page->clinic_id,
            (int) $request->service_id,
            $request->professional_id ? (int) $request->professional_id : null,
            $request->month
        );

        return response()->json(['dates' => $dates]);
    }

    public function slots(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => 'required|string',
            'service_id' => 'required|integer',
            'professional_id' => 'nullable|integer',
            'date' => 'required|date_format:Y-m-d',
        ]);

        $page = BookingPage::where('slug', $request->slug)
            ->where('is_active', true)
            ->first();

        if (! $page) {
            return response()->json(['message' => 'Página de reserva no encontrada.'], 404);
        }

        $slots = $this->availabilityEngine->getAvailableSlots(
            $page->clinic_id,
            (int) $request->service_id,
            $request->professional_id ? (int) $request->professional_id : null,
            $request->date
        );

        return response()->json(['slots' => $slots]);
    }
}
