<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Availability\CheckAvailability;
use App\Services\BonusService;
use App\Models\Bonus;
use App\Models\BonusUsage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * Listado de citas (opcionalmente por rango)
     */
    public function index(Request $request)
    {
        // Persistir automáticamente citas programadas cuya end_time ya pasó
        Appointment::where('status', 'scheduled')
            ->where('end_time', '<', Carbon::now())
            ->update(['status' => 'completed']);

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

        // PRE-VALIDATION: enforce payment_type rules before creating appointment
        $paymentType = $request->input('payment_type');
        $useBonusId = $request->input('use_bonus_id');

        if ($paymentType === 'single' && $useBonusId) {
            return response()->json(['error' => 'Cuando payment_type es single, bonus_id debe ser null'], 422);
        }

        if ($paymentType === 'bonus') {
            if (! $useBonusId) {
                return response()->json(['error' => 'Debe seleccionar un bono cuando payment_type es bonus'], 422);
            }

            // Validate bonus exists and belongs to patient and clinic, has remaining_sessions and not expired
            $bonus = Bonus::find($useBonusId);
            if (! $bonus) {
                return response()->json(['error' => 'Bono no encontrado'], 422);
            }
            if ($bonus->patient_id != $data['patient_id']) {
                return response()->json(['error' => 'El bono no pertenece a este paciente'], 422);
            }
            if ($bonus->clinic_id != $clinicId) {
                return response()->json(['error' => 'El bono no pertenece a esta clínica'], 422);
            }
            if ($bonus->remaining_sessions <= 0) {
                return response()->json(['error' => 'Bono agotado'], 422);
            }
            if ($bonus->isExpired()) {
                return response()->json(['error' => 'Bono expirado'], 422);
            }
        }

        // All good — create appointment and apply bonus inside a transaction so failure to apply bonus rolls back appointment
        try {
            $result = DB::transaction(function () use ($data, $clinicId, $request) {
                // persist clinic id and map use_bonus_id to bonus_id column
                $data['clinic_id'] = $clinicId;
                if (isset($data['use_bonus_id'])) {
                    $data['bonus_id'] = $data['use_bonus_id'];
                }

                $appointment = Appointment::create($data);

                // If bonus flow, attempt to consume the bonus (BonusService handles usage logging)
                if (($request->input('payment_type') === 'bonus') || $request->filled('use_bonus_id')) {
                    $bonusService = new BonusService();
                    $usage = $bonusService->useBonusForAppointment($request->input('use_bonus_id'), $appointment, $request->input('bonus_notes'));
                    return ['appointment' => $appointment, 'bonus_usage' => $usage];
                }

                return ['appointment' => $appointment];
            });
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Return created resources
        if (isset($result['bonus_usage'])) {
            return response()->json(['appointment' => $result['appointment'], 'bonus_usage' => $result['bonus_usage']], 201);
        }

        return response()->json($result['appointment'], 201);
    }

    /**
     * Ver cita
     */
    public function show(Request $request, Appointment $appointment)
    {
        // Si la cita está marcada como programada pero su end_time ya pasó,
        // persistimos el estado como 'completed'.
        try {
            if ($appointment->status === 'scheduled' && $appointment->end_time && Carbon::parse($appointment->end_time)->lt(Carbon::now())) {
                $appointment->status = 'completed';
                $appointment->save();
                // Refresh the model so callers receive updated value
                $appointment->refresh();
            }
        } catch (\Exception $e) {
            // no bloquear la vista si hay problema al parsear/la actualización
        }

        // If a bonus id is provided, attempt to use it for this appointment
        if ($request->filled('use_bonus_id')) {
            $bonusService = new BonusService();
            try {
                $usage = $bonusService->useBonusForAppointment($request->input('use_bonus_id'), $appointment, $request->input('bonus_notes'));
                $appointment->load(['bonus', 'patient']);
                return response()->json(['appointment' => $appointment, 'bonus_usage' => $usage]);
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        // Ensure related bonus (if any) and patient are loaded so frontend can show bonus details
        $appointment->load(['bonus', 'patient']);
        return $appointment;
    }

    /**
     * Actualizar cita
     */
    public function update(Request $request, Appointment $appointment)
    {
        // Permitimos actualizar fecha/hora, notas y campos de pago
        $data = $request->validate([
            'status'     => ['sometimes', 'string'],
            'start_time' => ['sometimes', 'date'],
            'end_time'   => ['sometimes', 'date', 'after:start_time'],
            'notes'      => ['sometimes', 'string', 'nullable'],
            'use_bonus_id' => ['sometimes', 'integer', 'exists:bonuses,id'],
            'bonus_notes' => ['sometimes', 'string', 'nullable'],
            'payment_type' => ['sometimes', 'string', 'in:single,bonus'],
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

        $oldPaymentType = $appointment->payment_type;
        $newPaymentType = $request->input('payment_type', $oldPaymentType);
        $useBonusId = $request->input('use_bonus_id');

        // Normalize data: if use_bonus_id present, map to bonus_id
        if (isset($data['use_bonus_id'])) {
            $data['bonus_id'] = $data['use_bonus_id'];
        }

        // Case: switching from bonus -> single (restore)
        if ($oldPaymentType === 'bonus' && $newPaymentType === 'single') {
            try {
                $result = DB::transaction(function () use ($appointment, $data) {
                    // clear bonus reference on appointment
                    $appointment->update(array_merge($data, ['payment_type' => 'single', 'bonus_id' => null]));

                    $bonusService = new BonusService();
                    $bonusService->restoreBonusIfCancelled($appointment);

                    return ['appointment' => $appointment];
                });
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return response()->json($result['appointment']);
        }

        // Case: switching to bonus (from single or different bonus)
        if ($newPaymentType === 'bonus') {
            // require a bonus id to consume
            if (! $useBonusId && ! isset($appointment->bonus_id)) {
                return response()->json(['error' => 'Debe seleccionar un bono al cambiar a payment_type=bonus'], 422);
            }

            $targetBonusId = $useBonusId ?: $appointment->bonus_id;

            // Pre-validate bonus
            $bonus = Bonus::find($targetBonusId);
            if (! $bonus) {
                return response()->json(['error' => 'Bono no encontrado'], 422);
            }
            if ($bonus->patient_id != $appointment->patient_id) {
                return response()->json(['error' => 'El bono no pertenece a este paciente'], 422);
            }
            $clinicId = app()->has('activeClinic') ? app('activeClinic')->id : $appointment->clinic_id;
            if ($bonus->clinic_id != $clinicId) {
                return response()->json(['error' => 'El bono no pertenece a esta clínica'], 422);
            }
            if ($bonus->remaining_sessions <= 0) {
                return response()->json(['error' => 'Bono agotado'], 422);
            }
            if ($bonus->isExpired()) {
                return response()->json(['error' => 'Bono expirado'], 422);
            }

            try {
                $result = DB::transaction(function () use ($appointment, $data, $targetBonusId, $request, $oldPaymentType) {
                    $bonusService = new BonusService();

                    // If previously bonus and different bonus id, restore old usage first
                    if ($oldPaymentType === 'bonus' && $appointment->bonus_id && $appointment->bonus_id != $targetBonusId) {
                        $bonusService->restoreBonusIfCancelled($appointment);
                    }

                    // persist new bonus_id and payment_type
                    $appointment->update(array_merge($data, ['payment_type' => 'bonus', 'bonus_id' => $targetBonusId]));

                    // If a usage already exists for this appointment and bonus, reuse it (editing case)
                    $existing = \App\Models\BonusUsage::where('bonus_id', $targetBonusId)->where('appointment_id', $appointment->id)->first();
                    if ($existing) {
                        $usage = $existing;
                    } else {
                        // consume target bonus for this appointment
                        $usage = $bonusService->useBonusForAppointment($targetBonusId, $appointment, $request->input('bonus_notes'));
                    }

                    return ['appointment' => $appointment, 'bonus_usage' => $usage];
                });
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return response()->json(['appointment' => $result['appointment'], 'bonus_usage' => $result['bonus_usage']]);
        }

        // Default: no payment type change or unrelated changes — regular update
        $appointment->update($data);

        return $appointment;
    }

    /**
     * Cancelar cita (cambio de estado)
     */
    public function cancel(Appointment $appointment)
    {
        try {
            $result = DB::transaction(function () use ($appointment) {
                $appointment->status = 'canceled';
                $appointment->save();

                // If this appointment had an associated bonus, restore its usage
                if ($appointment->bonus_id) {
                    // Restore remaining_sessions and delete BonusUsage if exists
                    $appointment->restoreBonusUsageIfCancelled();

                    // Clear bonus reference on the appointment
                    $appointment->bonus_id = null;
                    $appointment->payment_type = 'single';
                    $appointment->save();
                }

                return $appointment;
            });
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
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
