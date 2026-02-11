<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Availability\CheckAvailability;
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

        return response()->json($appointment, 201);
    }

    /**
     * Ver cita
     */
    public function show(Appointment $appointment)
    {
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
