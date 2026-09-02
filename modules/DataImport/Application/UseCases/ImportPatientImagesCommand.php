<?php

declare(strict_types=1);

namespace Modules\DataImport\Application\UseCases;

use App\Models\Patient;
use App\Models\PatientImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\DataImport\Application\DTOs\ImportResult;
use Modules\DataImport\Domain\Contracts\ImporterInterface;
use Modules\DataImport\Domain\Entities\CsvRow;
use Modules\DataImport\Domain\Services\RowSanitizer;
use Modules\DataImport\Domain\Services\ValidatesImportHeaders;
use Modules\DataImport\Infrastructure\Services\ZipImageExtractor;

/**
 * Importa imágenes de paciente.
 *
 * Columnas: nif_o_email, imagen_1..imagen_n (nombres de archivo dentro del ZIP
 * adjunto). Se aplican las mismas reglas que en el cargador manual: máximo 6
 * archivos por paciente y 200 KB por archivo.
 *
 * La validación de cada fila es "todo o nada": si un archivo de la fila falla,
 * no se importa ninguno de esa fila.
 */
final class ImportPatientImagesCommand implements ImporterInterface
{
    use ValidatesImportHeaders;

    private const MAX_IMAGE_FILES = 10;

    public function import(array $rows, array $context = []): ImportResult
    {
        $clinicId = (int) ($context['clinic_id'] ?? 0);
        $zipPath = (string) ($context['zip_path'] ?? '');

        $result = new ImportResult();
        $result->setEntity('patient-images');

        $this->ensureHeaders($rows, [
            'nif_o_email' => ['nif', 'email', 'dni', 'documento', 'correo'],
            'imagen_1' => ['imagen1', 'imagen'],
        ]);

        if ($zipPath === '' || ! is_file($zipPath)) {
            throw new \Modules\DataImport\Domain\Exceptions\InvalidCsvException('No se recibió el fichero ZIP de imágenes.');
        }

        $extractor = app(ZipImageExtractor::class);
        $files = $extractor->extract($zipPath);

        $allowedExtensions = (array) config('dataimport.images.allowed_extensions', []);
        $allowedMimes = (array) config('dataimport.images.allowed_mimes', []);
        $maxKb = (int) config('dataimport.images.max_kb', 200);
        $maxPerPatient = (int) config('dataimport.images.max_per_patient', 6);

        foreach ($rows as $row) {
            $result->countRow();

            $this->importRow(
                row: $row,
                clinicId: $clinicId,
                result: $result,
                filesByBasename: $files,
                allowedExtensions: $allowedExtensions,
                allowedMimes: $allowedMimes,
                maxKb: $maxKb,
                maxPerPatient: $maxPerPatient,
            );
        }

        return $result;
    }

    /**
     * @param array<string, array{content:string, size:int, extension:string}> $filesByBasename
     * @param list<string> $allowedExtensions
     * @param list<string> $allowedMimes
     */
    private function importRow(
        CsvRow $row,
        int $clinicId,
        ImportResult $result,
        array $filesByBasename,
        array $allowedExtensions,
        array $allowedMimes,
        int $maxKb,
        int $maxPerPatient,
    ): void {
        $identity = RowSanitizer::string($row->first(['nif_o_email']), 255);

        if ($identity === null) {
            $result->error($row->number, 'Falta el identificador del paciente (nif_o_email).');

            return;
        }

        // Recopilar los nombres de archivo referenciados en la fila.
        $references = [];
        for ($i = 1; $i <= self::MAX_IMAGE_FILES; $i++) {
            $value = $row->first(['imagen_' . $i, 'imagen' . $i]);
            if ($value !== null && trim((string) $value) !== '') {
                $references[] = trim((string) $value);
            }
        }

        if ($references === []) {
            $result->error($row->number, 'No se indicó ninguna imagen (imagen_1..imagen_n).');

            return;
        }

        $patient = $this->resolvePatient($identity, $clinicId);

        if (! $patient) {
            $result->error($row->number, 'No se encontró un paciente con ese NIF o email en la clínica.');

            return;
        }

        // Normalizar referencias: quitar rutas, comparar por basename.
        $readyFiles = [];
        foreach ($references as $reference) {
            $basename = basename(str_replace('\\', '/', $reference));

            if (! isset($filesByBasename[$basename])) {
                $result->error($row->number, "El archivo «{$basename}» no está en el fichero ZIP.");

                return;
            }

            $file = $filesByBasename[$basename];

            if (! in_array($file['extension'], $allowedExtensions, true)) {
                $result->error($row->number, "El archivo «{$basename}» no tiene una extensión permitida.");

                return;
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($file['content']);

            if (! in_array($mime, $allowedMimes, true)) {
                $result->error($row->number, "El archivo «{$basename}» no es una imagen válida.");

                return;
            }

            if ($file['size'] > $maxKb * 1024) {
                $result->error($row->number, "El archivo «{$basename}» supera el tamaño máximo de {$maxKb} KB.");

                return;
            }

            $readyFiles[] = [$basename, $file];
        }

        // Límite por paciente (contando las imágenes ya existentes).
        $existingCount = PatientImage::query()
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patient->id)
            ->count();

        if ($existingCount + count($readyFiles) > $maxPerPatient) {
            $result->error($row->number, "El paciente supera el máximo de {$maxPerPatient} imágenes.");

            return;
        }

        foreach ($readyFiles as [$basename, $file]) {
            $storedName = (string) Str::uuid() . '_' . $basename;
            Storage::disk('public')->put("patients/{$patient->id}/images/{$storedName}", $file['content']);

            PatientImage::create([
                'clinic_id' => $clinicId,
                'patient_id' => $patient->id,
                'description' => 'Importación CSV',
                'path' => "patients/{$patient->id}/images/{$storedName}",
                'mime_type' => $file['extension'] === 'jpg' ? 'image/jpeg' : "image/{$file['extension']}",
                'size_bytes' => $file['size'],
            ]);
        }

        $result->created();
    }

    private function resolvePatient(string $identity, int $clinicId): ?Patient
    {
        $nif = RowSanitizer::normalizeNif($identity);
        $email = filter_var($identity, FILTER_VALIDATE_EMAIL)
            ? strtolower($identity)
            : null;

        return Patient::query()
            ->where('clinic_id', $clinicId)
            ->where(function ($q) use ($nif, $email) {
                if ($nif !== null) {
                    $q->where('nif', $nif);
                }
                if ($email !== null) {
                    $q->orWhere('email', $email);
                }
            })
            ->first();
    }
}