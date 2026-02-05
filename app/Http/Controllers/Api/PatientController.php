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
        $perPage = (int) $request->get('per_page', 15);

        $paginator = Patient::orderBy('last_name')
            ->paginate($perPage);

        $items = $paginator->getCollection()->transform(function ($p) {
            return [
                'id' => $p->id,
                'clinic_id' => $p->clinic_id,
                'name' => $p->name,
                'phone' => $p->phone,
                'email' => $p->email,
                'birth_date' => $p->birth_date,
                'notes' => $p->notes,
                'created_at' => $p->created_at,
                'updated_at' => $p->updated_at,
            ];
        })->toArray();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Crear un paciente
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'phone'      => 'nullable|string|max:50',
            'email'      => 'nullable|email|max:255',
            'birth_date' => 'nullable|date',
            'notes'      => 'nullable|string',
        ]);

        // Split básico: primera palabra -> first_name, resto -> last_name
        $parts = preg_split('/\s+/', trim($data['name']));
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

        $patient = Patient::create([
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'name' => $patient->name,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'birth_date' => $patient->birth_date,
            'notes' => $patient->notes,
            'created_at' => $patient->created_at,
            'updated_at' => $patient->updated_at,
        ], 201);
    }

    /**
     * Ver un paciente
     */
    public function show(Patient $patient)
    {
        return response()->json([
            'id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'name' => $patient->name,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'birth_date' => $patient->birth_date,
            'notes' => $patient->notes,
            'created_at' => $patient->created_at,
            'updated_at' => $patient->updated_at,
        ]);
    }

    /**
     * Actualizar paciente
     */
    public function update(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'name'       => 'sometimes|required|string|max:255',
            'phone'      => 'nullable|string|max:50',
            'email'      => 'nullable|email|max:255',
            'birth_date' => 'nullable|date',
            'notes'      => 'nullable|string',
        ]);

        $payload = [];
        if (array_key_exists('name', $data)) {
            $parts = preg_split('/\s+/', trim($data['name']));
            $payload['first_name'] = $parts[0] ?? '';
            $payload['last_name'] = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
        }

        foreach (['phone','email','birth_date','notes'] as $k) {
            if (array_key_exists($k, $data)) $payload[$k] = $data[$k];
        }

        $patient->update($payload);

        return response()->json([
            'id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'name' => $patient->name,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'birth_date' => $patient->birth_date,
            'notes' => $patient->notes,
            'created_at' => $patient->created_at,
            'updated_at' => $patient->updated_at,
        ]);
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
