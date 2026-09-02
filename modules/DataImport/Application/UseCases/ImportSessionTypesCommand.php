<?php

declare(strict_types=1);

namespace Modules\DataImport\Application\UseCases;

use App\Models\AppointmentType;
use Modules\DataImport\Application\DTOs\ImportResult;
use Modules\DataImport\Domain\Contracts\ImporterInterface;
use Modules\DataImport\Domain\Entities\CsvRow;
use Modules\DataImport\Domain\Services\RowSanitizer;
use Modules\DataImport\Domain\Services\ValidatesImportHeaders;

/**
 * Importa tipos de sesión (cesiones / AppointmentType).
 *
 * Columnas: nombre, horas_estimadas, minutos_estimados, precio, color.
 *
 * La deduplicación funciona por descripción dentro de la clínica.
 */
final class ImportSessionTypesCommand implements ImporterInterface
{
    use ValidatesImportHeaders;

    public function import(array $rows, array $context = []): ImportResult
    {
        $clinicId = (int) ($context['clinic_id'] ?? 0);

        $result = new ImportResult();
        $result->setEntity('session-types');

        $this->ensureHeaders($rows, [
            'nombre' => ['name', 'tipo', 'descripcion'],
            'horas_estimadas' => ['horas'],
            'minutos_estimados' => ['minutos'],
            'precio' => ['price'],
            'color' => [],
        ]);

        foreach ($rows as $row) {
            $result->countRow();

            $this->importRow($row, $clinicId, $result);
        }

        return $result;
    }

    private function importRow(CsvRow $row, int $clinicId, ImportResult $result): void
    {
        $description = RowSanitizer::string($row->first(['nombre', 'name', 'tipo', 'descripcion']), 255);

        if ($description === null) {
            $result->error($row->number, 'Falta el nombre del tipo de sesión.');

            return;
        }

        $rawColor = RowSanitizer::string($row->first(['color']));
        $color = RowSanitizer::color($rawColor);

        if ($rawColor !== null && $color === null) {
            $result->error($row->number, 'El color no tiene un formato válido (#RGB o #RRGGBB).');

            return;
        }

        $exists = AppointmentType::query()
            ->where('clinic_id', $clinicId)
            ->where('description', $description)
            ->exists();

        if ($exists) {
            $result->skipped();
            $result->warning($row->number, 'El tipo de sesión ya existe en la clínica. Se omite.');

            return;
        }

        AppointmentType::create([
            'clinic_id' => $clinicId,
            'description' => $description,
            'estimated_hours' => max(RowSanitizer::int($row->first(['horas_estimadas', 'horas'])) ?? 0, 0),
            'estimated_minutes' => max(RowSanitizer::int($row->first(['minutos_estimados', 'minutos'])) ?? 60, 0),
            'price' => max(RowSanitizer::float($row->first(['precio', 'price'])) ?? 0, 0),
            'color' => $color,
        ]);

        $result->created();
    }
}