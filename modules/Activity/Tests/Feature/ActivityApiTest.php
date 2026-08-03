<?php

declare(strict_types=1);

namespace Modules\Activity\Tests\Feature;

use App\Http\Middleware\CheckSubscriptionAccess;
use App\Http\Middleware\EnsureClinic;
use App\Http\Middleware\EnsureClinicIsActive;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Profile;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ActivityApiTest extends TestCase
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

        $profiles = [
            ['name' => 'Administrador', 'slug' => 'admin'],
            ['name' => 'Gestor', 'slug' => 'manager'],
            ['name' => 'Profesional', 'slug' => 'professional'],
            ['name' => 'Recepcionista', 'slug' => 'reception'],
        ];

        foreach ($profiles as $p) {
            Profile::firstOrCreate(['slug' => $p['slug']], $p);
        }

        $this->clinic = Clinic::create([
            'name' => 'Activity Clinic',
            'subscription_status' => 'active',
            'status' => 'active',
            'plan' => 'pro',
            'max_users' => 5,
        ]);

        $this->owner = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Owner',
            'email' => 'owner@activity.test',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'profile_id' => Profile::where('slug', 'admin')->first()->id,
        ]);

        $this->receptionist = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Recepcion',
            'email' => 'recep@activity.test',
            'password' => Hash::make('password'),
            'role' => 'reception',
            'profile_id' => Profile::where('slug', 'reception')->first()->id,
        ]);
    }

    private function actAsOwner(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        app()->instance('activeClinic', $this->clinic);
    }

    public function test_owner_can_list_activity_log(): void
    {
        $this->actAsOwner();

        ActivityLogger::log(
            tenantId: (int) $this->clinic->id,
            userId: (int) $this->owner->id,
            event: 'patient.created',
            description: 'Paciente creado',
            metadata: ['entity' => 'patient', 'entity_id' => 1],
            ip: '127.0.0.1',
        );

        $response = $this->getJson('/api/activity')->assertOk();

        $this->assertNotEmpty($response->json('data'));
        $this->assertEquals('patient.created', $response->json('data.0.event'));
        $this->assertEquals('patient', $response->json('data.0.entity'));
        $this->assertEquals(1, $response->json('data.0.entity_id'));
    }

    public function test_activity_filters_by_entity_and_event(): void
    {
        $this->actAsOwner();

        ActivityLogger::log(
            tenantId: (int) $this->clinic->id,
            userId: (int) $this->owner->id,
            event: 'payment.created',
            description: 'Pago registrado',
            metadata: ['entity' => 'payment', 'entity_id' => 7],
            ip: '127.0.0.1',
        );

        $this->getJson('/api/activity?entity=payment')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/activity?event=payment.created')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/activity?entity=patient')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_receptionist_cannot_access_activity_log(): void
    {
        $this->actingAs($this->receptionist, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/activity')->assertForbidden();
    }

    public function test_patient_creation_is_logged(): void
    {
        $this->actAsOwner();

        $patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Juan',
            'last_name' => 'Perez',
        ]);

        ActivityLogger::log(
            tenantId: (int) $this->clinic->id,
            userId: (int) $this->owner->id,
            event: 'patient.created',
            description: 'Paciente creado',
            metadata: ['entity' => 'patient', 'entity_id' => (int) $patient->id],
            ip: '127.0.0.1',
        );

        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $this->clinic->id,
            'user_id' => $this->owner->id,
            'event' => 'patient.created',
        ]);
    }

    public function test_clinic_activity_feed_hides_login_events(): void
    {
        $this->actAsOwner();

        ActivityLogger::log(
            tenantId: (int) $this->clinic->id,
            userId: (int) $this->owner->id,
            event: 'login',
            description: 'Inicio de sesion exitoso',
            metadata: ['channel' => 'spa'],
            ip: '127.0.0.1',
        );

        ActivityLogger::log(
            tenantId: (int) $this->clinic->id,
            userId: (int) $this->owner->id,
            event: 'patient.created',
            description: 'Paciente creado',
            metadata: ['entity' => 'patient', 'entity_id' => 9],
            ip: '127.0.0.1',
        );

        $response = $this->getJson('/api/activity')->assertOk();

        $events = collect($response->json('data'))->pluck('event')->all();
        $this->assertNotContains('login', $events);
        $this->assertContains('patient.created', $events);
    }

    public function test_login_logs_are_capped_to_three_per_user(): void
    {
        $user = $this->owner;
        $user->forceFill(['email_verified_at' => now()])->save();

        for ($i = 0; $i < 4; $i++) {
            ActivityLogger::log(
                tenantId: (int) $this->clinic->id,
                userId: (int) $user->id,
                event: 'login',
                description: 'Inicio de sesion exitoso',
                metadata: ['channel' => 'spa'],
                ip: '127.0.0.1',
            );
        }

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $loginCount = DB::table('activity_logs')
            ->where('tenant_id', $this->clinic->id)
            ->where('user_id', $user->id)
            ->where('event', 'login')
            ->count();

        $this->assertSame(3, $loginCount);
    }
}
