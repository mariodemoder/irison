<?php

declare(strict_types=1);

namespace Modules\DataImport\Domain\Entities;

/**
 * Representa una fila de datos de un CSV ya normalizado (headers canónicos).
 *
 * El número de fila corresponde a la línea del fichero original (fila 1 =
 * línea de encabezados, las filas de datos empiezan en 2).
 */
final class CsvRow
{
    /**
     * @param int   $number Número de línea dentro del fichero CSV.
     * @param array<string, string> $data  Valores de la fila indexados por clave canónica.
     */
    public function __construct(
        public readonly int $number,
        public readonly array $data,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Devuelve el primer valor presente entre varias claves (soporta alias).
     */
    public function first(array $keys, mixed $default = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->data)) {
                return $this->data[$key];
            }
        }

        return $default;
    }

    /**
     * ¿La fila incluye alguna de las claves indicadas?
     */
    public function hasAny(array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->data)) {
                return true;
            }
        }

        return false;
    }
}