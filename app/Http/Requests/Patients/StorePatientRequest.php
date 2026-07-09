<?php

namespace App\Http\Requests\Patients;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\ValidatePhoneFormat;
use App\Rules\ValidateNIFFormat;

class StorePatientRequest extends FormRequest
{
    /**
     * Valida que el usuario autenticado pertenezca a la clínica correcta.
     * La autorización se delega a las Policies.
     */
    public function authorize(): bool
    {
        return true; // Autorización manejada por Policy/Gate
    }

    /**
     * Reglas de validación para crear un patient.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', new ValidatePhoneFormat()],
            'nif' => ['nullable', new ValidateNIFFormat()],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Mensajes de error personalizados (español).
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del paciente es requerido.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'email.email' => 'El email debe ser un formato válido.',
            'birth_date.date' => 'La fecha de nacimiento debe ser una fecha válida.',
        ];
    }
}
