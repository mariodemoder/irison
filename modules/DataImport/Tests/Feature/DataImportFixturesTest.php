<?php

declare(strict_types=1);

namespace Modules\DataImport\Tests\Feature;

use App\Http\Middleware\CheckSubscriptionAccess;
use App\Http\Middleware\EnsureClinic;
use App\Http\Middleware\EnsureClinicIsActive;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Clinic;
use App\Models\PatientImage;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Bonus\Models\BonusType;
use Modules\DataImport\Application\UseCases\ImportBonusTypesCommand;
use Modules\DataImport\Application\UseCases\ImportClinicalHistoriesCommand;
use Modules\DataImport\Application\UseCases\ImportPatientImagesCommand;
use Modules\DataImport\Application\UseCases\ImportPatientsCommand;
use Modules\DataImport\Application\UseCases\ImportProductsCommand;
use Modules\DataImport\Application\UseCases\ImportSessionTypesCommand;
use Modules\DataImport\Domain\Services\CsvParser;
use Tests\TestCase;

/**
 * Test de integración end-to-end que consume los archivos CSV reales
 * ubicados en tests/fixtures/import/.
 *
 * Valida que la pipeline completa (CsvParser + Importer) procesa
 * correctamente los 10 registros por entidad sin errores.
 */
class DataImportFixturesTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;

    private User $owner;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturesPath = base_path('tests/fixtures/import');

        $this->withoutMiddleware([
            EnsureClinic::class,
            EnsureClinicIsActive::class,
            CheckSubscriptionAccess::class,
        ]);

        foreach ([
            ['name' => 'Administrador', 'slug' => 'admin'],
            ['name' => 'Gestor', 'slug' => 'manager'],
            ['name' => 'Profesional', 'slug' => 'professional'],
            ['name' => 'Recepcionista', 'slug' => 'reception'],
        ] as $profile) {
            Profile::firstOrCreate(['slug' => $profile['slug']], $profile);
        }

        $this->clinic = Clinic::create([
            'name' => 'Fixtures Test Clinic',
            'subscription_status' => 'active',
            'status' => 'active',
            'plan' => 'pro',
            'max_users' => 5,
        ]);

        $this->owner = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Owner',
            'email' => 'owner@fixtures.test',
            'password' => 'password',
            'role' => 'owner',
            'profile_id' => Profile::where('slug', 'admin')->first()->id,
        ]);

        $this->actingAs($this->owner, 'sanctum');
        app()->instance('activeClinic', $this->clinic);
    }

    private function fixtureFile(string $filename): UploadedFile
    {
        $path = $this->fixturesPath . '/' . $filename;

        if (! is_file($path)) {
            $this->fail("Fixture file not found: {$path}");
        }

        return new UploadedFile($path, $filename, 'text/plain', null, true);
    }

    /**
     * Importa un fixture CSV directamente (sin HTTP) y devuelve el ImportResult.
     */
    private function importFixture(string $entity, ?string $zipName = null): \Modules\DataImport\Application\DTOs\ImportResult
    {
        $rows = (new CsvParser())->parse($this->fixturesPath . '/' . self::ENTITY_FILES[$entity]);
        $context = [
            'clinic_id' => $this->clinic->id,
            'user_id' => $this->owner->id,
        ];

        if ($zipName !== null) {
            $context['zip_path'] = $this->fixturesPath . '/' . $zipName;
        }

        return app(self::ENTITY_IMPORTERS[$entity])->import($rows, $context);
    }

    /**
     * @var array<string, class-string>
     */
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

    // ---------------------------------------------------------------------
    // 1. Tipos de sesión (deben ir primero — bonus-types los referencian)
    // ---------------------------------------------------------------------

    public function test_fixture_session_types_creates_10_records(): void
    {
        $rows = (new CsvParser())->parse($this->fixturesPath . '/session-types.csv');
        $importer = app(ImportSessionTypesCommand::class);

        $result = $importer->import($rows, [
            'clinic_id' => $this->clinic->id,
            'user_id' => $this->owner->id,
        ]);

        $this->assertSame(10, $result->total);
        $this->assertSame(10, $result->created);
        $this->assertSame(0, $result->skipped);
        $this->assertEmpty($result->errors);

        $this->assertCount(10, AppointmentType::where('clinic_id', $this->clinic->id)->get());

        foreach (['Masaje Deportivo', 'Osteopatía', 'Punción Seca', 'Terapia Manual'] as $name) {
            $this->assertDatabaseHas('appointment_types', [
                'clinic_id' => $this->clinic->id,
                'description' => $name,
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // 2. Pacientes
    // ---------------------------------------------------------------------

    public function test_fixture_patients_creates_10_records(): void
    {
        $rows = (new CsvParser())->parse($this->fixturesPath . '/patients.csv');
        $importer = app(ImportPatientsCommand::class);

        $result = $importer->import($rows, [
            'clinic_id' => $this->clinic->id,
            'user_id' => $this->owner->id,
        ]);

        $this->assertSame(10, $result->total);
        $this->assertSame(10, $result->created);
        $this->assertSame(0, $result->skipped);
        $this->assertEmpty($result->errors);

        $this->assertDatabaseHas('patients', [
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Ana',
            'last_name' => 'García',
            'nif' => '48000001J',
        ]);

        $this->assertDatabaseHas('patients', [
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Marcos',
            'last_name' => 'Jiménez',
            'nif' => '48000010E',
        ]);
    }

    // ---------------------------------------------------------------------
    // 3. Productos
    // ---------------------------------------------------------------------

    public function test_fixture_products_creates_10_records(): void
    {
        $rows = (new CsvParser())->parse($this->fixturesPath . '/products.csv');
        $importer = app(ImportProductsCommand::class);

        $result = $importer->import($rows, [
            'clinic_id' => $this->clinic->id,
            'user_id' => $this->owner->id,
        ]);

        $this->assertSame(10, $result->total);
        $this->assertSame(10, $result->created);
        $this->assertSame(0, $result->skipped);
        $this->assertEmpty($result->errors);

        $this->assertDatabaseHas('products', [
            'clinic_id' => $this->clinic->id,
            'reference' => 'DEMO-REF-001',
            'name' => 'Crema Antiinflamatoria',
            'sale_price' => 25.95,
        ]);

        $this->assertDatabaseHas('products', [
            'clinic_id' => $this->clinic->id,
            'reference' => 'DEMO-REF-010',
            'name' => 'Bandas de Resistencia Set',
        ]);
    }

    // ---------------------------------------------------------------------
    // 4. Tipos de bono (depende de session-types ya importados)
    // ---------------------------------------------------------------------

    public function test_fixture_bonus_types_creates_10_records(): void
    {
        // Prerequisito: los bonus-types referencian session-types
        $this->importFixture('session-types');

        $result = $this->importFixture('bonus-types');

        $this->assertSame(10, $result->total);
        $this->assertSame(10, $result->created);
        $this->assertSame(0, $result->skipped);
        $this->assertEmpty($result->errors);

        $bonuses = BonusType::where('clinic_id', $this->clinic->id)->get();
        $this->assertCount(10, $bonuses);

        // Verificar pivotes: Bono Completo 15 tiene 5 líneas
        $bonoCompleto = BonusType::where('clinic_id', $this->clinic->id)
            ->where('description', 'Bono Completo 15')
            ->first();
        $this->assertNotNull($bonoCompleto);
        $this->assertSame(550.0, (float) $bonoCompleto->price);
        $this->assertCount(5, $bonoCompleto->appointmentTypes);

        // Verificar expiración
        $bonoOsteo = BonusType::where('clinic_id', $this->clinic->id)
            ->where('description', 'Bono Osteopatía 8')
            ->first();
        $this->assertNotNull($bonoOsteo);
        $this->assertSame('2027-06-30', $bonoOsteo->expires_at?->toDateString());
    }

    // ---------------------------------------------------------------------
    // 5. Historias clínicas (depende de pacientes ya importados)
    // ---------------------------------------------------------------------

    public function test_fixture_clinical_histories_creates_10_records(): void
    {
        // Prerequisito: las historias clínicas referencian pacientes
        $this->importFixture('patients');

        $result = $this->importFixture('clinical-histories');

        $this->assertSame(10, $result->total);
        $this->assertSame(10, $result->created);
        $this->assertSame(0, $result->skipped);
        $this->assertEmpty($result->errors);

        $imported = Appointment::where('clinic_id', $this->clinic->id)
            ->where('booking_source', 'import')
            ->get();

        $this->assertCount(10, $imported);

        foreach ($imported as $appt) {
            $this->assertSame('completed', $appt->status);
            $this->assertSame(0.0, (float) $appt->price);
            $this->assertNotEmpty($appt->notes);
        }
    }

    // ---------------------------------------------------------------------
    // 6. Imágenes de paciente (depende de pacientes + ZIP)
    // ---------------------------------------------------------------------

    public function test_fixture_patient_images_creates_10_records(): void
    {
        Storage::fake('public');

        // Prerequisito: las imágenes referencian pacientes
        $this->importFixture('patients');

        $result = $this->importFixture('patient-images', 'patient-images.zip');

        $this->assertSame(10, $result->total);
        $this->assertSame(10, $result->created);
        $this->assertSame(0, $result->skipped);
        $this->assertEmpty($result->errors);

        $images = PatientImage::where('clinic_id', $this->clinic->id)->get();
        $this->assertCount(20, $images); // 10 pacientes × 2 imágenes

        foreach ($images as $image) {
            $this->assertStringStartsWith('patients/', $image->path);
            Storage::disk('public')->assertExists($image->path);
        }
    }

    // ---------------------------------------------------------------------
    // Pipeline completa — ejecuta todo en orden de dependencia
    // ---------------------------------------------------------------------

    public function test_full_fixture_pipeline_creates_all_entities(): void
    {
        Storage::fake('public');
        $zipPath = $this->fixturesPath . '/patient-images.zip';

        $pipeline = [
            'session-types' => ['file' => 'session-types.csv', 'context' => []],
            'patients' => ['file' => 'patients.csv', 'context' => []],
            'products' => ['file' => 'products.csv', 'context' => []],
            'bonus-types' => ['file' => 'bonus-types.csv', 'context' => []],
            'clinical-histories' => ['file' => 'clinical-histories.csv', 'context' => []],
            'patient-images' => ['file' => 'patient-images.csv', 'context' => ['zip_path' => $zipPath]],
        ];

        $importers = [
            'session-types' => ImportSessionTypesCommand::class,
            'patients' => ImportPatientsCommand::class,
            'products' => ImportProductsCommand::class,
            'bonus-types' => ImportBonusTypesCommand::class,
            'clinical-histories' => ImportClinicalHistoriesCommand::class,
            'patient-images' => ImportPatientImagesCommand::class,
        ];

        foreach ($pipeline as $entity => $config) {
            $rows = (new CsvParser())->parse($this->fixturesPath . '/' . $config['file']);

            $context = array_merge([
                'clinic_id' => $this->clinic->id,
                'user_id' => $this->owner->id,
            ], $config['context']);

            $result = app($importers[$entity])->import($rows, $context);

            $this->assertSame(0, count($result->errors), "Entity {$entity} has errors: " . json_encode($result->errors));
            $this->assertSame(10, $result->created, "Entity {$entity} created {$result->created} instead of 10");
        }

        // Totales globales
        $this->assertCount(10, \App\Models\Patient::where('clinic_id', $this->clinic->id)->get());
        $this->assertCount(10, \App\Models\Product::where('clinic_id', $this->clinic->id)->get());
        $this->assertCount(10, AppointmentType::where('clinic_id', $this->clinic->id)->get());
        $this->assertCount(10, BonusType::where('clinic_id', $this->clinic->id)->get());
        $this->assertCount(10, Appointment::where('clinic_id', $this->clinic->id)->where('booking_source', 'import')->get());
        $this->assertCount(20, PatientImage::where('clinic_id', $this->clinic->id)->get());
    }
}
