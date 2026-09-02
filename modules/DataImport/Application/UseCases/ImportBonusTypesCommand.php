<?php

declare(strict_types=1);

namespace Modules\DataImport\Application\UseCases;

use App\Models\AppointmentType;
use Modules\Bonus\Models\BonusType;
use Modules\DataImport\Application\DTOs\ImportResult;
use Modules\DataImport\Domain\Contracts\ImporterInterface;
use Modules\DataImport\Domain\Entities\CsvRow;
use Modules\DataImport\Domain\Services\RowSanitizer;
use Modules\DataImport\Domain\Services\ValidatesImportHeaders;

/**
 * Importa tipos de bono (plantillas, no packs por paciente).
 *
 * Columnas: nombre, sesiones, precio, expira_el, linea_1..linea_9.
 *
 * Cada línea usa el formato "TipoDeSesion|cantidad|precio_unitario" (precio
 * opcional; sin él se usa el precio del tipo de sesión). Si el tipo de sesión
 * no existe en la clínica, la fila se marca como error.
 */
final class ImportBonusTypesCommand implements ImporterInterface
{
    use ValidatesImportHeaders;

    private const MAX_LINES = 9;

    public function import(array $rows, array $context = []): ImportResult
    {
        $clinicId = (int) ($context['clinic_id'] ?? 0);

        $result = new ImportResult();
        $result->setEntity('bonus-types');

        $this->ensureHeaders($rows, [
            'nombre' => ['name', 'descripcion'],
            'sesiones' => ['sessions'],
            'precio' => ['price'],
            'expira_el' => ['expira', 'expiration'],
            'linea_1' => ['linea1'],
        ]);

        foreach ($rows as $row) {
            $result->countRow();

            $this->importRow($row, $clinicId, $result);
        }

        return $result;
    }

    private function importRow(CsvRow $row, int $clinicId, ImportResult $result): void
    {
        $description = RowSanitizer::string($row->first(['nombre', 'name', 'descripcion']), 255);

        if ($description === null) {
            $result->error($row->number, 'Falta el nombre del tipo de bono.');

            return;
        }

        $exists = BonusType::query()
            ->where('clinic_id', $clinicId)
            ->where('description', $description)
            ->exists();

        if ($exists) {
            $result->skipped();
            $result->warning($row->number, 'El tipo de bono ya existe en la clínica. Se omite.');

            return;
        }

        $lines = $this->parseLines($row, $clinicId);
        if (isset($lines['error'])) {
            $result->error($row->number, 'Línea inválida: ' . $lines['error']);

            return;
        }

        $bonusType = BonusType::create([
            'clinic_id' => $clinicId,
            'description' => $description,
            'sessions' => max(RowSanitizer::int($row->first(['sesiones', 'sessions'])) ?? 1, 1),
            'price' => max(RowSanitizer::float($row->first(['precio', 'price'])) ?? 0, 0),
            'expires_at' => RowSanitizer::dateYmd($row->first(['expira_el', 'expira', 'expiration'])),
        ]);

        if ($lines['lines'] !== []) {
            $bonusType->appointmentTypes()->sync($lines['lines']);
        }

        $result->created();
    }

    /**
     * @return array{lines?: array<int, array{quantity:int, unit_price:float}>, error?: string}
     */
    private function parseLines(CsvRow $row, int $clinicId): array
    {
        /** @var array<int, array{appointment_type_id:int, quantity:int, unit_price:float}> $pivot */
        $pivot = [];
        /** @var array<string, int> $appointmentTypeCache */
        $appointmentTypeCache = [];

        for ($i = 1; $i <= self::MAX_LINES; $i++) {
            $value = $row->first(['linea_' . $i, 'linea' . $i]);

            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', (string) $value));
            $sessionName = $parts[0] ?? '';

            if ($sessionName === '') {
                return ['error' => "la línea {$i} no tiene nombre de tipo de sesión."];
            }

            if (! isset($appointmentTypeCache[$sessionName])) {
                $type = AppointmentType::query()
                    ->where('clinic_id', $clinicId)
                    ->where('description', $sessionName)
                    ->first();

                if (! $type) {
                    return ['error' => "el tipo de sesión «{$sessionName}» no existe en la clínica (línea {$i})."];
                }

                $appointmentTypeCache[$sessionName] = (int) $type->id;
            }

            $quantity = max(RowSanitizer::int($parts[1] ?? null) ?? 1, 1);

            $unitPrice = isset($parts[2]) && trim($parts[2]) !== ''
                ? RowSanitizer::float($parts[2])
                : null;

            if ($unitPrice === null) {
                $price = AppointmentType::query()
                    ->where('id', $appointmentTypeCache[$sessionName])
                    ->value('price');
                $unitPrice = max((float) $price, 0);
            }

            $pivot[$appointmentTypeCache[$sessionName]] = [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        return ['lines' => $pivot];
    }
}