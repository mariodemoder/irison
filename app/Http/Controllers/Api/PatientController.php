<?php

namespace App\Http\Controllers\Api;

use App\Models\Patient;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Patients\PatientsServices;
use DomainException;

class PatientController extends BaseController
{
    public function __construct(private readonly PatientsServices $patientsServices)
    {
        $this->middleware(['auth:sanctum', 'clinic']);
    }

    /**
     * Listado de pacientes (paginado)
     */
    public function index(Request $request)
    {
        return response()->json($this->patientsServices->index($request->all()));
    }

    /**
     * Crear un paciente
     */
    public function store(Request $request)
    {
        $clinicId = (int) Auth::user()->clinic_id;
        $result = $this->patientsServices->store($request->all(), $clinicId);

        return response()->json($result['payload'], $result['status']);
    }

    /**
     * Ver un paciente
     */
    public function show(Patient $patient)
    {
        return response()->json($this->patientsServices->show($patient));
    }

    /**
     * Actualizar paciente
     */
    public function update(Request $request, Patient $patient)
    {
        $clinicId = (int) Auth::user()->clinic_id;
        $result = $this->patientsServices->update($patient, $request->all(), $clinicId);

        return response()->json($result['payload'], $result['status']);
    }

    /**
     * Eliminar paciente
     */
    public function destroy(Patient $patient)
    {
        try {
            $this->patientsServices->destroy($patient);
            return response()->noContent();
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
