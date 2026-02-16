<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Availability\CheckAvailability;
use App\Services\BonusService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * Listado de citas (opcionalmente por rango)
     */
    public function index(Request $request)
    {
        $query = Appointment::query();

        // Cargar relación de paciente para que la API devuelva el nombre en el front
        $query->with('patient');

        // Si se pasa ?date=YYYY-MM-DD devolvemos citas de ese día
        if ($request->filled('date')) {
            $date = Carbon::parse($request->input('date'));
            $startOfDay = $date->startOfDay()->toDateTimeString();
            $endOfDay = $date->endOfDay()->toDateTimeString();

            $query->whereBetween('start_time', [$startOfDay, $endOfDay]);
        } else {
            if ($request->filled('from')) {
                $query->where('start_time', '>=', $request->date('from'));
            }

            if ($request->filled('to')) {
                $query->where('end_time', '<=', $request->date('to'));
            }
        }

        return $query->orderBy('start_time')->get();
    }

    /**
     * Crear cita
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'start_time' => ['required', 'date'],
            'end_time'   => ['required', 'date', 'after:start_time'],
            'status'     => ['nullable', 'string'],
            'payment_status' => ['nullable', 'string'],
            'payment_type' => ['sometimes', 'string', 'in:single,bonus'],
            'use_bonus_id' => ['sometimes', 'integer', 'exists:bonuses,id'],
            'bonus_notes' => ['sometimes', 'string', 'nullable'],
            'notes'      => ['nullable', 'string'],
        ]);

        $clinicId = app()->has('activeClinic') ? app('activeClinic')->id : $request->input('clinic_id');

        $start = Carbon::parse($data['start_time']);
        $end = Carbon::parse($data['end_time']);

        $checker = new CheckAvailability();
        $validation = $checker->validate($clinicId, $start, $end, $data['patient_id']);

        if (! $validation['valid']) {
            return response()->json(['errors' => $validation['errors']], 422);
        }

        $data['clinic_id'] = $clinicId;

        $appointment = Appointment::create($data);

        // If payment type is bonus or a bonus id was provided, attempt to apply the bonus
        if (($request->input('payment_type') === 'bonus') || $request->filled('use_bonus_id')) {
            $patientId = $data['patient_id'];

            // If user chose bonus but didn't send a specific bonus id, ensure there are active bonuses
            if ($request->input('payment_type') === 'bonus' && ! $request->filled('use_bonus_id')) {
                $hasActive = \App\Models\Bonus::where('patient_id', $patientId)
                    ->where('remaining_sessions', '>', 0)
                    ->where(function($q){ $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
                    ->exists();

                if (! $hasActive) {
                    return response()->json(['error' => 'No hay bonos activos disponibles para este paciente'], 422);
                }

                return response()->json(['error' => 'Debe seleccionar un bono para pagar con bono'], 422);
            }

            // If a bonus id was provided, attempt to use it
            if ($request->filled('use_bonus_id')) {
                $bonusService = new BonusService();
                try {
                    $usage = $bonusService->useBonusForAppointment($request->input('use_bonus_id'), $appointment, $request->input('bonus_notes'));
                } catch (\Exception $e) {
                    // Rollback appointment if bonus application fails
                    $appointment->delete();
                    return response()->json(['error' => $e->getMessage()], 422);
                }

                return response()->json(['appointment' => $appointment, 'bonus_usage' => $usage], 201);
            }
        }

        return response()->json($appointment, 201);
    }

    /**
     * Ver cita
     */
    public function show(Request $request, Appointment $appointment)
    {
        // If a bonus id is provided, attempt to use it for this appointment
        if ($request->filled('use_bonus_id')) {
            $bonusService = new BonusService();
            try {
                $usage = $bonusService->useBonusForAppointment($request->input('use_bonus_id'), $appointment, $request->input('bonus_notes'));
                return response()->json(['appointment' => $appointment, 'bonus_usage' => $usage]);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        return $appointment;
    }

    /**
     * Actualizar cita
     */
    public function update(Request $request, Appointment $appointment)
    {
        // Sólo permitimos actualizar fecha/hora y notas
        $data = $request->validate([
            'start_time' => ['sometimes', 'date'],
            'end_time'   => ['sometimes', 'date', 'after:start_time'],
            'notes'      => ['sometimes', 'string', 'nullable'],
            'use_bonus_id' => ['sometimes', 'integer', 'exists:bonuses,id'],
            'bonus_notes' => ['sometimes', 'string', 'nullable'],
        ]);

        $needsAvailabilityCheck = isset($data['start_time']) || isset($data['end_time']);

        if ($needsAvailabilityCheck) {
            $start = isset($data['start_time']) ? Carbon::parse($data['start_time']) : Carbon::parse($appointment->start_time);
            $end = isset($data['end_time']) ? Carbon::parse($data['end_time']) : Carbon::parse($appointment->end_time);

            $clinicId = app()->has('activeClinic') ? app('activeClinic')->id : $appointment->clinic_id;

            $checker = new CheckAvailability();
            $validation = $checker->validate($clinicId, $start, $end, $appointment->patient_id, $appointment->id);

            if (! $validation['valid']) {
                return response()->json(['errors' => $validation['errors']], 422);
            }
        }

        $appointment->update($data);

        // If a bonus is requested to be used during update, attempt to apply it
        if ($request->filled('use_bonus_id')) {
            $bonusService = new BonusService();
            try {
                $usage = $bonusService->useBonusForAppointment($request->input('use_bonus_id'), $appointment, $request->input('bonus_notes'));
                return response()->json(['appointment' => $appointment, 'bonus_usage' => $usage]);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        return $appointment;
    }

    /**
     * Cancelar cita (cambio de estado)
     */
    public function cancel(Appointment $appointment)
    {
        $appointment->status = 'canceled';
        $appointment->save();

        return response()->json($appointment);
    }

    /**
     * Eliminar cita
     */
    public function destroy(Appointment $appointment)
    {
        $appointment->delete();

        return response()->noContent();
    }
}
