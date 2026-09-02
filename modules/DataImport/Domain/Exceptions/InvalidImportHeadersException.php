<?php

declare(strict_types=1);

namespace Modules\DataImport\Domain\Exceptions;

use RuntimeException;

/**
 * El CSV no contiene las columnas obligatorias para la entidad importada.
 */
class InvalidImportHeadersException extends RuntimeException
{
}