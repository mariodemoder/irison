<?php

declare(strict_types=1);

namespace Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Booking\Services\PublicBookingService;

class PublicBookingController extends Controller
{
    public function __construct(
        private PublicBookingService $bookingService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string',
            'service_id' => 'required|integer',
            'professional_id' => 'nullable|integer',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'patient.first_name' => 'required|string|max:255',
            'patient.last_name' => 'required|string|max:255',
            'patient.email' => 'required|email|max:255',
            'patient.phone' => 'nullable|string|max:20',
            'patient.notes' => 'nullable|string|max:1000',
        ]);

        try {
            $appointment = $this->bookingService->createAppointment(
                $validated['slug'],
                (int) $validated['service_id'],
                isset($validated['professional_id']) ? (int) $validated['professional_id'] : null,
                $validated['date'],
                $validated['start_time'],
                $validated['patient']
            );

            $appointment->load(['patient', 'professional', 'clinic']);

            return response()->json([
                'message' => 'Cita creada correctamente.',
                'appointment' => [
                    'id' => $appointment->id,
                    'start_time' => $appointment->start_time->toDateTimeString(),
                    'end_time' => $appointment->end_time->toDateTimeString(),
                    'status' => $appointment->status,
                    'patient' => [
                        'first_name' => $appointment->patient->first_name,
                        'last_name' => $appointment->patient->last_name,
                        'email' => $appointment->patient->email,
                    ],
                    'professional' => $appointment->professional ? [
                        'name' => $appointment->professional->name,
                    ] : null,
                    'clinic' => [
                        'name' => $appointment->clinic->name,
                        'address' => $appointment->clinic->address,
                        'phone' => $appointment->clinic->phone,
                    ],
                ],
                'confirmation_token' => $appointment->confirmation_token,
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function show(string $token): JsonResponse
    {
        try {
            $appointment = $this->bookingService->findByToken($token);

            return response()->json([
                'appointment' => [
                    'id' => $appointment->id,
                    'start_time' => $appointment->start_time->toDateTimeString(),
                    'end_time' => $appointment->end_time->toDateTimeString(),
                    'status' => $appointment->status,
                    'patient' => [
                        'first_name' => $appointment->patient->first_name,
                        'last_name' => $appointment->patient->last_name,
                        'email' => $appointment->patient->email,
                    ],
                    'professional' => $appointment->professional ? [
                        'name' => $appointment->professional->name,
                    ] : null,
                    'clinic' => [
                        'name' => $appointment->clinic->name,
                        'address' => $appointment->clinic->address,
                        'phone' => $appointment->clinic->phone,
                    ],
                ],
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function cancel(string $token): JsonResponse
    {
        try {
            $appointment = $this->bookingService->cancelByToken($token);

            return response()->json([
                'message' => 'Cita cancelada correctamente.',
                'appointment' => [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                ],
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
