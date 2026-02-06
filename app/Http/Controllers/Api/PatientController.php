<?php

namespace App\Http\Controllers\Api;

use App\Models\Patient;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
                'nif' => $p->nif,
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
            'nif'        => ['nullable','string','max:50','regex:/\\d/'],
            'phone'      => 'nullable|string|max:50',
            'email'      => 'nullable|email|max:255',
            'birth_date' => 'nullable|date',
            'notes'      => 'nullable|string',
        ]);

        // Si se proporciona nif, comprobar duplicado y devolver 409 con el id existente
        if (!empty($data['nif'])) {
            $existing = Patient::where('nif', $data['nif'])->first();
            if ($existing) {
                return response()->json([
                    'message' => 'El NIF ya existe para otro paciente',
                    'existing' => ['id' => $existing->id]
                ], 409);
            }
        }

        // Split básico: primera palabra -> first_name, resto -> last_name
        $parts = preg_split('/\s+/', trim($data['name']));
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

        $patient = Patient::create([
            'first_name' => $first,
            'last_name' => $last,
            'nif' => $data['nif'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'nif' => $patient->nif,
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
        // Cargar relaciones esenciales (aunque vacías) para futuro historial
        $patient->load(['appointments', 'packs', 'payments']);

        return response()->json([
            'id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'nif' => $patient->nif,
            'name' => $patient->name,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'birth_date' => $patient->birth_date,
            'notes' => $patient->notes,
            'appointments' => $patient->appointments->map(function ($a) {
                return [
                    'id' => $a->id,
                    'start_time' => $a->start_time,
                    'status' => $a->status,
                ];
            })->toArray(),
            'packs' => $patient->packs->map(function ($p) {
                return [
                    'id' => $p->id,
                    'total_sessions' => $p->total_sessions,
                    'remaining_sessions' => $p->remaining_sessions,
                    'status' => $p->status,
                ];
            })->toArray(),
            'payments' => $patient->payments->map(function ($pay) {
                return [
                    'id' => $pay->id,
                    'amount' => $pay->amount,
                    'status' => $pay->status,
                ];
            })->toArray(),
            'created_at' => $patient->created_at,
            'updated_at' => $patient->updated_at,
        ]);
    }

    /**
     * Actualizar paciente
     */
    public function update(Request $request, Patient $patient)
    {
        try {
            $data = $request->validate([
                'name'       => 'sometimes|required|string|max:255',
                'nif'        => ['nullable','string','max:50','regex:/\\d/', Rule::unique('patients','nif')->ignore($patient->id)],
                'phone'      => 'nullable|string|max:50',
                'email'      => 'nullable|email|max:255',
                'birth_date' => 'nullable|date',
                'notes'      => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            // Si la validación falla por nif duplicado, devolver 409 con id existente
            if (isset($errors['nif'])) {
                $nifVal = $request->input('nif');
                if (!empty($nifVal)) {
                    $existing = Patient::where('nif', $nifVal)->where('id', '!=', $patient->id)->first();
                    if ($existing) {
                        return response()->json([
                            'message' => 'El NIF ya existe para otro paciente',
                            'existing' => ['id' => $existing->id]
                        ], 409);
                    }
                }
            }

            // Re-throw para que Laravel devuelva 422 como antes si no es nif
            throw $e;
        }

        $payload = [];
        if (array_key_exists('name', $data)) {
            $parts = preg_split('/\s+/', trim($data['name']));
            $payload['first_name'] = $parts[0] ?? '';
            $payload['last_name'] = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
        }

        if (array_key_exists('nif', $data)) {
            $payload['nif'] = $data['nif'];
        }

        foreach (['phone','email','birth_date','notes'] as $k) {
            if (array_key_exists($k, $data)) $payload[$k] = $data[$k];
        }

        $patient->update($payload);

        return response()->json([
            'id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'nif' => $patient->nif,
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
