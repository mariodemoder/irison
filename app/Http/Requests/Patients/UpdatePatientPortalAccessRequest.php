<?php

namespace App\Http\Requests\Patients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientPortalAccessRequest extends FormRequest
{
    /**
     * La autorización se delega a las Policies (PatientPolicy::update).
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'El estado de acceso es requerido.',
            'status.in' => 'El estado de acceso debe ser "active" o "inactive".',
        ];
    }
}