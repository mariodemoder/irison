<?php

declare(strict_types=1);

namespace Modules\DataImport\Domain\Exceptions;

use RuntimeException;

/**
 * El CSV no se puede procesar (vacío, ilegible, sin encabezados, codificación
 * no soportada) o el ZIP adjunto es inválido.
 */
class InvalidCsvException extends RuntimeException
{
}