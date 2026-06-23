<?php

namespace App\Services\Team;

use App\Models\Profession;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class ProfessionService
{
    public function index(int $clinicId): array
    {
        $professions = Profession::where('clinic_id', $clinicId)
            ->orderBy('name')
            ->get();

        return ['data' => $professions->map(fn ($p) => $this->map($p))->values()->toArray()];
    }

    public function store(array $input, int $clinicId): array
    {
        $data = Validator::make($input, [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('professions', 'name')
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
        ])->validate();

        $profession = Profession::create([
            'clinic_id' => $clinicId,
            'name' => trim($data['name']),
        ]);

        return ['status' => 201, 'payload' => $this->map($profession)];
    }

    public function update(Profession $profession, array $input, int $clinicId): array
    {
        $data = Validator::make($input, [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('professions', 'name')
                    ->ignore($profession->id)
                    ->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
        ])->validate();

        $profession->update(['name' => trim($data['name'])]);

        return ['status' => 200, 'payload' => $this->map($profession->fresh())];
    }

    public function destroy(Profession $profession): array
    {
        if ($profession->users()->exists()) {
            return ['status' => 409, 'payload' => [
                'message' => 'No se puede eliminar una profesión que tiene usuarios asignados.',
            ]];
        }

        $profession->delete();

        return ['status' => 200, 'payload' => ['message' => 'Profesión eliminada.']];
    }

    private function map(Profession $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'created_at' => $p->created_at,
        ];
    }
}
