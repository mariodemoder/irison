<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppointmentType;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PatientImage;
use App\Models\Product;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Bonus\Models\BonusType;
use Modules\DataImport\Application\UseCases\ImportBonusTypesCommand;
use Modules\DataImport\Application\UseCases\ImportClinicalHistoriesCommand;
use Modules\DataImport\Application\UseCases\ImportPatientImagesCommand;
use Modules\DataImport\Application\UseCases\ImportPatientsCommand;
use Modules\DataImport\Application\UseCases\ImportProductsCommand;
use Modules\DataImport\Application\UseCases\ImportSessionTypesCommand;
use Modules\DataImport\Domain\Services\CsvParser;

/**
 * Comando de demostración: importa los CSVs de fixtures en la clínica indicada.
 *
 * Uso:
 *   php artisan dataimport:demo 7
 *   php artisan dataimport:demo 7 --skip-images
 *   php artisan dataimport:demo 7 --dry-run
 *
 * Los fixtures se encuentran en tests/fixtures/import/.
 * El comando es idempotente: re-ejecutarlo salta registros existentes.
 */
class DemoImportData extends Command
{
    protected $signature = 'dataimport:demo {clinic=7} {--skip-images} {--dry-run}';

    protected $description = 'Importa los CSVs de demostración (tests/fixtures) en la clínica indicada.';

    /** @var array<string, class-string> */
    private const ENTITY_IMPORTERS = [
        'session-types' => ImportSessionTypesCommand::class,
        'patients' => ImportPatientsCommand::class,
        'products' => ImportProductsCommand::class,
        'bonus-types' => ImportBonusTypesCommand::class,
        'clinical-histories' => ImportClinicalHistoriesCommand::class,
        'patient-images' => ImportPatientImagesCommand::class,
    ];

    private const ENTITY_FILES = [
        'session-types' => 'session-types.csv',
        'patients' => 'patients.csv',
        'products' => 'products.csv',
        'bonus-types' => 'bonus-types.csv',
        'clinical-histories' => 'clinical-histories.csv',
        'patient-images' => 'patient-images.csv',
    ];

    public function handle(): int
    {
        $clinicId = (int) $this->argument('clinic');
        $dryRun = $this->option('dry-run');
        $skipImages = $this->option('skip-images');

        // ── Resolve clinic ──────────────────────────────────────────────
        $clinic = Clinic::find($clinicId);

        if (! $clinic) {
            $this->error("Clínica #{$clinicId} no encontrada.");

            return self::FAILURE;
        }

        if (! $clinic->hasProFeatures()) {
            $this->error("La clínica «{$clinic->name}» no tiene plan PRO/Enterprise. Importación no permitida.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════════════");
        $this->info("  DEMO IMPORT — Clínica #{$clinic->id}: {$clinic->name}");
        $this->info("  Plan: {$clinic->plan} | Estado: {$clinic->status} | Sub: {$clinic->subscription_status}");
        if ($dryRun) {
            $this->warn('  ⚠  MODO DRY-RUN: no se escribirá ningún dato.');
        }
        $this->info("═══════════════════════════════════════════════════════════════");
        $this->newLine();

        // ── Set tenant context (mirrors EnsureClinic middleware) ────────
        app()->instance('activeClinic', $clinic);

        // ── Resolve audit user (owner or first admin) ──────────────────
        $auditUser = User::where('clinic_id', $clinic->id)
            ->where('role', 'owner')
            ->first();

        if (! $auditUser) {
            $auditUser = User::where('clinic_id', $clinic->id)->first();
        }

        if (! $auditUser) {
            $this->error('No se encontró ningún usuario en la clínica para auditoría.');

            return self::FAILURE;
        }

        $fixturesPath = base_path('tests/fixtures/import');
        $parser = app(CsvParser::class);
        $skipEntities = $skipImages ? ['patient-images'] : [];

        // ── Count before ───────────────────────────────────────────────
        $beforeCounts = $this->snapshotCounts($clinic->id);

        $this->comment('Estado previo:');
        $this->table(['Entidad', 'Antes'], $beforeCounts);
        $this->newLine();

        // ── Import pipeline ────────────────────────────────────────────
        $summaryRows = [];
        $totalCreated = 0;
        $totalErrors = 0;

        foreach (self::ENTITY_IMPORTERS as $entity => $importerClass) {
            if (in_array($entity, $skipEntities, true)) {
                $summaryRows[] = ['patient-images', '—', '—', '—', '—', '⏭  Saltado (--skip-images)'];
                $this->line("  [SKIP] {$entity}: saltado por opción --skip-images");

                continue;
            }

            $csvPath = $fixturesPath . '/' . self::ENTITY_FILES[$entity];

            if (! is_file($csvPath)) {
                $this->error("  [ERR] {$entity}: fixture no encontrado en {$csvPath}");
                $totalErrors++;

                continue;
            }

            $this->line("  [{$entity}] Parseando CSV...");

            try {
                $rows = $parser->parse($csvPath);
            } catch (\Throwable $e) {
                $this->error("  [ERR] {$entity}: {$e->getMessage()}");
                $totalErrors++;

                continue;
            }

            $context = [
                'clinic_id' => $clinic->id,
                'user_id' => $auditUser->id,
            ];

            if ($entity === 'patient-images') {
                $zipPath = $fixturesPath . '/patient-images.zip';
                if (! is_file($zipPath)) {
                    $this->error("  [ERR] {$entity}: ZIP no encontrado en {$zipPath}");
                    $totalErrors++;

                    continue;
                }
                $context['zip_path'] = $zipPath;
            }

            if ($dryRun) {
                $this->info("  [{$entity}] Dry-run: se importarían " . count($rows) . " filas.");

                $summaryRows[] = [
                    $entity,
                    count($rows),
                    count($rows),
                    0,
                    0,
                    '🔎 Dry-run (sin errores de formato)',
                ];

                continue;
            }

            $this->line("  [{$entity}] Importando " . count($rows) . " filas...");

            try {
                $result = DB::transaction(function () use ($importerClass, $rows, $context) {
                    return app($importerClass)->import($rows, $context);
                });
            } catch (\Throwable $e) {
                $this->error("  [ERR] {$entity}: {$e->getMessage()}");
                $totalErrors++;

                continue;
            }

            // ── Activity log (mirrors ImportController) ────────────────
            ActivityLogger::log(
                tenantId: $clinic->id,
                userId: $auditUser->id,
                event: 'dataimport.completed',
                description: "Importación demo completada ({$entity})",
                metadata: [
                    'entity' => $entity,
                    'total' => $result->total,
                    'created' => $result->created,
                    'skipped' => $result->skipped,
                    'errors' => count($result->errors),
                    'warnings' => count($result->warnings),
                    'demo' => true,
                ],
            );

            $statusIcon = $result->errors === [] ? '✅' : '⚠️';
            $statusMsg = $result->errors === []
                ? 'OK'
                : count($result->errors) . ' errores';

            $summaryRows[] = [
                $entity,
                $result->total,
                $result->created,
                $result->skipped,
                count($result->errors),
                "{$statusIcon} {$statusMsg}",
            ];

            $totalCreated += $result->created;

            if ($result->errors !== []) {
                $totalErrors += count($result->errors);
                foreach ($result->errors as $err) {
                    $this->warn("    Row {$err['row']}: {$err['message']}");
                }
            }

            if ($result->warnings !== []) {
                foreach ($result->warnings as $warn) {
                    $this->line("    ⚡ Row {$warn['row']}: {$warn['message']}");
                }
            }
        }

        // ── Summary table ──────────────────────────────────────────────
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('  RESUMEN DE IMPORTACIÓN');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->table(
            ['Entidad', 'Total', 'Creados', 'Saltados', 'Errores', 'Estado'],
            $summaryRows,
        );

        // ── Count after ────────────────────────────────────────────────
        $afterCounts = $this->snapshotCounts($clinic->id);

        $this->newLine();
        $this->comment('Estado post-importación:');
        $this->table(
            ['Entidad', 'Antes', 'Después', 'Diferencia'],
            array_map(function ($before, $after) {
                return [$before[0], $before[1], $after[1], $after[1] - $before[1]];
            }, $beforeCounts, $afterCounts),
        );

        $this->newLine();
        if ($dryRun) {
            $this->info("  📋 Dry-run completado. No se modificó ningún dato.");
        } else {
            $this->info("  ✅ Total creados: {$totalCreated}");
            if ($totalErrors > 0) {
                $this->warn("  ⚠  Total errores: {$totalErrors}");
            }
            $this->info("  📝 6 eventos dataimport.completed registrados en activity_logs.");
            $this->info("  👁  Los datos son visibles en la app (Pacientes, Productos, Servicios, Bonos, Historial).");
        }
        $this->newLine();

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<array{0:string, 1:int}>
     */
    private function snapshotCounts(int $clinicId): array
    {
        return [
            ['Pacientes', Patient::where('clinic_id', $clinicId)->count()],
            ['Productos', Product::where('clinic_id', $clinicId)->count()],
            ['Tipos de sesión', AppointmentType::where('clinic_id', $clinicId)->count()],
            ['Tipos de bono', BonusType::where('clinic_id', $clinicId)->count()],
            ['Citas importadas', \App\Models\Appointment::where('clinic_id', $clinicId)->where('booking_source', 'import')->count()],
            ['Imágenes', PatientImage::where('clinic_id', $clinicId)->count()],
        ];
    }
}
