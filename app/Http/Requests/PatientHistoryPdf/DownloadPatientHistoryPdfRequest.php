<?php

namespace App\Http\Requests\PatientHistoryPdf;

use Illuminate\Foundation\Http\FormRequest;

class DownloadPatientHistoryPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorización manejada por Policy
    }

    /**
     * Reglas de validación para descargar el PDF del historial clínico de un paciente.
     */
    public function rules(): array
    {
        return [
            'download' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'download.boolean' => 'El parámetro download debe ser verdadero o falso.',
        ];
    }
}
