<?php

namespace App\Services\Patients;

use App\Models\Patient;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use DomainException;

class PatientsServices
{
    public function index(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $q = (string) ($filters['q'] ?? '');

        $query = Patient::orderBy('last_name');

        if ($q !== '') {
            $like = '%' . strtolower($q) . '%';
            $query->where(function ($sub) use ($like) {
                $sub->whereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", [$like])
                    ->orWhereRaw('LOWER(nif) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        $paginator = $query->paginate($perPage);

        return [
            'data' => $this->mapPaginatorItems($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function store(array $input, int $clinicId): array
    {
        $data = Validator::make($input, [
            'name' => 'required|string|max:255',
            'nif' => ['nullable', 'string', 'max:50', 'regex:/\\d/'],
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:120',
            'province' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
            'birth_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ])->validate();

        $nifResult = $this->resolveNifForCreate($data['nif'] ?? null, $clinicId);
        if (($nifResult['conflict'] ?? false) === true) {
            return [
                'status' => 409,
                'payload' => [
                    'message' => 'El NIF ya existe para otro paciente en esta clínica',
                    'existing' => ['id' => $nifResult['existing_id']],
                ],
            ];
        }

        [$firstName, $lastName] = $this->splitName($data['name']);

        $patient = Patient::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'nif' => $nifResult['nif'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'zip' => $data['zip'] ?? null,
            'city' => $data['city'] ?? null,
            'province' => $data['province'] ?? null,
            'country' => $data['country'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return [
            'status' => 201,
            'payload' => $this->mapPatient($patient),
        ];
    }

    public function show(Patient $patient): array
    {
        $patient->loadMissing(['appointments.clinicalRecord', 'packs', 'payments', 'clinicalRecords']);

        $appointments = $patient->relationLoaded('appointments')
            ? $patient->appointments->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'start_time' => $appointment->start_time,
                    'status' => $appointment->status,
                    'notes' => $appointment->notes ?: ($appointment->clinicalRecord?->notes ?? null),
                ];
            })->toArray()
            : [];

        $packs = $patient->relationLoaded('packs')
            ? $patient->packs->map(function ($pack) {
                return [
                    'id' => $pack->id,
                    'total_sessions' => $pack->total_sessions,
                    'remaining_sessions' => $pack->remaining_sessions,
                    'status' => $pack->status,
                ];
            })->toArray()
            : [];

        $payments = $patient->relationLoaded('payments')
            ? $patient->payments
                ->sortByDesc(function ($payment) {
                    return $payment->paid_at ?? $payment->created_at;
                })
                ->values()
                ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'concept' => $payment->concept,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'method' => $payment->method,
                    'paid_at' => $payment->paid_at,
                    'created_at' => $payment->created_at,
                ];
            })->toArray()
            : [];

        $clinicalRecords = $patient->relationLoaded('clinicalRecords')
            ? $patient->clinicalRecords->map(function ($record) {
                return [
                    'id' => $record->id,
                    'notes' => $record->notes ?? null,
                    'created_at' => $record->created_at,
                ];
            })->toArray()
            : [];

        return [
            'id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'counter' => $patient->counter,
            'nif' => $patient->nif,
            'name' => $patient->name,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'address' => $patient->address,
            'zip' => $patient->zip,
            'city' => $patient->city,
            'province' => $patient->province,
            'country' => $patient->country,
            'birth_date' => $patient->birth_date,
            'notes' => $patient->notes,
            'available_credit' => $patient->availableCredit(),
            'appointments' => $appointments,
            'packs' => $packs,
            'payments' => $payments,
            'clinical_records' => $clinicalRecords,
            'created_at' => $patient->created_at,
            'updated_at' => $patient->updated_at,
        ];
    }

    public function update(Patient $patient, array $input, int $clinicId): array
    {
        $data = Validator::make($input, [
            'name' => 'sometimes|required|string|max:255',
            'nif' => [
                'nullable',
                'string',
                'max:50',
                'regex:/\\d/',
                Rule::unique('patients', 'nif')->ignore($patient->id)->where(function ($query) use ($clinicId) {
                    return $query->where('clinic_id', $clinicId);
                }),
            ],
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:120',
            'province' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
            'birth_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ])->validate();

        $payload = [];

        if (array_key_exists('name', $data)) {
            [$firstName, $lastName] = $this->splitName($data['name']);
            $payload['first_name'] = $firstName;
            $payload['last_name'] = $lastName;
        }

        if (array_key_exists('nif', $data)) {
            $nifResult = $this->resolveNifForUpdate($patient, $data['nif'], $clinicId);
            if (($nifResult['conflict'] ?? false) === true) {
                return [
                    'status' => 409,
                    'payload' => [
                        'message' => 'El NIF ya existe para otro paciente',
                        'existing' => ['id' => $nifResult['existing_id']],
                    ],
                ];
            }
            $payload['nif'] = $nifResult['nif'];
        }

        foreach (['phone', 'email', 'address', 'zip', 'city', 'province', 'country', 'birth_date', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        $patient->update($payload);

        return [
            'status' => 200,
            'payload' => $this->mapPatient($patient),
        ];
    }

    public function destroy(Patient $patient): void
    {
        $hasNonCanceledAppointments = $patient->appointments()
            ->whereNotIn('status', ['canceled', 'cancelled'])
            ->exists();

        $hasBlockingPayments = $patient->payments()
            ->whereIn('status', ['pending', 'completed'])
            ->exists();

        if ($hasNonCanceledAppointments || $hasBlockingPayments) {
            throw new DomainException('No se puede eliminar el paciente porque tiene citas no canceladas o pagos pendientes/realizados');
        }

        $patient->delete();
    }

    private function mapPaginatorItems(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()->transform(function (Patient $patient) {
            return $this->mapPatient($patient);
        })->toArray();
    }

    private function mapPatient(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'clinic_id' => $patient->clinic_id,
            'counter' => $patient->counter,
            'nif' => $patient->nif,
            'name' => $patient->name,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'address' => $patient->address,
            'zip' => $patient->zip,
            'city' => $patient->city,
            'province' => $patient->province,
            'country' => $patient->country,
            'birth_date' => $patient->birth_date,
            'notes' => $patient->notes,
            'available_credit' => $patient->availableCredit(),
            'created_at' => $patient->created_at,
            'updated_at' => $patient->updated_at,
        ];
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name));
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

        return [$first, $last];
    }

    private function resolveNifForCreate(?string $nif, int $clinicId): array
    {
        if (empty($nif)) {
            return ['nif' => null, 'conflict' => false];
        }

        $existing = Patient::where('nif', $nif)->first();
        if (!$existing) {
            return ['nif' => $nif, 'conflict' => false];
        }

        if ((int) $existing->clinic_id === $clinicId) {
            return ['nif' => null, 'conflict' => true, 'existing_id' => $existing->id];
        }

        return ['nif' => null, 'conflict' => false];
    }

    private function resolveNifForUpdate(Patient $patient, ?string $nif, int $clinicId): array
    {
        if (empty($nif)) {
            return ['nif' => null, 'conflict' => false];
        }

        $existing = Patient::where('nif', $nif)
            ->where('id', '!=', $patient->id)
            ->first();

        if (!$existing) {
            return ['nif' => $nif, 'conflict' => false];
        }

        if ((int) $existing->clinic_id === $clinicId) {
            return ['nif' => null, 'conflict' => true, 'existing_id' => $existing->id];
        }

        return ['nif' => null, 'conflict' => false];
    }
}
