<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clinicId = (int) (auth()->user()->clinic_id ?? 0);

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('professions', 'name')->where(fn ($q) => $q->where('clinic_id', $clinicId)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la profesión es requerido.',
            'name.unique' => 'Esta profesión ya existe en tu clínica.',
        ];
    }
}
