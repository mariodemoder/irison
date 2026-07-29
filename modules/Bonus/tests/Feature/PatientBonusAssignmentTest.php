<?php

namespace Modules\Bonus\Tests\Feature;

use App\Models\Bonus;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientBonusAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create(['name' => 'Test Clinic']);

        app()->instance('activeClinic', $this->clinic);

        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);
        $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionAccess::class);

        Profile::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador']);
        Profile::firstOrCreate(['slug' => 'manager'], ['name' => 'Gestor']);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'email' => 'patient@example.com',
        ]);
    }

    private function createOwnerUser(): User
    {
        return User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'owner',
        ]);
    }

    private function createAdminUser(): User
    {
        $profile = Profile::where('slug', 'admin')->first();

        return User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'admin',
            'profile_id' => $profile->id,
        ]);
    }

    private function createManagerUser(): User
    {
        $profile = Profile::where('slug', 'manager')->first();

        return User::create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'manager',
            'profile_id' => $profile->id,
        ]);
    }

    private function assignBonusPayload(): array
    {
        return [
            'name' => 'Bienestar',
            'price' => 600,
            'total_sessions' => 2,
            'expires_at' => now()->addMonth()->format('Y-m-d'),
        ];
    }

    public function test_owner_can_assign_bonus_to_patient(): void
    {
        $user = $this->createOwnerUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/patients/{$this->patient->id}/bonuses", $this->assignBonusPayload());

        $response->assertCreated();
        $response->assertJsonStructure(['data' => ['id', 'name', 'total_sessions', 'remaining_sessions', 'price']]);
        $response->assertJsonPath('data.name', 'Bienestar');
        $response->assertJsonPath('data.total_sessions', 2);
        $response->assertJsonPath('data.remaining_sessions', 2);
        $response->assertJsonFragment(['price' => '600.00']);

        $this->assertDatabaseHas('bonuses', [
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'name' => 'Bienestar',
        ]);
    }

    public function test_admin_can_assign_bonus_to_patient(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/patients/{$this->patient->id}/bonuses", $this->assignBonusPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Bienestar');
        $response->assertJsonPath('data.total_sessions', 2);
        $response->assertJsonFragment(['price' => '600.00']);
    }

    public function test_manager_can_assign_bonus_to_patient(): void
    {
        $user = $this->createManagerUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/patients/{$this->patient->id}/bonuses", $this->assignBonusPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Bienestar');
        $response->assertJsonPath('data.total_sessions', 2);
        $response->assertJsonFragment(['price' => '600.00']);
    }

    public function test_assigned_bonus_appears_in_patient_bonuses(): void
    {
        $user = $this->createOwnerUser();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/patients/{$this->patient->id}/bonuses", $this->assignBonusPayload());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/patients/{$this->patient->id}/bonuses");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Bienestar');
    }
}
