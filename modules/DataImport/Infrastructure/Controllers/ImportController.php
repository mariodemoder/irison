<?php

declare(strict_types=1);

namespace Modules\DataImport\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\DataImport\Application\UseCases\ImportBonusTypesCommand;
use Modules\DataImport\Application\UseCases\ImportClinicalHistoriesCommand;
use Modules\DataImport\Application\UseCases\ImportPatientImagesCommand;
use Modules\DataImport\Application\UseCases\ImportPatientsCommand;
use Modules\DataImport\Application\UseCases\ImportProductsCommand;
use Modules\DataImport\Application\UseCases\ImportSessionTypesCommand;
use Modules\DataImport\Domain\Contracts\ImporterInterface;
use Modules\DataImport\Domain\Exceptions\InvalidCsvException;
use Modules\DataImport\Domain\Exceptions\InvalidImportHeadersException;
use Modules\DataImport\Domain\Services\CsvParser;
use Modules\DataImport\Infrastructure\Requests\ImportCsvRequest;

/**
 * API de importación CSV (PRO/Enterprise).
 *
 *  - POST /api/imports/{entity}      → procesa el CSV (y ZIP en imágenes)
 *  - GET  /api/imports/{entity}/template → plantilla CSV descargable
 */
class ImportController extends Controller
{
    /** @var array<string, class-string<ImporterInterface>> */
    private const ENTITY_IMPORTERS = [
        'patients' => ImportPatientsCommand::class,
        'products' => ImportProductsCommand::class,
        'session-types' => ImportSessionTypesCommand::class,
        'bonus-types' => ImportBonusTypesCommand::class,
        'clinical-histories' => ImportClinicalHistoriesCommand::class,
        'patient-images' => ImportPatientImagesCommand::class,
    ];

    /** @var array<string, list<string>> */
    private const TEMPLATE_COLUMNS = [
        'patients' => ['nombre', 'nif', 'email', 'telefono', 'fecha_nacimiento', 'direccion', 'cp', 'poblacion', 'provincia', 'pais', 'observaciones'],
        'products' => ['referencia', 'nombre', 'precio_venta', 'precio_compra', 'iva_venta', 'iva_compra', 'familia', 'lote'],
        'session-types' => ['nombre', 'horas_estimadas', 'minutos_estimados', 'precio', 'color'],
        'bonus-types' => ['nombre', 'sesiones', 'precio', 'expira_el', 'linea_1', 'linea_2', 'linea_3', 'linea_4', 'linea_5', 'linea_6', 'linea_7', 'linea_8', 'linea_9'],
        'clinical-histories' => ['nif_o_email', 'fecha', 'historia'],
        'patient-images' => ['nif_o_email', 'imagen_1', 'imagen_2', 'imagen_3', 'imagen_4', 'imagen_5', 'imagen_6'],
    ];

    /**
     * Mapa entidad → fixture CSV de demostración (tests/fixtures/import/).
     * Las plantillas descargables incluyen estas filas reales como ejemplo.
     *
     * @var array<string, string>
     */
    private const TEMPLATE_FIXTURES = [
        'patients' => 'patients.csv',
        'products' => 'products.csv',
        'session-types' => 'session-types.csv',
        'bonus-types' => 'bonus-types.csv',
        'clinical-histories' => 'clinical-histories.csv',
        'patient-images' => 'patient-images.csv',
    ];

    public function import(ImportCsvRequest $request, string $entity): JsonResponse
    {
        $this->authorizeAccess();

        if (! isset(self::ENTITY_IMPORTERS[$entity])) {
            abort(404, 'Entidad de importación no encontrada.');
        }

        $user = $request->user();
        $clinicId = (int) currentClinicId();

        if (! $user || $clinicId <= 0) {
            abort(422, 'No se pudo determinar la clínica del usuario.');
        }

        $csvPath = $request->file('file')->getRealPath();
        if (! $csvPath) {
            abort(422, 'El fichero CSV no se pudo leer.');
        }

        try {
            $rows = app(CsvParser::class)->parse($csvPath);
        } catch (InvalidCsvException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $maxRows = (int) config('dataimport.max_rows', 2000);
        if (count($rows) > $maxRows) {
            return response()->json([
                'message' => "El fichero supera el límite de {$maxRows} filas.",
            ], 422);
        }

        $context = [
            'clinic_id' => $clinicId,
            'user_id' => (int) $user->id,
        ];

        if ($entity === 'patient-images') {
            $zip = $request->file('zip');
            $context['zip_path'] = $zip ? (string) $zip->getRealPath() : '';
        }

        $importer = app(self::ENTITY_IMPORTERS[$entity]);

        try {
            $result = DB::transaction(function () use ($importer, $rows, $context) {
                return $importer->import($rows, $context);
            });
        } catch (InvalidCsvException | InvalidImportHeadersException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        ActivityLogger::log(
            tenantId: $clinicId,
            userId: (int) $user->id,
            event: 'dataimport.completed',
            description: 'Importación de datos completada (' . $entity . ')',
            metadata: [
                'entity' => $entity,
                'total' => $result->total,
                'created' => $result->created,
                'skipped' => $result->skipped,
                'errors' => count($result->errors),
                'warnings' => count($result->warnings),
            ],
            ip: $request->ip(),
        );

        return response()->json(['data' => $result->toArray()]);
    }

    public function template(string $entity): Response
    {
        $this->authorizeAccess();

        $columns = self::TEMPLATE_COLUMNS[$entity] ?? null;
        if ($columns === null) {
            abort(404, 'Plantilla no encontrada.');
        }

        $rows = $this->templateExampleRows($entity, $columns);

        $csv = "\xEF\xBB\xBF" . implode(';', $columns) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(';', $row) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_' . $entity . '.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Devuelve las filas de ejemplo de la plantilla.
     *
     * Se usan los registros reales del fixture de demostración (10 filas por
     * defecto, o solo la primera si el fixture no está disponible), alineados
     * al orden de columnas de la plantilla. Si no hay fixture, se devuelve una
     * única fila de ejemplo para que la plantilla siga siendo descargable.
     *
     * @param  list<string>  $columns
     * @return list<list<string>>
     */
    private function templateExampleRows(string $entity, array $columns): array
    {
        $fixture = self::TEMPLATE_FIXTURES[$entity] ?? null;

        if ($fixture === null) {
            return [array_fill(0, count($columns), '')];
        }

        $path = base_path('tests/fixtures/import/' . $fixture);

        if (! is_file($path) || ! is_readable($path)) {
            return [array_fill(0, count($columns), '')];
        }

        try {
            $rows = app(CsvParser::class)->parse($path);
        } catch (\Throwable) {
            return [array_fill(0, count($columns), '')];
        }

        if ($rows === []) {
            return [array_fill(0, count($columns), '')];
        }

        // Convertir cada CsvRow a un array indexado según el orden de columnas
        // de la plantilla (valores vacíos si la fila no los tiene).
        $out = [];
        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = $row->get($column) ?? '';
            }
            $out[] = $line;
        }

        return $out;
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();

        if ($user->role !== 'owner' && ! in_array($user->profile?->slug, ['admin', 'manager'], true)) {
            abort(403, 'No tienes permisos para importar datos.');
        }
    }
}