<?php

namespace Tests\Feature\Authorization;

use App\Http\Middleware\CheckSubscriptionAccess;
use App\Http\Middleware\EnsureClinic;
use App\Http\Middleware\EnsureClinicIsActive;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Profile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReceptionRoleTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureClinic::class,
            EnsureClinicIsActive::class,
            CheckSubscriptionAccess::class,
        ]);

        $this->seedProfiles();

        $this->clinic = Clinic::create([
            'name' => 'Pro Clinic',
            'subscription_status' => 'active',
            'status' => 'active',
            'plan' => 'pro',
            'max_users' => 5,
        ]);

        $this->owner = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'profile_id' => Profile::where('slug', 'admin')->first()->id,
        ]);
    }

    private function seedProfiles(): void
    {
        $profiles = [
            ['name' => 'Administrador', 'slug' => 'admin'],
            ['name' => 'Gestor', 'slug' => 'manager'],
            ['name' => 'Profesional', 'slug' => 'professional'],
            ['name' => 'Recepcionista', 'slug' => 'reception'],
        ];

        foreach ($profiles as $p) {
            Profile::firstOrCreate(['slug' => $p['slug']], $p);
        }
    }

    private function createReceptionist(): User
    {
        return User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Reception',
            'email' => 'reception@test.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'profile_id' => Profile::where('slug', 'reception')->first()->id,
        ]);
    }

    public function test_owner_can_assign_reception_profile_on_pro_plan(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $response = $this->postJson('/api/team/users', [
            'name' => 'Recepción',
            'email' => 'recep@test.com',
            'password' => 'Contrasena123!',
            'profile_id' => Profile::where('slug', 'reception')->first()->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'recep@test.com']);
    }

    public function test_reception_profile_is_blocked_on_basic_plan(): void
    {
        $basic = Clinic::create([
            'name' => 'Basic Clinic',
            'subscription_status' => 'active',
            'status' => 'active',
            'plan' => 'basic',
            'max_users' => 1,
        ]);

        $basicOwner = User::create([
            'clinic_id' => $basic->id,
            'name' => 'Basic Owner',
            'email' => 'bo@test.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'profile_id' => Profile::where('slug', 'admin')->first()->id,
        ]);

        $this->actingAs($basicOwner, 'sanctum');
        app()->instance('activeClinic', $basic);

        $this->postJson('/api/team/users', [
            'name' => 'Recepción',
            'email' => 'recep-basic@test.com',
            'password' => 'Contrasena123!',
            'profile_id' => Profile::where('slug', 'reception')->first()->id,
        ])->assertForbidden();
    }

    public function test_receptionist_has_operational_access_but_not_full(): void
    {
        $reception = $this->createReceptionist();

        $this->assertFalse($reception->hasFullAccess());
        $this->assertTrue($reception->hasOperationalAccess());
        $this->assertTrue($reception->isReceptionist());
    }

    public function test_receptionist_can_create_appointment(): void
    {
        $reception = $this->createReceptionist();
        $patient = Patient::create(['clinic_id' => $this->clinic->id, 'first_name' => 'Ana', 'last_name' => 'Lopez']);

        $this->actingAs($reception, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $start = Carbon::now()->addDays(2)->startOfHour();

        $this->postJson('/api/appointments', [
            'patient_id' => $patient->id,
            'start_time' => $start->toDateTimeString(),
            'end_time' => $start->copy()->addHour()->toDateTimeString(),
            'price' => 60,
        ])->assertStatus(201);
    }

    public function test_receptionist_can_create_patient(): void
    {
        $reception = $this->createReceptionist();

        $this->actingAs($reception, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->postJson('/api/patients', ['name' => 'Paciente Nuevo'])
            ->assertStatus(201);
    }

    public function test_receptionist_cannot_manage_team(): void
    {
        $reception = $this->createReceptionist();

        $this->actingAs($reception, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/team/users')->assertForbidden();
    }

    public function test_receptionist_cannot_access_finance(): void
    {
        $reception = $this->createReceptionist();

        $this->actingAs($reception, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/finance/benefits')->assertForbidden();
        $this->getJson('/api/finance/expenses')->assertForbidden();
    }

    public function test_finance_blocked_for_basic_plan_clinic(): void
    {
        $basic = Clinic::create([
            'name' => 'Basic Clinic 2',
            'subscription_status' => 'active',
            'status' => 'active',
            'plan' => 'basic',
            'max_users' => 1,
        ]);

        $basicOwner = User::create([
            'clinic_id' => $basic->id,
            'name' => 'Basic Owner 2',
            'email' => 'bo2@test.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'profile_id' => Profile::where('slug', 'admin')->first()->id,
        ]);

        $this->actingAs($basicOwner, 'sanctum');
        app()->instance('activeClinic', $basic);

        $this->getJson('/api/finance/expenses')->assertForbidden();
    }

    public function test_professional_user_exists_for_finance_tarifas(): void
    {
        $this->assertDatabaseHas('profiles', ['slug' => 'admin']);
        $this->assertDatabaseHas('profiles', ['slug' => 'reception']);
    }

    public function test_owner_can_set_cost_per_hour_when_creating_user(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $response = $this->postJson('/api/team/users', [
            'name' => 'Profe Coste',
            'email' => 'profe-coste@test.com',
            'password' => 'Contrasena123!',
            'profile_id' => Profile::where('slug', 'professional')->first()->id,
            'cost_per_hour' => 35.5,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(35.5, (float) $response->json('cost_per_hour'));

        $user = User::where('email', 'profe-coste@test.com')->first();
        $this->assertDatabaseHas('professional_rates', [
            'user_id' => $user->id,
            'cost_per_hour' => 35.5,
        ]);
    }

    public function test_owner_can_update_cost_per_hour_and_remove_it(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $user = $this->createReceptionist();

        $this->putJson('/api/team/users/' . $user->id, [
            'cost_per_hour' => 40,
        ])->assertOk();

        $this->assertDatabaseHas('professional_rates', [
            'user_id' => $user->id,
            'cost_per_hour' => 40,
        ]);

        $this->putJson('/api/team/users/' . $user->id, [
            'cost_per_hour' => 0,
        ])->assertOk();

        $this->assertDatabaseMissing('professional_rates', ['user_id' => $user->id]);
    }

    public function test_pro_plan_limits_total_users_including_owner_to_five(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $professionalProfileId = Profile::where('slug', 'professional')->first()->id;

        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/api/team/users', [
                'name' => 'Miembro ' . $i,
                'email' => 'miembro' . $i . '@test.com',
                'password' => 'Contrasena123!',
                'profile_id' => $professionalProfileId,
            ])->assertStatus(201);
        }

        $response = $this->postJson('/api/team/users', [
            'name' => 'Sexto Usuario',
            'email' => 'sexto@test.com',
            'password' => 'Contrasena123!',
            'profile_id' => $professionalProfileId,
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseMissing('users', ['email' => 'sexto@test.com']);
    }
}