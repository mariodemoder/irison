<?php

namespace Tests\Feature\Authorization;

use App\Http\Middleware\EnsureClinic;
use App\Http\Middleware\EnsureClinicIsActive;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Document;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureClinic::class);
        $this->withoutMiddleware(EnsureClinicIsActive::class);
    }

    private function createClinicWithTrial(string $name): Clinic
    {
        return Clinic::create([
            'name' => $name,
            'subscription_status' => 'trial',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    public function test_patient_show_denies_access_to_other_clinic_with_403(): void
    {
        $clinicA = $this->createClinicWithTrial('Clinic A');
        $clinicB = $this->createClinicWithTrial('Clinic B');

        $userA = $this->createUserForClinic($clinicA, 'owner.a@example.test');

        $patientB = Patient::create([
            'clinic_id' => $clinicB->id,
            'first_name' => 'Paciente',
            'last_name' => 'Externo',
        ]);

        $this->actingAs($userA, 'sanctum');
        app()->instance('activeClinic', null);

        $this->getJson('/api/patients/' . $patientB->id)
            ->assertNotFound();
    }

    public function test_document_show_denies_access_to_other_clinic_with_403(): void
    {
        $clinicA = $this->createClinicWithTrial('Clinic A');
        $clinicB = $this->createClinicWithTrial('Clinic B');

        $userA = $this->createUserForClinic($clinicA, 'owner.doc@example.test');

        $patientB = Patient::create([
            'clinic_id' => $clinicB->id,
            'first_name' => 'Paciente',
            'last_name' => 'Documento',
        ]);

        $documentB = Document::create([
            'clinic_id' => $clinicB->id,
            'patient_id' => $patientB->id,
            'type' => 'invoice',
            'type_from' => 'manual',
            'counter' => 'DOC-0001',
            'typeinvoice' => 'manual',
            'date' => now()->toDateString(),
            'amount' => 100,
            'status' => 'issued',
        ]);

        $this->actingAs($userA, 'sanctum');
        app()->instance('activeClinic', null);

        $this->getJson('/api/documents/' . $documentB->id)
            ->assertNotFound();
    }

    public function test_payment_update_denies_refunded_payment_with_403(): void
    {
        $clinic = $this->createClinicWithTrial('Clinic A');
        $user = $this->createUserForClinic($clinic, 'owner.pay.refunded@example.test');

        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Pago',
            'last_name' => 'Refunded',
        ]);

        $payment = Payment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'concept' => 'credit',
            'amount' => 50,
            'method' => 'cash',
            'status' => 'refunded',
            'counter' => 'PAY-0001',
            'paid_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum');
        app()->instance('activeClinic', null);

        $this->putJson('/api/payments/' . $payment->id, [])
            ->assertForbidden();
    }

    public function test_payment_update_denies_when_related_appointment_is_invoiced_with_403(): void
    {
        $clinic = $this->createClinicWithTrial('Clinic A');
        $user = $this->createUserForClinic($clinic, 'owner.pay.invoice@example.test');

        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Pago',
            'last_name' => 'Bloqueado',
        ]);

        $document = Document::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'type' => 'invoice',
            'type_from' => 'manual',
            'counter' => 'DOC-0001',
            'typeinvoice' => 'manual',
            'date' => now()->toDateString(),
            'amount' => 100,
            'status' => 'issued',
        ]);

        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'start_time' => now()->addDay()->startOfHour(),
            'end_time' => now()->addDay()->startOfHour()->addHour(),
            'invoice_id' => $document->id,
            'payment_status' => 'pending',
            'status' => 'scheduled',
        ]);

        $payment = Payment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'concept' => 'appointment',
            'amount' => 80,
            'method' => 'card',
            'status' => 'completed',
            'counter' => 'PAY-0002',
            'paid_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum');
        app()->instance('activeClinic', null);

        $this->putJson('/api/payments/' . $payment->id, [])
            ->assertForbidden();
    }

    private function createClinic(string $name): Clinic
    {
        return Clinic::create(['name' => $name]);
    }

    private function createUserForClinic(Clinic $clinic, string $email): User
    {
        return User::create([
            'name' => 'Owner ' . $clinic->id,
            'email' => $email,
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
            'role' => 'owner',
        ]);
    }
}
