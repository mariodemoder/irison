<?php

declare(strict_types=1);

namespace Modules\DataImport\Application\UseCases;

use App\Models\Product;
use Modules\DataImport\Application\DTOs\ImportResult;
use Modules\DataImport\Domain\Contracts\ImporterInterface;
use Modules\DataImport\Domain\Entities\CsvRow;
use Modules\DataImport\Domain\Services\RowSanitizer;
use Modules\DataImport\Domain\Services\ValidatesImportHeaders;

/**
 * Importa productos.
 *
 * Columnas: referencia, nombre, precio_venta, precio_compra, iva_venta,
 * iva_compra, familia, lote.
 *
 * La deduplicación funciona por referencia dentro de la clínica.
 */
final class ImportProductsCommand implements ImporterInterface
{
    use ValidatesImportHeaders;

    public function import(array $rows, array $context = []): ImportResult
    {
        $clinicId = (int) ($context['clinic_id'] ?? 0);

        $result = new ImportResult();
        $result->setEntity('products');

        $this->ensureHeaders($rows, [
            'referencia' => ['reference', 'codigo'],
            'nombre' => ['name'],
            'precio_venta' => ['precio', 'price'],
            'precio_compra' => ['coste', 'cost'],
            'iva_venta' => ['iva'],
            'iva_compra' => [],
            'familia' => ['family', 'categoria'],
            'lote' => ['lot', 'stock'],
        ]);

        foreach ($rows as $row) {
            $result->countRow();

            $this->importRow($row, $clinicId, $result);
        }

        return $result;
    }

    private function importRow(CsvRow $row, int $clinicId, ImportResult $result): void
    {
        $reference = RowSanitizer::string($row->first(['referencia', 'reference', 'codigo']), 100);
        $name = RowSanitizer::string($row->first(['nombre', 'name']), 255);

        if ($reference === null || $name === null) {
            $result->error($row->number, 'Faltan la referencia o el nombre del producto.');

            return;
        }

        $exists = Product::query()
            ->where('clinic_id', $clinicId)
            ->where('reference', $reference)
            ->exists();

        if ($exists) {
            $result->skipped();
            $result->warning($row->number, 'El producto ya existe en la clínica. Se omite.');

            return;
        }

        Product::create([
            'clinic_id' => $clinicId,
            'reference' => $reference,
            'name' => $name,
            'sale_price' => RowSanitizer::float($row->first(['precio_venta', 'precio', 'price'])) ?? 0,
            'purchase_price' => RowSanitizer::float($row->first(['precio_compra', 'coste', 'cost'])) ?? 0,
            'sale_tax' => RowSanitizer::float($row->first(['iva_venta', 'iva'])) ?? 0,
            'purchase_tax' => RowSanitizer::float($row->get('iva_compra')) ?? 0,
            'family' => RowSanitizer::string($row->first(['familia', 'family', 'categoria']), 100),
            'lot' => RowSanitizer::string($row->first(['lote', 'lot', 'stock']), 100),
        ]);

        $result->created();
    }
}