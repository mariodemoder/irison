<?php

namespace App\Http\Requests\PatientImages;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientImageRequest extends FormRequest
{
    private const MAX_FILE_SIZE_KB = 200;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'file' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,gif,pdf',
                'max:' . self::MAX_FILE_SIZE_KB,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'La descripción es requerida.',
            'description.string' => 'La descripción debe ser texto.',
            'description.max' => 'La descripción no puede exceder 255 caracteres.',
            'file.file' => 'El archivo debe ser un archivo válido.',
            'file.mimes' => 'El archivo debe ser: jpg, jpeg, png, webp, gif o pdf.',
            'file.max' => 'El archivo no puede exceder ' . self::MAX_FILE_SIZE_KB . ' KB.',
        ];
    }
}
