<?php

namespace App\Http\Requests\Patients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\ValidatePhoneFormat;
use App\Rules\ValidateNIFFormat;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorización manejada por Policy
    }

    /**
     * Reglas de validación para actualizar un patient.
     * Los campos son 'sometimes' para permitir actualizaciones parciales.
     */
    public function rules(): array
    {
        $patientId = $this->route('patient')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', Rule::unique('patients', 'email')->ignore($patientId)],
            'phone' => ['sometimes', 'nullable', new ValidatePhoneFormat()],
            'nif' => ['sometimes', 'nullable', new ValidateNIFFormat()],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'zip' => ['sometimes', 'nullable', 'string', 'max:10'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del paciente es requerido.',
            'email.unique' => 'Este email ya está registrado.',
        ];
    }
}
