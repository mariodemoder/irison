<?php

declare(strict_types=1);

namespace Modules\DataImport\Infrastructure\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el envío de la importación. Para imágenes de paciente se requieren
 * dos ficheros: `file` (CSV) y `zip` (ZIP de imágenes).
 */
class ImportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $entity = (string) $this->route('entity');
        $isImages = $entity === 'patient-images';

        $maxCsvKb = (int) config('dataimport.max_csv_kb', 5120);
        $maxZipKb = (int) config('dataimport.max_zip_kb', 10240);

        $rules = [
            'file' => ['required', 'file', 'mimes:csv,txt', "max:{$maxCsvKb}"],
        ];

        if ($isImages) {
            $rules['zip'] = ['required', 'file', 'mimes:zip', "max:{$maxZipKb}"];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Se requiere el fichero CSV.',
            'file.mimes' => 'El fichero debe ser CSV.',
            'file.max' => 'El fichero CSV supera el tamaño máximo permitido.',
            'zip.required' => 'Se requiere el fichero ZIP de imágenes.',
            'zip.mimes' => 'El fichero de imágenes debe ser ZIP.',
            'zip.max' => 'El fichero ZIP supera el tamaño máximo permitido.',
        ];
    }
}