<?php

namespace App\Http\Controllers\Api;

use App\Models\Patient;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;

class PatientController extends BaseController
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'clinic']);
    }

    /**
     * Listado de pacientes (paginado)
     */
    public function index(Request $request)
    {
        $patients = Patient::orderBy('last_name')
            ->paginate($request->get('per_page', 15));

        return response()->json($patients);
    }

    /**
     * Crear un paciente
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'phone'      => 'nullable|string|max:50',
            'email'      => 'nullable|email|max:255',
            'birth_date' => 'nullable|date',
            'notes'      => 'nullable|string',
        ]);

        $patient = Patient::create($data); // clinic_id se asigna automáticamente

        return response()->json($patient, 201);
    }

    /**
     * Ver un paciente
     */
    public function show(Patient $patient)
    {
        return response()->json($patient);
    }

    /**
     * Actualizar paciente
     */
    public function update(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name'  => 'sometimes|required|string|max:255',
            'phone'      => 'nullable|string|max:50',
            'email'      => 'nullable|email|max:255',
            'birth_date' => 'nullable|date',
            'notes'      => 'nullable|string',
        ]);

        $patient->update($data);

        return response()->json($patient);
    }

    /**
     * Eliminar paciente
     */
    public function destroy(Patient $patient)
    {
        $patient->delete();

        return response()->noContent();
    }
}
