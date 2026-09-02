<?php

declare(strict_types=1);

namespace Modules\DataImport\Domain\Contracts;

use Modules\DataImport\Application\DTOs\ImportResult;
use Modules\DataImport\Domain\Entities\CsvRow;

/**
 * Contrato de los casos de uso de importación. Cada entidad implementa su
 * propio importador con su lógica de normalización, deduplicación y creación.
 *
 * @experimental - La firma puede evolucionar con nuevos orígenes de datos.
 */
interface ImporterInterface
{
    /**
     * Procesa las filas del CSV y persiste los registros válidos.
     *
     * @param  list<CsvRow>                    $rows
     * @param  array<string, mixed>            $context  Datos de ejecución
     *                                                   (clinic_id, user_id, zip_path...)
     */
    public function import(array $rows, array $context = []): ImportResult;
}