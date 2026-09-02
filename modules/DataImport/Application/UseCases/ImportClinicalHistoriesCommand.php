<?php

declare(strict_types=1);

namespace Modules\DataImport\Application\UseCases;

use App\Models\Appointment;
use App\Models\Patient;
use Modules\DataImport\Application\DTOs\ImportResult;
use Modules\DataImport\Domain\Contracts\ImporterInterface;
use Modules\DataImport\Domain\Entities\CsvRow;
use Modules\DataImport\Domain\Services\RowSanitizer;
use Modules\DataImport\Domain\Services\ValidatesImportHeaders;

/**
 * Importa historias clínicas como una cita inicial "completada" del paciente.
 *
 * Columnas: nif_o_email, fecha (opcional, defecto hoy), historia (obligatoria).
 *
 * Cada paciente solo recibe una cita importada (idempotencia por
 * booking_source = 'import'): si ya existe, la fila se omite con warning.
 */
final class ImportClinicalHistoriesCommand implements ImporterInterface
{
    use ValidatesImportHeaders;

    public function import(array $rows, array $context = []): ImportResult
    {
        $clinicId = (int) ($context['clinic_id'] ?? 0);

        $result = new ImportResult();
        $result->setEntity('clinical-histories');

        $this->ensureHeaders($rows, [
            'nif_o_email' => ['nif', 'email', 'dni', 'documento', 'correo'],
            'fecha' => ['fecha_historia', 'fecha_inicial'],
            'historia' => ['historia_clinica', 'historia_clínica', 'nota', 'notas'],
        ]);

        foreach ($rows as $row) {
            $result->countRow();

            $this->importRow($row, $clinicId, $result);
        }

        return $result;
    }

    private function importRow(CsvRow $row, int $clinicId, ImportResult $result): void
    {
        $identity = RowSanitizer::string($row->first(['nif_o_email']), 255);

        if ($identity === null) {
            $result->error($row->number, 'Falta el identificador del paciente (nif_o_email).');

            return;
        }

        $history = RowSanitizer::string($row->first(['historia', 'historia_clinica', 'historia_clínica', 'nota', 'notas']), 50000);

        if ($history === null) {
            $result->error($row->number, 'Falta el texto de la historia clínica.');

            return;
        }

        $patient = $this->resolvePatient($identity, $clinicId);

        if (! $patient) {
            $result->error($row->number, 'No se encontró un paciente con ese NIF o email en la clínica.');

            return;
        }

        $alreadyImported = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->where('patient_id', $patient->id)
            ->where('booking_source', (string) config('dataimport.clinical_history.booking_source', 'import'))
            ->exists();

        if ($alreadyImported) {
            $result->skipped();
            $result->warning($row->number, 'El paciente ya tiene historia clínica importada. Se omite.');

            return;
        }

        $date = RowSanitizer::dateYmd($row->first(['fecha', 'fecha_historia', 'fecha_inicial'])) ?? now()->toDateString();
        $start = $date . ' 09:00:00';

        Appointment::create([
            'clinic_id' => $clinicId,
            'patient_id' => $patient->id,
            'start_time' => $start,
            'end_time' => $date . ' 09:30:00',
            'status' => (string) config('dataimport.clinical_history.default_status', 'completed'),
            'payment_status' => 'pending',
            'price' => (float) config('dataimport.clinical_history.price', 0),
            'notes' => $history,
            'booking_source' => (string) config('dataimport.clinical_history.booking_source', 'import'),
            'booking_notes' => 'Historia clínica importada por CSV',
        ]);

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