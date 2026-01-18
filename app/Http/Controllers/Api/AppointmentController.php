<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * Listado de citas (opcionalmente por rango)
     */
    public function index(Request $request)
    {
        $query = Appointment::query();

        if ($request->filled('from')) {
            $query->where('start_time', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('end_time', '<=', $request->date('to'));
        }

        return $query
            ->orderBy('start_time')
            ->get();
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
        ]);

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
        $data = $request->validate([
            'start_time' => ['sometimes', 'date'],
            'end_time'   => ['sometimes', 'date', 'after:start_time'],
            'status'     => ['sometimes', 'string'],
            'payment_status' => ['sometimes', 'string'],
        ]);

        $appointment->update($data);

        return $appointment;
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
