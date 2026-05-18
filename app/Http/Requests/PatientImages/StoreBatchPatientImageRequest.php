<?php

namespace App\Http\Requests\PatientImages;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchPatientImageRequest extends FormRequest
{
    private const MAX_FILES_PER_PATIENT = 6;
    private const MAX_FILE_SIZE_KB = 200;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:' . self::MAX_FILES_PER_PATIENT],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,gif,pdf',
                'max:' . self::MAX_FILE_SIZE_KB,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Debe proporcionar al menos un archivo.',
            'items.array' => 'Los items deben ser un arreglo.',
            'items.min' => 'Debe proporcionar al menos 1 archivo.',
            'items.max' => 'No se pueden subir más de ' . self::MAX_FILES_PER_PATIENT . ' archivos por lote.',
            'items.*.description.required' => 'La descripción de cada archivo es requerida.',
            'items.*.description.max' => 'La descripción no puede exceder 255 caracteres.',
            'items.*.file.required' => 'El archivo es requerido.',
            'items.*.file.file' => 'Cada item debe ser un archivo válido.',
            'items.*.file.mimes' => 'Los archivos deben ser: jpg, jpeg, png, webp, gif o pdf.',
            'items.*.file.max' => 'Cada archivo no puede exceder ' . self::MAX_FILE_SIZE_KB . ' KB.',
        ];
    }
}
