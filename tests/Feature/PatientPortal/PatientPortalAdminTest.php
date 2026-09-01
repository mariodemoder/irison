<?php

namespace Tests\Feature\PatientPortal;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Gestión del acceso al Portal del Paciente desde el backoffice
 * (PUT /api/patients/{patient}/portal-access) — activar/desactivar + revocación.
 *
 * Patrón de autenticación: Sanctum::actingAs con usuario de la clínica (sin factories).
 */
class PatientPortalAdminTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private Clinic $otherClinic;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedProfiles();

        $this->clinic = Clinic::create([
            'name' => 'Clinica Portal Test',
            'email' => 'portal@clinic.test',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'plan' => 'pro',
        ]);

        $this->otherClinic = Clinic::create([
            'name' => 'Clinica Ajena',
            'email' => 'ajena@clinic.test',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'plan' => 'pro',
        ]);

        $this->owner = $this->makeUser($this->clinic, 'owner@clinic.test', 'owner', 'admin');
    }

    private function makeUser(Clinic $clinic, string $email, string $role, string $profileSlug): User
    {
        return User::create([
            'name' => 'Usuario',
            'email' => $email,
            'password' => Hash::make('password123'),
            'clinic_id' => $clinic->id,
            'role' => $role,
            'profile_id' => Profile::where('slug', $profileSlug)->first()->id,
        ]);
    }

    private function makePatient(Clinic $clinic, string $email, string $status = 'inactive'): Patient
    {
        return Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Paciente',
            'last_name' => 'Test',
            'email' => $email,
            'status' => $status,
        ]);
    }

    private function seedProfiles(): void
    {
        foreach ([
            ['name' => 'Administrador', 'slug' => 'admin'],
            ['name' => 'Gestor', 'slug' => 'manager'],
            ['name' => 'Profesional', 'slug' => 'professional'],
            ['name' => 'Recepcionista', 'slug' => 'reception'],
        ] as $p) {
            Profile::firstOrCreate(['slug' => $p['slug']], ['name' => $p['name']]);
        }
    }

    public function test_owner_can_activate_patient_portal(): void
    {
        Sanctum::actingAs($this->owner);

        $patient = $this->makePatient($this->clinic, 'p@clinic.test');

        $response = $this->putJson("/api/patients/{$patient->id}/portal-access", ['status' => 'active']);

        $response->assertOk()
            ->assertJsonPath('portal_status', 'active')
            ->assertJsonPath('has_portal_access', true);

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'status' => 'active']);
    }

    public function test_owner_can_deactivate_and_revokes_tokens(): void
    {
        Sanctum::actingAs($this->owner);

        $patient = $this->makePatient($this->clinic, 'p@clinic.test', 'active');
        $patient->createToken('portal-test', ['patient']);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->putJson("/api/patients/{$patient->id}/portal-access", ['status' => 'inactive']);

        $response->assertOk()
            ->assertJsonPath('portal_status', 'inactive')
            ->assertJsonPath('has_portal_access', false);

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'status' => 'inactive']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_deactivate_without_tokens_is_idempotent(): void
    {
        Sanctum::actingAs($this->owner);

        $patient = $this->makePatient($this->clinic, 'p@clinic.test', 'active');

        $response = $this->putJson("/api/patients/{$patient->id}/portal-access", ['status' => 'inactive']);

        $response->assertOk()->assertJsonPath('portal_status', 'inactive');
    }

    public function test_reactivate_after_deactivate(): void
    {
        Sanctum::actingAs($this->owner);

        $patient = $this->makePatient($this->clinic, 'p@clinic.test', 'active');

        $this->putJson("/api/patients/{$patient->id}/portal-access", ['status' => 'inactive'])->assertOk();
        $this->putJson("/api/patients/{$patient->id}/portal-access", ['status' => 'active'])->assertOk();

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'status' => 'active']);
    }

    public function test_invalid_status_returns_422(): void
    {
        Sanctum::actingAs($this->owner);

        $patient = $this->makePatient($this->clinic, 'p@clinic.test');

        $response = $this->putJson("/api/patients/{$patient->id}/portal-access", ['status' => 'banana']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'status' => 'inactive']);
    }

    public function test_cross_clinic_cannot_manage_portal_access(): void
    {
        Sanctum::actingAs($this->owner);

        $foreignPatient = $this->makePatient($this->otherClinic, 'foreign@clinic.test');

        $response = $this->putJson("/api/patients/{$foreignPatient->id}/portal-access", ['status' => 'active']);

        // Route model binding + ClinicScope: el paciente ajeno no resuelve -> 404
        $response->assertStatus(404);

        $this->assertDatabaseHas('patients', ['id' => $foreignPatient->id, 'status' => 'inactive']);
    }

    public function test_viewer_cannot_manage_portal_access(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'viewer@clinic.test', 'user', 'professional'));

        $patient = $this->makePatient($this->clinic, 'p@clinic.test');

        $response = $this->putJson("/api/patients/{$patient->id}/portal-access", ['status' => 'active']);

        $response->assertForbidden();
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'status' => 'inactive']);
    }

    public function test_guest_cannot_manage_portal_access(): void
    {
        $patient = $this->makePatient($this->clinic, 'p@clinic.test');

        $response = $this->putJson("/api/patients/{$patient->id}/portal-access", ['status' => 'active']);

        $response->assertUnauthorized();
    }
}