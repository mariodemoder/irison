<?php

namespace App\Http\Controllers\Api;

use App\Models\Patient;
use App\Http\Requests\Patients\StorePatientRequest;
use App\Http\Requests\Patients\UpdatePatientRequest;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
        Gate::authorize('viewAny', Patient::class);

        $result = $this->patientsServices->index($request->all());

        if (Auth::user()->isViewer()) {
            $result['data'] = array_map(function ($patient) {
                return $this->stripFinancialData($patient);
            }, $result['data']);
        }

        return response()->json($result);
    }

    /**
     * Crear un paciente
     */
    public function store(StorePatientRequest $request)
    {
        Gate::authorize('create', Patient::class);

        $clinicId = (int) Auth::user()->clinic_id;
        $result = $this->patientsServices->store($request->validated(), $clinicId);

        return response()->json($result['payload'], $result['status']);
    }

    /**
     * Ver un paciente
     */
    public function show(Patient $patient)
    {
        Gate::authorize('view', $patient);

        $data = $this->patientsServices->show($patient);

        if (Auth::user()->isViewer()) {
            $data = $this->stripFinancialData($data);
        }

        return response()->json($data);
    }

    /**
     * Actualizar paciente
     */
    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        Gate::authorize('update', $patient);

        $clinicId = (int) Auth::user()->clinic_id;
        $result = $this->patientsServices->update($patient, $request->validated(), $clinicId);

        return response()->json($result['payload'], $result['status']);
    }

    /**
     * Eliminar paciente
     */
    private function stripFinancialData(array $data): array
    {
        unset(
            $data['available_credit'],
            $data['payments'],
            $data['packs'],
        );

        return $data;
    }

    public function destroy(Patient $patient)
    {
        Gate::authorize('delete', $patient);

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
