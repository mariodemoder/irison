<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Bonus;
use App\Models\Clinic;
use App\Models\ConsentTemplate;
use App\Models\Document;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\PatientPortalNotification;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Base test case for the Patient Portal.
 *
 * Sets up two clinics and three patients (primary, same-clinic peer and
 * foreign-clinic peer) and authenticates the primary patient through the real
 * login endpoint so the full Sanctum token flow is exercised.
 */
abstract class PatientPortalTestCase extends TestCase
{
    use RefreshDatabase;

    protected Clinic $clinic;
    protected Clinic $otherClinic;

    protected Patient $patient;
    protected Patient $otherPatient;
    protected Patient $foreignPatient;

    protected string $plainPassword = 'password123';
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->patient = $this->makePatient($this->clinic, 'patient@portal.test');
        $this->otherPatient = $this->makePatient($this->clinic, 'other@portal.test');
        $this->foreignPatient = $this->makePatient($this->otherClinic, 'foreign@portal.test');

        $this->token = $this->loginAsPatient($this->patient);
    }

    protected function makePatient(Clinic $clinic, string $email, string $status = 'active'): Patient
    {
        return Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Paciente',
            'last_name' => 'Test',
            'email' => $email,
            'password' => Hash::make($this->plainPassword),
            'status' => $status,
        ]);
    }

    /**
     * Authenticate through the real login endpoint and return the bearer token.
     */
    protected function loginAsPatient(Patient $patient): string
    {
        $response = $this->postJson('/api/patient/auth/login', [
            'email' => $patient->email,
            'password' => $this->plainPassword,
        ]);

        $response->assertOk();

        return (string) $response->json('token');
    }

    /**
     * Headers carrying the primary patient's bearer token.
     */
    protected function patientHeaders(?string $token = null): array
    {
        return [
            'Authorization' => 'Bearer ' . ($token ?? $this->token),
            'Accept' => 'application/json',
        ];
    }

    protected function makeAppointment(Patient $patient, array $overrides = []): Appointment
    {
        return Appointment::create(array_merge([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'start_time' => now()->addDays(3)->setTime(10, 0),
            'end_time' => now()->addDays(3)->setTime(11, 0),
            'status' => 'scheduled',
        ], $overrides));
    }

    protected function makeBonus(Patient $patient, array $overrides = []): Bonus
    {
        return Bonus::create(array_merge([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'name' => 'Pack Test',
            'total_sessions' => 10,
            'remaining_sessions' => 5,
            'price' => 100,
        ], $overrides));
    }

    protected function makePayment(Patient $patient, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'concept' => 'other',
            'amount' => 45,
            'method' => 'cash',
            'status' => 'pending',
        ], $overrides));
    }

    protected function makeNotification(Patient $patient, array $overrides = []): PatientPortalNotification
    {
        return PatientPortalNotification::create(array_merge([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'type' => 'appointment_reminder',
            'title' => 'Recordatorio',
            'body' => 'Le recordamos su cita.',
        ], $overrides));
    }

    /**
     * Creates a published consent template for the patient's clinic.
     */
    protected function makeConsentTemplate(Clinic $clinic): ConsentTemplate
    {
        return ConsentTemplate::create([
            'clinic_id' => $clinic->id,
            'title' => 'Consentimiento Informado',
            'content' => '<p>Contenido del consentimiento.</p>',
            'version' => 1,
            'status' => 'published',
        ]);
    }

    /**
     * Creates a consent (with its template) for the given patient.
     */
    protected function makeConsentFor(Patient $patient, array $overrides = []): PatientConsent
    {
        $template = $this->makeConsentTemplate($patient->clinic);

        return PatientConsent::create(array_merge([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'template_id' => $template->id,
            'template_version' => $template->version,
            'status' => 'sent',
            'sent_at' => now(),
        ], $overrides));
    }

    /**
     * Creates an invoice-like document for the given patient.
     */
    protected function makeDocumentFor(Patient $patient, string $counter): Document
    {
        return Document::create([
            'clinic_id' => $patient->clinic_id,
            'patient_id' => $patient->id,
            'type' => Document::TYPE_INVOICE,
            'typeinvoice' => 'appointment',
            'counter' => $counter,
            'date' => now(),
            'amount' => 120,
        ]);
    }

    /**
     * Creates an appointment type for the clinic (needed by session lines).
     */
    protected function makeAppointmentType(Clinic $clinic): AppointmentType
    {
        return AppointmentType::create([
            'clinic_id' => $clinic->id,
            'description' => 'Sesión de fisioterapia',
            'estimated_minutes' => 60,
            'price' => 40,
        ]);
    }
}
