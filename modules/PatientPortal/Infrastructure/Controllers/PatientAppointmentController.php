<?php

namespace Modules\PatientPortal\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\PatientPortal\Application\Services\PatientAppointmentService;
use Modules\PatientPortal\Application\DTOs\AppointmentRequestDTO;
use Modules\PatientPortal\Domain\Exceptions\AppointmentCancellationDeniedException;
class PatientAppointmentController extends Controller
{
    public function __construct(
        private PatientAppointmentService $appointmentService
    ) {}

    public function upcoming(Request $request)
    {
        $appointments = $this->appointmentService->upcoming($request->user());

        return response()->json(['appointments' => $appointments]);
    }

    public function history(Request $request)
    {
        $appointments = $this->appointmentService->history($request->user(), $request->only([
            'from', 'to', 'status', 'professional_id', 'per_page',
        ]));

        return response()->json($appointments);
    }

    public function show(Request $request, int $id)
    {
        $appointment = $this->appointmentService->show($request->user(), $id);

        return response()->json(['appointment' => $appointment]);
    }

    public function request(Request $request)
    {
        $request->validate([
            'preferred_date' => 'required|date|after_or_equal:today',
            'preferred_time' => 'required|string',
            'professional_id' => 'nullable|integer',
            'service_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $dto = AppointmentRequestDTO::fromRequest($request);

        try {
            $appointment = $this->appointmentService->request(
                $request->user(),
                $dto,
                $request->ip(),
                $request->userAgent()
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['appointment' => $appointment], 201);
    }

    public function cancel(Request $request, int $id)
    {
        $appointment = \App\Models\Appointment::where('clinic_id', $request->user()->clinic_id)
            ->where('patient_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        try {
            $this->appointmentService->cancel(
                $request->user(),
                $appointment,
                $request->ip(),
                $request->userAgent()
            );
        } catch (AppointmentCancellationDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['message' => 'Cita cancelada correctamente.']);
    }

    public function reschedule(Request $request, int $id)
    {
        $request->validate([
            'preferred_date' => 'required|date|after_or_equal:today',
            'preferred_time' => 'required|string',
            'professional_id' => 'nullable|integer',
            'service_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $appointment = \App\Models\Appointment::where('clinic_id', $request->user()->clinic_id)
            ->where('patient_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $dto = AppointmentRequestDTO::fromRequest($request);

        try {
            $newAppointment = $this->appointmentService->reschedule(
                $request->user(),
                $appointment,
                $dto,
                $request->ip(),
                $request->userAgent()
            );
        } catch (AppointmentCancellationDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['appointment' => $newAppointment], 201);
    }
}
