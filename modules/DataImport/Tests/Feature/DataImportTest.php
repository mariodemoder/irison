<?php

declare(strict_types=1);

namespace Modules\DataImport\Tests\Feature;

use App\Http\Middleware\CheckSubscriptionAccess;
use App\Http\Middleware\EnsureClinic;
use App\Http\Middleware\EnsureClinicIsActive;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\PatientImage;
use App\Models\Profile;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Bonus\Models\BonusType;
use Tests\TestCase;
use ZipArchive;

class DataImportTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;

    private User $owner;

    private User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();

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
            'name' => 'Data Import Clinic',
            'subscription_status' => 'active',
            'status' => 'active',
            'plan' => 'pro',
            'max_users' => 5,
        ]);

        $this->owner = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Owner',
            'email' => 'owner@dataimport.test',
            'password' => 'password',
            'role' => 'owner',
            'profile_id' => Profile::where('slug', 'admin')->first()->id,
        ]);

        $this->receptionist = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Recepcion',
            'email' => 'recep@dataimport.test',
            'password' => 'password',
            'role' => 'reception',
            'profile_id' => Profile::where('slug', 'reception')->first()->id,
        ]);
    }

    private function actAsOwner(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        app()->instance('activeClinic', $this->clinic);
    }

    private function csvFile(string $content, string $name = 'import.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import_');
        if ($path === false) {
            $this->fail('No se pudo crear el fichero temporal del CSV.');
        }
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'text/plain', null, true);
    }

    /**
     * @param  array<string, string>  $entries  nombre dentro del zip => contenido
     */
    private function zipFile(array $entries, string $name = 'images.zip'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'images_');
        if ($path === false) {
            $this->fail('No se pudo crear el fichero temporal del ZIP.');
        }

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($entries as $entryName => $content) {
            $zip->addFromString($entryName, $content);
        }
        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }

    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            true,
        ) ?: '';
    }

    // ---------------------------------------------------------------------
    // Pacientes
    // ---------------------------------------------------------------------

    public function test_imports_patients_with_nif_or_email(): void
    {
        $this->actAsOwner();

        $csv = "nombre;nif;email;telefono;fecha_nacimiento;direccion;cp;poblacion;provincia;pais;observaciones\n" .
            "Ana Garcia;12345678Z;ana@example.com;600000000;01/01/1990;Calle 1;28001;Madrid;Madrid;España;-\n" .
            "Luis Perez;X1234567L;luis@example.com;611111111;1990-01-02;Calle 2;08001;Barcelona;Barcelona;España;-\n";

        $response = $this->postJson('/api/imports/patients', [
            'file' => $this->csvFile($csv),
        ])->assertOk();

        $this->assertSame(2, $response->json('data.created'));
        $this->assertSame(2, $response->json('data.total'));
        $this->assertCount(0, $response->json('data.errors'));

        $this->assertDatabaseHas('patients', [
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Ana',
            'last_name' => 'Garcia',
            'nif' => '12345678Z',
            'email' => 'ana@example.com',
            'status' => 'active',
        ]);

        // Nombre con coma → "Apellido, Nombre"
        $this->assertDatabaseHas('patients', [
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Luis',
            'last_name' => 'Perez',
        ]);
    }

    public function test_imports_patients_skips_duplicates_with_warning(): void
    {
        $this->actAsOwner();

        Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Juan',
            'last_name' => 'Duplicado',
            'nif' => '11111111H',
        ]);

        $csv = "nombre;nif;email\n" .
            "Juan Duplicado;11111111H;juan@example.com\n" .
            "Nuevo Paciente;22222222J;nuevo@example.com\n" .
            "Repetido;33333333P;repetido@example.com\n" .
            "Repetido 2;44444444A;repetido@example.com\n";

        $response = $this->postJson('/api/imports/patients', [
            'file' => $this->csvFile($csv),
        ])->assertOk();

        $this->assertSame(2, $response->json('data.created'));
        $this->assertSame(2, $response->json('data.skipped'));
        $this->assertCount(2, $response->json('data.warnings'), 'Debe avisar de duplicados');
    }

    public function test_patient_row_without_nif_or_email_reports_error(): void
    {
        $this->actAsOwner();

        $csv = "nombre;nif;email\n" .
            "Sin Identificador;;\n" .
            "Valido;12345678Z;valido@example.com\n";

        $response = $this->postJson('/api/imports/patients', [
            'file' => $this->csvFile($csv),
        ])->assertOk();

        $this->assertSame(1, $response->json('data.created'));
        $this->assertCount(1, $response->json('data.errors'));
        $this->assertSame(2, $response->json('data.errors.0.row'));
    }

    public function test_patient_row_with_invalid_nif_reports_error(): void
    {
        $this->actAsOwner();

        $csv = "nombre;nif;email\n" .
            "Mal NIF;00000000A;mal@example.com\n";

        $response = $this->postJson('/api/imports/patients', [
            'file' => $this->csvFile($csv),
        ])->assertOk();

        $this->assertSame(0, $response->json('data.created'));
        $this->assertCount(1, $response->json('data.errors'));
    }

    // ---------------------------------------------------------------------
    // Productos
    // ---------------------------------------------------------------------

    public function test_imports_products_and_skips_existing_reference(): void
    {
        $this->actAsOwner();

        Product::create([
            'clinic_id' => $this->clinic->id,
            'reference' => 'REF-001',
            'name' => 'Existente',
            'sale_price' => 10,
            'purchase_price' => 5,
        ]);

        $csv = "referencia;nombre;precio_venta;precio_compra;iva_venta;iva_compra;familia;lote\n" .
            "REF-001;Existente;25,00;10,00;21;0;Fam;Lote\n" .
            "REF-002;Nuevo producto;35,50;12,00;21;0;Fam;Lote\n";

        $response = $this->postJson('/api/imports/products', [
            'file' => $this->csvFile($csv),
        ])->assertOk();

        $this->assertSame(1, $response->json('data.created'));
        $this->assertSame(1, $response->json('data.skipped'));

        $this->assertDatabaseHas('products', [
            'clinic_id' => $this->clinic->id,
            'reference' => 'REF-002',
            'name' => 'Nuevo producto',
            'sale_price' => 35.50,
        ]);
    }

    // ---------------------------------------------------------------------
    // Tipos de sesión
    // ---------------------------------------------------------------------

    public function test_imports_session_types_and_skips_existing(): void
    {
        $this->actAsOwner();

        AppointmentType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Masaje',
            'estimated_minutes' => 60,
            'price' => 30,
        ]);

        $csv = "nombre;horas_estimadas;minutos_estimados;precio;color\n" .
            "Masaje;0;60;35,50;#1a73e8\n" .
            "Fisioterapia;0;45;40,00;#ff0000\n";

        $response = $this->postJson('/api/imports/session-types', [
            'file' => $this->csvFile($csv),
        ])->assertOk();

        $this->assertSame(1, $response->json('data.created'));
        $this->assertSame(1, $response->json('data.skipped'));

        $this->assertDatabaseHas('appointment_types', [
            'clinic_id' => $this->clinic->id,
            'description' => 'Fisioterapia',
            'price' => 40.00,
            'color' => '#FF0000',
        ]);
    }

    // ---------------------------------------------------------------------
    // Tipos de bono
    // ---------------------------------------------------------------------

    public function test_imports_bonus_types_with_session_lines(): void
    {
        $this->actAsOwner();

        AppointmentType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Masaje',
            'estimated_minutes' => 60,
            'price' => 30,
        ]);
        AppointmentType::create([
            'clinic_id' => $this->clinic->id,
            'description' => 'Fisioterapia',
            'estimated_minutes' => 45,
            'price' => 40,
        ]);

        $csv = "nombre;sesiones;precio;expira_el;linea_1;linea_2;linea_3;linea_4;linea_5;linea_6;linea_7;linea_8;linea_9\n" .
            "Bono 5 sesiones;5;150,00;31/12/2026;Masaje|3;Fisioterapia|2|35,00;;;;;;;;\n";

        $response = $this->postJson('/api/imports/bonus-types', [
            'file' => $this->csvFile($csv),
        ])->assertOk();

        $this->assertSame(1, $response->json('data.created'));
        $this->assertCount(0, $response->json('data.errors'));

        $bonusType = BonusType::where('clinic_id', $this->clinic->id)
            ->where('description', 'Bono 5 sesiones')
            ->first();

        $this->assertNotNull($bonusType);
        $this->assertSame(5, (int) $bonusType->sessions);
        $this->assertSame('2026-12-31', $bonusType->expires_at?->toDateString());

        $this->assertDatabaseHas('appointment_type_bonus_type', [
            'bonus_type_id' => $bonusType->id,
            'appointment_type_id' => AppointmentType::where('clinic_id', $this->clinic->id)->where('description', 'Masaje')->first()->id,
            'quantity' => 3,
            'unit_price' => 30.00,
        ]);

        $this->assertDatabaseHas('appointment_type_bonus_type', [
            'bonus_type_id' => $bonusType->id,
            'appointment_type_id' => AppointmentType::where('clinic_id', $this->clinic->id)->where('description', 'Fisioterapia')->first()->id,
            'quantity' => 2,
            'unit_price' => 35.00,
        ]);
    }

    public function test_bonus_type_line_with_unknown_session_reports_error(): void
    {
        $this->actAsOwner();

        $csv = "nombre;sesiones;precio;expira_el;linea_1;linea_2;linea_3;linea_4;linea_5;linea_6;linea_7;linea_8;linea_9\n" .
            "Bono invalido;3;90,00;;SesionInexistente|3;;;;;;;;\n";

        $response = $this->postJson('/api/imports/bonus-types', [
            'file' => $this->csvFile($csv),
        ])->assertOk();

        $this->assertSame(0, $response->json('data.created'));
        $this->assertCount(1, $response->json('data.errors'));
        $this->assertStringContainsString('no existe', $response->json('data.errors.0.message'));
    }

    // ---------------------------------------------------------------------
    // Historias clínicas
    // ---------------------------------------------------------------------

    public function test_imports_clinical_history_as_completed_appointment(): void
    {
        $this->actAsOwner();

        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Ana',
            'last_name' => 'Garcia',
            'nif' => '12345678Z',
        ]);

        $csv = "nif_o_email;fecha;historia\n" .
            "12345678Z;10/03/2024;Paciente con dolor lumbar crónico.\n";

        $response = $this->postJson('/api/imports/clinical-histories', [
            'file' => $this->csvFile($csv),
        ])->assertOk();

        $this->assertSame(1, $response->json('data.created'));

        $this->assertDatabaseHas('appointments', [
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'status' => 'completed',
            'booking_source' => 'import',
            'price' => 0,
            'notes' => 'Paciente con dolor lumbar crónico.',
        ]);
    }

    public function test_clinical_history_is_idempotent_per_patient(): void
    {
        $this->actAsOwner();

        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Ana',
            'last_name' => 'Garcia',
            'nif' => '12345678Z',
        ]);

        Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'start_time' => '2024-01-01 09:00:00',
            'end_time' => '2024-01-01 09:30:00',
            'status' => 'completed',
            'payment_status' => 'pending',
            'booking_source' => 'import',
        ]);

        $csv = "nif_o_email;fecha;historia\n" .
            "12345678Z;;Segunda historia.\n";

        $response = $this->postJson('/api/imports/clinical-histories', [
            'file' => $this->csvFile($csv),
        ])->assertOk();

        $this->assertSame(0, $response->json('data.created'));
        $this->assertSame(1, $response->json('data.skipped'));

        $this->assertSame(1, Appointment::where('clinic_id', $this->clinic->id)
            ->where('patient_id', $patient->id)
            ->count());
    }

    public function test_clinical_history_with_unknown_patient_reports_error(): void
    {
        $this->actAsOwner();

        $csv = "nif_o_email;fecha;historia\n" .
            "99999999R;;Historia de un paciente inexistente.\n";

        $response = $this->postJson('/api/imports/clinical-histories', [
            'file' => $this->csvFile($csv),
        ])->assertOk();

        $this->assertSame(0, $response->json('data.created'));
        $this->assertCount(1, $response->json('data.errors'));
    }

    // ---------------------------------------------------------------------
    // Imágenes de paciente
    // ---------------------------------------------------------------------

    public function test_imports_patient_images_from_zip(): void
    {
        Storage::fake('public');
        $this->actAsOwner();

        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Ana',
            'last_name' => 'Garcia',
            'nif' => '12345678Z',
        ]);

        $csv = "nif_o_email;imagen_1;imagen_2;imagen_3;imagen_4;imagen_5;imagen_6\n" .
            "12345678Z;foto1.png;foto2.png;;;;\n";

        $response = $this->postJson('/api/imports/patient-images', [
            'file' => $this->csvFile($csv),
            'zip' => $this->zipFile([
                'foto1.png' => $this->pngBytes(),
                'foto2.png' => $this->pngBytes(),
            ]),
        ])->assertOk();

        $this->assertSame(1, $response->json('data.created'));
        $this->assertSame(2, PatientImage::where('clinic_id', $this->clinic->id)
            ->where('patient_id', $patient->id)
            ->count());

        $image = PatientImage::where('clinic_id', $this->clinic->id)
            ->where('patient_id', $patient->id)
            ->first();
        $this->assertNotEmpty($image->path);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_patient_images_with_missing_zip_file_reports_error(): void
    {
        Storage::fake('public');
        $this->actAsOwner();

        Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Ana',
            'last_name' => 'Garcia',
            'nif' => '12345678Z',
        ]);

        $csv = "nif_o_email;imagen_1;imagen_2;imagen_3;imagen_4;imagen_5;imagen_6\n" .
            "12345678Z;foto1.png;otra.png;;;;\n";

        $response = $this->postJson('/api/imports/patient-images', [
            'file' => $this->csvFile($csv),
            'zip' => $this->zipFile([
                'foto1.png' => $this->pngBytes(),
            ]),
        ])->assertOk();

        $this->assertSame(0, $response->json('data.created'));
        $this->assertCount(1, $response->json('data.errors'));
        $this->assertSame(0, PatientImage::where('clinic_id', $this->clinic->id)->count());
    }

    public function test_patient_images_zip_rejects_path_traversal(): void
    {
        Storage::fake('public');
        $this->actAsOwner();

        Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Ana',
            'last_name' => 'Garcia',
            'nif' => '12345678Z',
        ]);

        $csv = "nif_o_email;imagen_1;imagen_2;imagen_3;imagen_4;imagen_5;imagen_6\n" .
            "12345678Z;foto1.png;;;;;\n";

        // El zip interno intenta navegar fuera de su carpeta.
        $response = $this->postJson('/api/imports/patient-images', [
            'file' => $this->csvFile($csv),
            'zip' => $this->zipFile([
                '../foto1.png' => $this->pngBytes(),
            ]),
        ])->assertOk();

        $this->assertSame(0, $response->json('data.created'));
        $this->assertCount(1, $response->json('data.errors'));
    }

    // ---------------------------------------------------------------------
    // Validaciones globales
    // ---------------------------------------------------------------------

    public function test_missing_required_column_returns_422(): void
    {
        $this->actAsOwner();

        $csv = "nif;email\n" .
            "12345678Z;ana@example.com\n";

        $this->postJson('/api/imports/patients', [
            'file' => $this->csvFile($csv),
        ])->assertStatus(422);
    }

    public function test_empty_csv_returns_422(): void
    {
        $this->actAsOwner();

        $this->postJson('/api/imports/patients', [
            'file' => $this->csvFile(''),
        ])->assertStatus(422);
    }

    public function test_max_row_limit_returns_422(): void
    {
        $this->actAsOwner();

        $content = "nombre;nif;email\n";
        for ($i = 0; $i <= (int) config('dataimport.max_rows', 2000); $i++) {
            $content .= "Paciente {$i};12345678Z;p{$i}@example.com\n";
        }

        $this->postJson('/api/imports/patients', [
            'file' => $this->csvFile($content),
        ])->assertStatus(422);
    }

    public function test_basic_clinic_cannot_import(): void
    {
        $clinic = Clinic::create([
            'name' => 'Basic Clinic',
            'subscription_status' => 'active',
            'status' => 'active',
            'plan' => 'basic',
            'max_users' => 5,
        ]);

        $owner = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Basic Owner',
            'email' => 'basic@dataimport.test',
            'password' => 'password',
            'role' => 'owner',
            'profile_id' => Profile::where('slug', 'admin')->first()->id,
        ]);

        $this->actingAs($owner, 'sanctum');
        app()->instance('activeClinic', $clinic);

        $csv = "nombre;nif;email\n" .
            "Ana Garcia;12345678Z;ana@example.com\n";

        $this->postJson('/api/imports/patients', [
            'file' => $this->csvFile($csv),
        ])->assertForbidden();
    }

    public function test_receptionist_cannot_import(): void
    {
        $this->actingAs($this->receptionist, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $csv = "nombre;nif;email\n" .
            "Ana Garcia;12345678Z;ana@example.com\n";

        $this->postJson('/api/imports/patients', [
            'file' => $this->csvFile($csv),
        ])->assertForbidden();
    }

    public function test_unknown_entity_returns_404(): void
    {
        $this->actAsOwner();

        $csv = "nombre;nif;email\n" .
            "Ana Garcia;12345678Z;ana@example.com\n";

        $this->postJson('/api/imports/whatever', [
            'file' => $this->csvFile($csv),
        ])->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Plantillas
    // ---------------------------------------------------------------------

    public function test_template_download_returns_csv_with_bom(): void
    {
        $this->actAsOwner();

        $response = $this->getJson('/api/imports/patients/template')->assertOk();

        $content = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('nombre;nif;email', $content);
        $this->assertStringContainsString('plantilla_patients.csv', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_template_includes_10_fixture_rows(): void
    {
        $this->actAsOwner();

        // Pacientes: la plantilla incluye las 10 filas reales del fixture.
        $response = $this->getJson('/api/imports/patients/template')->assertOk();
        $dataRows = $this->templateDataRows($response->getContent());

        $this->assertCount(10, $dataRows);
        $this->assertStringContainsString('Ana García', $dataRows[0], 'Primera fila de pacientes (con acentos).');
        $this->assertStringContainsString('48000001J', $dataRows[0], 'NIF con checksum válido.');
        $this->assertStringContainsString('Marcos Jiménez', $dataRows[9], 'Décima fila de pacientes.');
        $this->assertStringContainsString('48000010E', $dataRows[9]);

        // Productos: referencias y precios con coma decimal.
        $response = $this->getJson('/api/imports/products/template')->assertOk();
        $dataRows = $this->templateDataRows($response->getContent());

        $this->assertCount(10, $dataRows);
        $this->assertStringContainsString('DEMO-REF-001', $dataRows[0]);
        $this->assertStringContainsString('25,95', $dataRows[0], 'Precio con coma decimal.');
        $this->assertStringContainsString('Bandas de Resistencia Set', $dataRows[9]);

        // Tipos de sesión.
        $response = $this->getJson('/api/imports/session-types/template')->assertOk();
        $dataRows = $this->templateDataRows($response->getContent());

        $this->assertCount(10, $dataRows);
        $this->assertStringContainsString('Masaje Deportivo', $dataRows[0]);

        // Tipos de bono (líneas con |).
        $response = $this->getJson('/api/imports/bonus-types/template')->assertOk();
        $dataRows = $this->templateDataRows($response->getContent());

        $this->assertCount(10, $dataRows);
        $this->assertStringContainsString('Masaje Deportivo|5|40,00', $dataRows[0], 'Línea de bono con formato Tipo|cantidad|precio.');

        // Historias clínicas.
        $response = $this->getJson('/api/imports/clinical-histories/template')->assertOk();
        $dataRows = $this->templateDataRows($response->getContent());

        $this->assertCount(10, $dataRows);
        $this->assertStringContainsString('48000001J', $dataRows[0]);

        // Imágenes de paciente.
        $response = $this->getJson('/api/imports/patient-images/template')->assertOk();
        $dataRows = $this->templateDataRows($response->getContent());

        $this->assertCount(10, $dataRows);
        $this->assertStringContainsString('demo-01.png', $dataRows[0]);
    }

    /**
     * Devuelve las filas de datos de una respuesta CSV (sin la línea de headers).
     *
     * @return list<string> Filas tal cual aparecen (sin BOM en la primera línea).
     */
    private function templateDataRows(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?: '';

        $lines = preg_split('/\R/', trim($csv)) ?: [];

        // La primera línea es el encabezado.
        return array_values(array_slice($lines, 1));
    }

    public function test_import_is_logged_in_activity(): void
    {
        $this->actAsOwner();

        $csv = "nombre;nif;email\n" .
            "Ana Garcia;12345678Z;ana@example.com\n";

        $this->postJson('/api/imports/patients', [
            'file' => $this->csvFile($csv),
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $this->clinic->id,
            'user_id' => $this->owner->id,
            'event' => 'dataimport.completed',
        ]);
    }
}