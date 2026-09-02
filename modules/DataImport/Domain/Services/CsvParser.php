<?php

declare(strict_types=1);

namespace Modules\DataImport\Domain\Services;

use Modules\DataImport\Domain\Entities\CsvRow;
use Modules\DataImport\Domain\Exceptions\InvalidCsvException;

/**
 * Parser CSV tolerante al formato emitido por Excel en español:
 *  - admite delimitadores `;`, `,` y tabulador (auto-detección);
 *  - elimina el BOM UTF-8 y transcodifica ISO-8859-1/Windows-1252 a UTF-8;
 *  - normaliza los encabezados a claves canónicas (minúsculas, sin acentos,
 *    espacios a `_`).
 *
 * Devuelve un array de {@see CsvRow} o lanza {@see InvalidCsvException}.
 */
final class CsvParser
{
    private const DELIMITER_CANDIDATES = [';', ',', "\t"];

    /**
     * @return list<CsvRow>
     *
     * @throws InvalidCsvException
     */
    public function parse(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidCsvException('El fichero CSV no existe o no es legible.');
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            throw new InvalidCsvException('El fichero CSV está vacío.');
        }

        $content = $this->normalizeContent($raw);
        $delimiter = $this->detectDelimiter($content);

        return $this->rowsFromString($content, $delimiter);
    }

    private function normalizeContent(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $converted = mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
            if ($converted === false || $converted === '') {
                throw new InvalidCsvException('No se pudo decodificar el fichero CSV (codificación no soportada).');
            }

            $raw = $converted;
        }

        return $raw;
    }

    private function detectDelimiter(string $raw): string
    {
        $lines = preg_split('/\R/', substr($raw, 0, 4096)) ?: [];
        $counts = array_fill_keys(self::DELIMITER_CANDIDATES, 0);

        foreach (array_slice($lines, 0, 5) as $line) {
            foreach (self::DELIMITER_CANDIDATES as $delimiter) {
                $counts[$delimiter] += substr_count($line, $delimiter);
            }
        }

        arsort($counts);

        $best = (string) array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    /**
     * @return list<CsvRow>
     *
     * @throws InvalidCsvException
     */
    private function rowsFromString(string $raw, string $delimiter): array
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new InvalidCsvException('No se pudo abrir el fichero CSV.');
        }

        try {
            fwrite($handle, $raw);
            rewind($handle);

            /** @var list<string>|null $header */
            $header = null;
            /** @var list<CsvRow> $rows */
            $rows = [];
            $lineNumber = 0;

            while (($fields = fgetcsv($handle, 0, $delimiter)) !== false) {
                $lineNumber++;

                /** @var list<string> $fields */
                $clean = array_map(static fn ($value) => trim((string) $value), $fields);

                if ($header === null) {
                    if ($this->isEmptyLine($clean)) {
                        continue;
                    }

                    $header = $this->normalizeHeader($clean);

                    if ($header === []) {
                        throw new InvalidCsvException('No se detectaron encabezados en el fichero CSV.');
                    }

                    continue;
                }

                if ($this->isEmptyLine($clean)) {
                    continue;
                }

                $count = count($header);
                $clean = array_slice($clean, 0, $count);
                $clean = array_pad($clean, $count, '');

                /** @var array<string, string> $rowData */
                $rowData = array_combine($header, $clean) ?: [];
                $rows[] = new CsvRow($lineNumber, $rowData);
            }

            if ($header === null) {
                throw new InvalidCsvException('No se detectó una fila de encabezados en el fichero CSV.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<string> $fields
     */
    private function isEmptyLine(array $fields): bool
    {
        foreach ($fields as $field) {
            if ($field !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $header
     *
     * @return list<string>
     */
    private function normalizeHeader(array $header): array
    {
        $keys = [];
        $seen = [];

        foreach ($header as $column) {
            if ($column === '') {
                continue;
            }

            $key = $this->canonicalKey($column);
            if ($key === '') {
                continue;
            }

            // Evitar duplicados tras normalizar (última columna gana).
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $keys[] = $key;
        }

        return $keys;
    }

    private function canonicalKey(string $column): string
    {
        $key = strtolower(trim($column));

        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $key);
        if (is_string($translit) && $translit !== '') {
            $key = $translit;
        }

        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? '';
        $key = trim($key, '_');

        return $key;
    }
}