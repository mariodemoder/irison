<?php

declare(strict_types=1);

namespace Modules\DataImport\Infrastructure\Services;

use Modules\DataImport\Domain\Exceptions\InvalidCsvException;
use ZipArchive;

/**
 * Extrae el contenido de un ZIP de imágenes de forma segura.
 *
 * Rechaza rutas con `..`, rutas absolutas, entradas de directorio y devuelve
 * un mapa nombre => datos binarios del archivo (basename, colisión de nombres
 * → gana la última entrada).
 */
final class ZipImageExtractor
{
    /**
     * @return array<string, array{content:string, size:int, extension:string}>
     *
     * @throws InvalidCsvException
     */
    public function extract(string $zipPath): array
    {
        $zip = new ZipArchive();

        if (! is_file($zipPath) || $zip->open($zipPath) !== true) {
            throw new InvalidCsvException('No se pudo abrir el fichero ZIP.');
        }

        try {
            /** @var array<string, array{content:string, size:int, extension:string}> $files */
            $files = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false) {
                    continue;
                }

                $basename = $this->safeBasename($name);
                if ($basename === null) {
                    continue;
                }

                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    continue;
                }

                $files[$basename] = [
                    'content' => $content,
                    'size' => strlen($content),
                    'extension' => strtolower((string) pathinfo($basename, PATHINFO_EXTENSION)),
                ];
            }

            return $files;
        } finally {
            $zip->close();
        }
    }

    private function safeBasename(string $name): ?string
    {
        $name = str_replace('\\', '/', $name);

        if ($name === '') {
            return null;
        }

        // Rechazar navegación fuera del zip y rutas absolutas.
        if (str_contains($name, '..')) {
            return null;
        }

        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:\//', $name) === 1) {
            return null;
        }

        $basename = basename($name);

        if ($basename === '' || $basename === '.' || $basename === '..') {
            return null;
        }

        return $basename;
    }
}