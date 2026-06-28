<?php

namespace Tests\Feature\Authorization;

use App\Http\Middleware\EnsureClinic;
use App\Http\Middleware\EnsureClinicIsActive;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Document;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $ownerUser;
    private User $adminUser;
    private User $managerUser;
    private User $professionalUser;
    private Patient $patient;
    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureClinic::class);
        $this->withoutMiddleware(EnsureClinicIsActive::class);

        $this->seedProfiles();

        $this->clinic = Clinic::create([
            'name' => 'Test Clinic',
            'subscription_status' => 'trial',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
        ]);

        $this->ownerUser = User::create([
            'name' => 'Owner User',
            'email' => 'owner@test.com',
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'owner',
            'profile_id' => Profile::where('slug', 'admin')->first()->id,
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'user',
            'profile_id' => Profile::where('slug', 'admin')->first()->id,
        ]);

        $this->managerUser = User::create([
            'name' => 'Manager User',
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'user',
            'profile_id' => Profile::where('slug', 'manager')->first()->id,
        ]);

        $this->professionalUser = User::create([
            'name' => 'Professional User',
            'email' => 'professional@test.com',
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'user',
            'profile_id' => Profile::where('slug', 'professional')->first()->id,
        ]);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Test',
            'last_name' => 'Patient',
        ]);

        $this->appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'professional_id' => $this->professionalUser->id,
            'start_time' => Carbon::now()->addDay()->startOfHour(),
            'end_time' => Carbon::now()->addDay()->startOfHour()->addHour(),
            'price' => 50,
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);
    }

    private function seedProfiles(): void
    {
        $profiles = [
            ['name' => 'Administrador', 'slug' => 'admin'],
            ['name' => 'Gestor', 'slug' => 'manager'],
            ['name' => 'Profesional', 'slug' => 'professional'],
        ];

        foreach ($profiles as $p) {
            Profile::firstOrCreate(['slug' => $p['slug']], $p);
        }
    }

    // ---------- ROLE HELPERS ----------

    public function test_owner_has_full_access(): void
    {
        $this->assertTrue($this->ownerUser->hasFullAccess());
        $this->assertTrue($this->ownerUser->isOwner());
        $this->assertFalse($this->ownerUser->isViewer());
    }

    public function test_admin_has_full_access(): void
    {
        $this->assertTrue($this->adminUser->hasFullAccess());
        $this->assertTrue($this->adminUser->isAdmin());
        $this->assertFalse($this->adminUser->isViewer());
    }

    public function test_manager_has_full_access(): void
    {
        $this->assertTrue($this->managerUser->hasFullAccess());
        $this->assertTrue($this->managerUser->isManager());
        $this->assertFalse($this->managerUser->isViewer());
    }

    public function test_professional_is_viewer(): void
    {
        $this->assertFalse($this->professionalUser->hasFullAccess());
        $this->assertTrue($this->professionalUser->isProfessional());
        $this->assertTrue($this->professionalUser->isViewer());
    }

    // ---------- APPOINTMENTS ----------

    public function test_full_access_users_can_create_appointments(): void
    {
        $date = Carbon::now()->addDays(2)->toDateString();

        $times = ['10:00', '12:00', '14:00'];

        foreach ([$this->ownerUser, $this->adminUser, $this->managerUser] as $i => $user) {
            $start = $times[$i];
            $end = Carbon::parse($date . ' ' . $start)->addHour()->format('H:i');

            $this->actingAs($user, 'sanctum');
            app()->instance('activeClinic', $this->clinic);

            $response = $this->postJson('/api/appointments', [
                'patient_id' => $this->patient->id,
                'date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'price' => 60,
            ]);

            $response->assertStatus(201);
        }
    }

    public function test_professional_cannot_create_appointments(): void
    {
        $payload = [
            'patient_id' => $this->patient->id,
            'start_time' => Carbon::now()->addDays(2)->startOfHour()->toDateTimeString(),
            'end_time' => Carbon::now()->addDays(2)->startOfHour()->addHour()->toDateTimeString(),
            'price' => 60,
        ];

        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->postJson('/api/appointments', $payload)
            ->assertForbidden();
    }

    public function test_professional_cannot_update_appointments(): void
    {
        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->putJson('/api/appointments/' . $this->appointment->id, ['status' => 'completed'])
            ->assertForbidden();
    }

    public function test_professional_cannot_delete_appointments(): void
    {
        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->deleteJson('/api/appointments/' . $this->appointment->id)
            ->assertForbidden();
    }

    public function test_professional_can_view_own_appointments(): void
    {
        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/appointments?date=' . Carbon::now()->addDay()->toDateString())
            ->assertOk();
    }

    public function test_professional_cannot_view_other_professional_appointments(): void
    {
        $otherProfessional = User::create([
            'name' => 'Other Prof',
            'email' => 'other.prof@test.com',
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'user',
            'profile_id' => Profile::where('slug', 'professional')->first()->id,
        ]);

        Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'professional_id' => $otherProfessional->id,
            'start_time' => Carbon::now()->addDay()->startOfHour(),
            'end_time' => Carbon::now()->addDay()->startOfHour()->addHour(),
            'price' => 50,
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $response = $this->getJson('/api/appointments?date=' . Carbon::now()->addDay()->toDateString());
        $response->assertOk();

        $appointments = $response->json();
        $this->assertCount(1, $appointments);
        $this->assertEquals($this->professionalUser->id, $appointments[0]['professional_id']);
    }

    public function test_professional_can_view_own_appointment_detail(): void
    {
        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/appointments/' . $this->appointment->id)
            ->assertOk();
    }

    public function test_professional_cannot_view_other_appointment_detail(): void
    {
        $other = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'professional_id' => null,
            'start_time' => Carbon::now()->addDay()->startOfHour(),
            'end_time' => Carbon::now()->addDay()->startOfHour()->addHour(),
            'price' => 50,
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/appointments/' . $other->id)
            ->assertForbidden();
    }

    // ---------- PATIENTS ----------

    public function test_full_access_users_can_create_patients(): void
    {
        foreach ([$this->ownerUser, $this->adminUser, $this->managerUser] as $user) {
            $this->actingAs($user, 'sanctum');
            app()->instance('activeClinic', $this->clinic);

            $this->postJson('/api/patients', ['name' => 'New Patient'])
                ->assertStatus(201);
        }
    }

    public function test_professional_cannot_create_patients(): void
    {
        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->postJson('/api/patients', ['name' => 'New Patient'])
            ->assertForbidden();
    }

    public function test_professional_cannot_update_patients(): void
    {
        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->putJson('/api/patients/' . $this->patient->id, ['name' => 'Updated'])
            ->assertForbidden();
    }

    public function test_professional_cannot_delete_patients(): void
    {
        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->deleteJson('/api/patients/' . $this->patient->id)
            ->assertForbidden();
    }

    public function test_professional_can_view_patients_list(): void
    {
        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/patients')
            ->assertOk();
    }

    public function test_professional_can_view_patient_detail(): void
    {
        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/patients/' . $this->patient->id)
            ->assertOk();
    }

    public function test_professional_patient_detail_has_no_financial_data(): void
    {
        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $response = $this->getJson('/api/patients/' . $this->patient->id);
        $response->assertOk();

        $data = $response->json();
        $this->assertArrayNotHasKey('available_credit', $data);
        $this->assertArrayNotHasKey('payments', $data);
        $this->assertArrayNotHasKey('packs', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('phone', $data);
        $this->assertArrayHasKey('email', $data);
    }

    public function test_owner_patient_detail_has_financial_data(): void
    {
        $this->actingAs($this->ownerUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $response = $this->getJson('/api/patients/' . $this->patient->id);
        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('available_credit', $data);
        $this->assertArrayHasKey('payments', $data);
        $this->assertArrayHasKey('packs', $data);
    }

    // ---------- PAYMENTS ----------

    public function test_full_access_users_can_view_payments(): void
    {
        Payment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'concept' => 'appointment',
            'amount' => 50,
            'method' => 'cash',
            'status' => 'completed',
            'counter' => 'PAY-001',
            'paid_at' => now(),
        ]);

        foreach ([$this->ownerUser, $this->adminUser, $this->managerUser] as $user) {
            $this->actingAs($user, 'sanctum');
            app()->instance('activeClinic', $this->clinic);

            $this->getJson('/api/payments')
                ->assertOk();
        }
    }

    public function test_professional_cannot_view_payments(): void
    {
        Payment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'concept' => 'appointment',
            'amount' => 50,
            'method' => 'cash',
            'status' => 'completed',
            'counter' => 'PAY-001',
            'paid_at' => now(),
        ]);

        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/payments')
            ->assertForbidden();
    }

    // ---------- PRODUCTS ----------

    public function test_full_access_users_can_view_products(): void
    {
        Product::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Test Product',
            'reference' => 'PROD-001',
            'price' => 10,
        ]);

        foreach ([$this->ownerUser, $this->adminUser, $this->managerUser] as $user) {
            $this->actingAs($user, 'sanctum');
            app()->instance('activeClinic', $this->clinic);

            $this->getJson('/api/products')
                ->assertOk();
        }
    }

    public function test_professional_cannot_view_products(): void
    {
        Product::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Test Product',
            'reference' => 'PROD-001',
            'price' => 10,
        ]);

        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/products')
            ->assertForbidden();
    }

    // ---------- DOCUMENTS ----------

    public function test_full_access_users_can_view_documents(): void
    {
        Document::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'type' => 'invoice',
            'type_from' => 'manual',
            'counter' => 'DOC-001',
            'typeinvoice' => 'manual',
            'date' => now()->toDateString(),
            'amount' => 100,
            'status' => 'issued',
        ]);

        foreach ([$this->ownerUser, $this->adminUser, $this->managerUser] as $user) {
            $this->actingAs($user, 'sanctum');
            app()->instance('activeClinic', $this->clinic);

            $this->getJson('/api/documents')
                ->assertOk();
        }
    }

    public function test_professional_cannot_view_documents(): void
    {
        Document::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'type' => 'invoice',
            'type_from' => 'manual',
            'counter' => 'DOC-001',
            'typeinvoice' => 'manual',
            'date' => now()->toDateString(),
            'amount' => 100,
            'status' => 'issued',
        ]);

        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/documents')
            ->assertForbidden();
    }

    // ---------- BONUSES ----------

    public function test_full_access_users_can_view_bonuses(): void
    {
        foreach ([$this->ownerUser, $this->adminUser, $this->managerUser] as $user) {
            $this->actingAs($user, 'sanctum');
            app()->instance('activeClinic', $this->clinic);

            $this->getJson('/api/bonuses')
                ->assertOk();
        }
    }

    public function test_professional_cannot_view_bonuses(): void
    {
        $this->actingAs($this->professionalUser, 'sanctum');
        app()->instance('activeClinic', $this->clinic);

        $this->getJson('/api/bonuses')
            ->assertForbidden();
    }
}
