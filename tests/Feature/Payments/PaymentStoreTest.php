<?php

namespace Tests\Feature\Payments;

use App\Http\Middleware\CheckSubscriptionAccess;
use App\Http\Middleware\EnsureClinic;
use App\Http\Middleware\EnsureClinicIsActive;
use App\Models\Bonus;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_package_payment_success_for_same_patient_and_clinic(): void
    {
        $clinic = Clinic::create(['name' => 'Clinica QA']);
        app()->instance('activeClinic', $clinic);

        $user = User::create([
            'name' => 'QA User',
            'email' => 'qa.payments@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
            'role' => 'owner',
        ]);

        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Paciente',
            'last_name' => 'QA',
            'counter' => 'PC-000001',
        ]);

        $bonus = Bonus::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'name' => 'Bono QA',
            'total_sessions' => 10,
            'remaining_sessions' => 10,
            'price' => 1700,
            'counter' => 'B0-000001',
            'expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($user, 'sanctum');
        $this->withoutMiddleware(EnsureClinic::class);
        $this->withoutMiddleware(EnsureClinicIsActive::class);
        $this->withoutMiddleware(CheckSubscriptionAccess::class);

        $payload = [
            'patient_id' => $patient->id,
            'concept' => 'package',
            'amount' => 1700,
            'method' => 'cash',
            'status' => 'completed',
            'notes' => 'Pago QA de bono',
            'paid_at' => now()->format('Y-m-d\TH:i'),
            'appointment_id' => null,
            'package_id' => $bonus->id,
        ];

        $response = $this->postJson('/api/payments', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('patient_id', $patient->id)
            ->assertJsonPath('concept', 'package')
            ->assertJsonPath('package_id', $bonus->id)
            ->assertJsonPath('method', 'cash')
            ->assertJsonPath('status', 'completed');

        $this->assertDatabaseHas('payments', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'package_id' => $bonus->id,
            'concept' => 'package',
            'method' => 'cash',
            'status' => 'completed',
        ]);
    }
}
