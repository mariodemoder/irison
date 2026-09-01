<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

use App\Models\Clinic;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Configuración del slug del Portal del Paciente desde el backoffice
 * (GET/PUT /api/patient-portal/settings, GET /api/patient-portal/slug-check).
 *
 * Autorización: owner / admin / manager. Patrón Sanctum::actingAs con usuarios staff.
 */
class PatientPortalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private Clinic $otherClinic;
    private Clinic $sluglessClinic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProfiles();

        $this->clinic = Clinic::create([
            'name' => 'Clinica Portal Test',
            'slug' => 'clinica-portal-test',
            'email' => 'portal@clinic.test',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'plan' => 'pro',
        ]);

        $this->otherClinic = Clinic::create([
            'name' => 'Clinica Ajena',
            'slug' => 'clinica-ajena',
            'email' => 'ajena@clinic.test',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'plan' => 'pro',
        ]);

        // Clínica sin slug (caso clínicas existentes pre-migración).
        $this->sluglessClinic = Clinic::create([
            'name' => 'Peluqueria Canina',
            'email' => 'slugless@clinic.test',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'plan' => 'pro',
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

    // ── GET /patient-portal/settings ─────────────────────────────

    public function test_owner_can_read_settings(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'owner@clinic.test', 'owner', 'admin'));

        $this->getJson('/api/patient-portal/settings')
            ->assertOk()
            ->assertJsonPath('slug', 'clinica-portal-test')
            ->assertJsonPath('suggested_slug', null);
    }

    public function test_settings_suggests_slug_when_clinic_has_none(): void
    {
        Sanctum::actingAs($this->makeUser($this->sluglessClinic, 'owner@slugless.test', 'owner', 'admin'));

        $this->getJson('/api/patient-portal/settings')
            ->assertOk()
            ->assertJsonPath('slug', null)
            ->assertJsonPath('suggested_slug', 'peluqueria-canina');
    }

    public function test_admin_and_manager_can_read_settings(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'admin@clinic.test', 'user', 'admin'));
        $this->getJson('/api/patient-portal/settings')->assertOk();

        Sanctum::actingAs($this->makeUser($this->clinic, 'manager@clinic.test', 'user', 'manager'));
        $this->getJson('/api/patient-portal/settings')->assertOk();
    }

    public function test_professional_cannot_read_settings(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'pro@clinic.test', 'user', 'professional'));

        $this->getJson('/api/patient-portal/settings')->assertForbidden();
    }

    public function test_guest_cannot_read_settings(): void
    {
        $this->getJson('/api/patient-portal/settings')->assertUnauthorized();
    }

    // ── PUT /patient-portal/settings ─────────────────────────────

    public function test_owner_can_update_slug(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'owner@clinic.test', 'owner', 'admin'));

        $this->putJson('/api/patient-portal/settings', ['slug' => 'nuevo-slug'])
            ->assertOk()
            ->assertJsonPath('slug', 'nuevo-slug');

        $this->assertDatabaseHas('clinics', ['id' => $this->clinic->id, 'slug' => 'nuevo-slug']);
    }

    public function test_update_validates_slug_format(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'owner@clinic.test', 'owner', 'admin'));

        $this->putJson('/api/patient-portal/settings', ['slug' => 'Slug Invalido!'])
            ->assertStatus(422);

        $this->assertDatabaseHas('clinics', ['id' => $this->clinic->id, 'slug' => 'clinica-portal-test']);
    }

    public function test_update_rejects_colliding_slug(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'owner@clinic.test', 'owner', 'admin'));

        $this->putJson('/api/patient-portal/settings', ['slug' => 'clinica-ajena'])
            ->assertStatus(422);

        $this->assertDatabaseHas('clinics', ['id' => $this->clinic->id, 'slug' => 'clinica-portal-test']);
    }

    public function test_update_accepts_slug_empty_is_required(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'owner@clinic.test', 'owner', 'admin'));

        $this->putJson('/api/patient-portal/settings', ['slug' => ''])
            ->assertStatus(422);
    }

    public function test_professional_cannot_update_slug(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'pro@clinic.test', 'user', 'professional'));

        $this->putJson('/api/patient-portal/settings', ['slug' => 'nuevo'])
            ->assertForbidden();

        $this->assertDatabaseHas('clinics', ['id' => $this->clinic->id, 'slug' => 'clinica-portal-test']);
    }

    // ── GET /patient-portal/slug-check ───────────────────────────

    public function test_slug_check_available(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'owner@clinic.test', 'owner', 'admin'));

        $this->getJson('/api/patient-portal/slug-check?slug=disponible')
            ->assertOk()
            ->assertJsonPath('available', true);
    }

    public function test_slug_check_unavailable_when_taken_by_other_clinic(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'owner@clinic.test', 'owner', 'admin'));

        $this->getJson('/api/patient-portal/slug-check?slug=clinica-ajena')
            ->assertOk()
            ->assertJsonPath('available', false);
    }

    public function test_slug_check_own_slug_is_available(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'owner@clinic.test', 'owner', 'admin'));

        $this->getJson('/api/patient-portal/slug-check?slug=clinica-portal-test')
            ->assertOk()
            ->assertJsonPath('available', true);
    }

    public function test_slug_check_requires_slug(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'owner@clinic.test', 'owner', 'admin'));

        $this->getJson('/api/patient-portal/slug-check')->assertStatus(422);
    }

    public function test_slug_check_forbidden_for_professional(): void
    {
        Sanctum::actingAs($this->makeUser($this->clinic, 'pro@clinic.test', 'user', 'professional'));

        $this->getJson('/api/patient-portal/slug-check?slug=disponible')->assertForbidden();
    }
}
