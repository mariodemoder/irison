<?php

declare(strict_types=1);

namespace Modules\DataImport\Domain\Services;

use Modules\DataImport\Domain\Entities\CsvRow;
use Modules\DataImport\Domain\Exceptions\InvalidCsvException;
use Modules\DataImport\Domain\Exceptions\InvalidImportHeadersException;

/**
 * Trait para validar que un conjunto de filas contiene las columnas
 * obligatorias de la entidad (alias permitidos además de la clave canónica).
 */
trait ValidatesImportHeaders
{
    /**
     * Comprueba que la primera fila de datos incluya las columnas requeridas.
     *
     * @param  list<CsvRow>                  $rows
     * @param  array<string, list<string>>   $required  Clave canónica => alias opcionales.
     *
     * @throws InvalidCsvException
     * @throws InvalidImportHeadersException
     */
    protected function ensureHeaders(array $rows, array $required): void
    {
        $sample = $rows[0] ?? null;

        if ($sample === null) {
            throw new InvalidCsvException('El fichero no contiene filas de datos.');
        }

        foreach ($required as $key => $aliases) {
            $candidates = array_merge([$key], $aliases);

            if (! $sample->hasAny($candidates)) {
                throw new InvalidImportHeadersException(
                    sprintf('Falta la columna obligatoria «%s» en el fichero CSV.', $key)
                );
            }
        }
    }
}