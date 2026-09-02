<?php

declare(strict_types=1);

namespace Modules\DataImport\Application\UseCases;

use App\Models\Patient;
use App\Rules\ValidateNIFFormat;
use App\Rules\ValidatePhoneFormat;
use Modules\DataImport\Domain\Contracts\ImporterInterface;
use Modules\DataImport\Domain\Entities\CsvRow;
use Modules\DataImport\Domain\Services\RowSanitizer;
use Modules\DataImport\Domain\Services\ValidatesImportHeaders;
use Modules\DataImport\Application\DTOs\ImportResult;

/**
 * Importa pacientes (1 fila = 1 paciente).
 *
 * Columnas: nombre, nif, email, telefono, fecha_nacimiento, direccion, cp,
 * poblacion, provincia, pais, observaciones.
 *
 * Se requieren NIF o email por fila. La deduplicación funciona por NIF o email
 * (dentro del fichero y contra la clínica); las filas duplicadas se omiten.
 */
final class ImportPatientsCommand implements ImporterInterface
{
    use ValidatesImportHeaders;

    public function import(array $rows, array $context = []): ImportResult
    {
        $clinicId = (int) ($context['clinic_id'] ?? 0);

        $result = new ImportResult();
        $result->setEntity('patients');

        $this->ensureHeaders($rows, [
            'nombre' => ['nombre_completo', 'name'],
            'nif' => ['dni', 'documento'],
            'email' => ['correo', 'correo_electronico'],
        ]);

        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($rows as $row) {
            $result->countRow();

            $this->importRow($row, $clinicId, $result, $seen);
        }

        return $result;
    }

    /**
     * @param array<string, true>            $seen
     */
    private function importRow(CsvRow $row, int $clinicId, ImportResult $result, array &$seen): void
    {
        $name = RowSanitizer::string($row->first(['nombre', 'nombre_completo', 'name']), 255);
        $nif = RowSanitizer::normalizeNif($row->first(['nif', 'dni', 'documento']));
        $email = RowSanitizer::normalizeEmail($row->first(['email', 'correo', 'correo_electronico']));

        if ($name === null) {
            $result->error($row->number, 'Falta el nombre del paciente.');

            return;
        }

        if ($nif === null && $email === null) {
            $result->error($row->number, 'Se requiere al menos NIF o email.');

            return;
        }

        if ($nif !== null && ! $this->validNif($nif)) {
            $result->error($row->number, 'El NIF no tiene un formato válido.');

            return;
        }

        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result->error($row->number, 'El email no tiene un formato válido.');

            return;
        }

        $phone = RowSanitizer::string($row->first(['telefono', 'tfno', 'phone']), 30);
        if ($phone !== null && ! $this->validPhone($phone)) {
            $result->error($row->number, 'El teléfono no tiene un formato válido.');

            return;
        }

        $key = $nif !== null ? 'nif:' . $nif : 'email:' . strtolower((string) $email);
        if (isset($seen[$key])) {
            $result->skipped();
            $result->warning($row->number, 'Paciente duplicado en el fichero. Se omite.');

            return;
        }
        $seen[$key] = true;

        $exists = Patient::query()
            ->where('clinic_id', $clinicId)
            ->where(function ($q) use ($nif, $email) {
                if ($nif !== null) {
                    $q->where('nif', $nif);
                }
                if ($email !== null) {
                    $q->orWhere('email', $email);
                }
            })
            ->exists();

        if ($exists) {
            $result->skipped();
            $result->warning($row->number, 'El paciente ya existe en la clínica. Se omite.');

            return;
        }

        [$firstName, $lastName] = $this->splitName($name);

        Patient::create([
            'clinic_id' => $clinicId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'nif' => $nif,
            'email' => $email,
            'phone' => $phone,
            'birth_date' => RowSanitizer::dateYmd($row->first(['fecha_nacimiento', 'nacimiento'])),
            'address' => RowSanitizer::string($row->first(['direccion', 'address']), 255),
            'zip' => RowSanitizer::string($row->first(['cp', 'codigo_postal', 'zip']), 10),
            'city' => RowSanitizer::string($row->first(['poblacion', 'ciudad', 'city']), 100),
            'province' => RowSanitizer::string($row->first(['provincia', 'province']), 100),
            'country' => RowSanitizer::string($row->first(['pais', 'country']), 100),
            'notes' => RowSanitizer::string($row->first(['observaciones', 'notas', 'notes']), 5000),
            'status' => (string) config('dataimport.patient_status_default', 'active'),
        ]);

        $result->created();
    }

    /**
     * "Apellido, Nombre" y "Nombre Apellidos" → [first_name, last_name].
     *
     * @return array{0:string, 1:string}
     */
    private function splitName(string $name): array
    {
        if (str_contains($name, ',')) {
            [$last, $first] = array_pad(explode(',', $name, 2), 2, '');

            return [trim($first), trim($last)];
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        $first = (string) array_shift($parts);
        $last = implode(' ', $parts);

        return [$first, $last];
    }

    private function validNif(string $nif): bool
    {
        $invalid = null;
        (new ValidateNIFFormat())->validate('nif', $nif, function (string $message) use (&$invalid) {
            $invalid = $message;
        });

        return $invalid === null;
    }

    private function validPhone(string $phone): bool
    {
        $invalid = null;
        (new ValidatePhoneFormat())->validate('telefono', $phone, function (string $message) use (&$invalid) {
            $invalid = $message;
        });

        return $invalid === null;
    }
}