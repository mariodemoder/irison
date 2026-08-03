<?php

declare(strict_types=1);

namespace Modules\Notifications\Tests\Feature;

use App\Events\AppointmentCreated;
use App\Mail\ConsentSignRequestMail;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ConsentTemplate;
use App\Models\EmailLog;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\Reminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Modules\Notifications\Patient\Notifications\AppointmentCreatedNotification;
use Modules\Notifications\Patient\Notifications\AppointmentReminderNotification;
use Tests\TestCase;

class EmailLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeClinic(): Clinic
    {
        return Clinic::create([
            'name' => 'Clinica Email Log',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'status' => 'active',
        ]);
    }

    private function makeOwner(Clinic $clinic): User
    {
        return User::create([
            'name' => 'Email Log Owner',
            'email' => 'el.owner@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
            'role' => 'owner',
        ]);
    }

    private function makePatient(Clinic $clinic, string $email = 'patient@example.com'): Patient
    {
        return Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'email' => $email,
        ]);
    }

    private function makeAppointment(Clinic $clinic, Patient $patient): Appointment
    {
        return Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(3),
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);
    }

    public function test_sent_mailable_is_logged_with_clinic_and_patient_context(): void
    {
        $clinic = $this->makeClinic();
        app()->instance('activeClinic', $clinic);

        $patient = $this->makePatient($clinic);
        $template = ConsentTemplate::create([
            'clinic_id' => $clinic->id,
            'title' => 'Tratamiento',
            'content' => '<p>Contenido</p>',
            'status' => 'active',
        ]);
        $consent = PatientConsent::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'template_id' => $template->id,
            'template_version' => 1,
            'status' => 'pending',
        ]);

        Mail::to($patient->email)->send(new ConsentSignRequestMail($consent, 'http://localhost/firmar'));

        $this->assertDatabaseHas('email_logs', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'category' => 'consent_sign_request',
            'to_email' => $patient->email,
            'status' => 'sent',
        ]);
    }

    public function test_appointment_created_email_is_logged_with_context(): void
    {
        $clinic = $this->makeClinic();
        app()->instance('activeClinic', $clinic);

        $patient = $this->makePatient($clinic);
        $appointment = $this->makeAppointment($clinic, $patient);

        AppointmentCreated::dispatch($appointment);

        $this->assertDatabaseHas('email_logs', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'category' => 'appointment_created',
            'to_email' => $patient->email,
            'status' => 'sent',
        ]);
    }

    public function test_appointment_created_without_patient_email_is_logged_failed(): void
    {
        $clinic = $this->makeClinic();
        app()->instance('activeClinic', $clinic);

        $patient = $this->makePatient($clinic, '');
        $appointment = $this->makeAppointment($clinic, $patient);

        AppointmentCreated::dispatch($appointment);

        $this->assertDatabaseHas('email_logs', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'category' => 'appointment_created',
            'status' => 'failed',
        ]);
    }

    public function test_can_list_email_logs_for_current_clinic_with_filters(): void
    {
        $clinic = $this->makeClinic();
        app()->instance('activeClinic', $clinic);
        $user = $this->makeOwner($clinic);

        $patient = $this->makePatient($clinic);
        $appointment = $this->makeAppointment($clinic, $patient);

        EmailLog::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'category' => 'appointment_created',
            'to_email' => $patient->email,
            'subject' => 'Nueva cita - Clinica Email Log',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        EmailLog::create([
            'clinic_id' => $clinic->id,
            'category' => 'contact',
            'to_email' => 'contacto@example.com',
            'subject' => '[Contacto] Hola',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/notifications?status=sent&category=appointment_created&q=ana&from_date=' . now()->toDateString() . '&to_date=' . now()->toDateString());

        $response->assertOk()
            ->assertJsonPath('summary.count', 2)
            ->assertJsonPath('data.0.category', 'appointment_created')
            ->assertJsonPath('data.0.patient.email', $patient->email)
            ->assertJsonPath('data.0.appointment.id', $appointment->id);
    }

    public function test_can_show_email_log_detail(): void
    {
        $clinic = $this->makeClinic();
        app()->instance('activeClinic', $clinic);
        $user = $this->makeOwner($clinic);

        $patient = $this->makePatient($clinic);
        $appointment = $this->makeAppointment($clinic, $patient);

        $log = EmailLog::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'category' => 'appointment_created',
            'to_email' => $patient->email,
            'from_email' => 'no-reply@dueleahi.local',
            'subject' => 'Nueva cita - Clinica Email Log',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/notifications/' . $log->id);

        $response->assertOk()
            ->assertJsonPath('id', $log->id)
            ->assertJsonPath('category_label', 'Nueva cita')
            ->assertJsonPath('clinic.name', $clinic->name)
            ->assertJsonPath('patient.email', $patient->email);
    }

    public function test_resend_is_rejected_for_non_reminder_logs(): void
    {
        $clinic = $this->makeClinic();
        app()->instance('activeClinic', $clinic);
        $user = $this->makeOwner($clinic);

        $log = EmailLog::create([
            'clinic_id' => $clinic->id,
            'category' => 'appointment_created',
            'to_email' => 'x@example.com',
            'status' => 'failed',
            'error_message' => 'SMTP timeout',
            'sent_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/notifications/' . $log->id . '/resend');

        $response->assertStatus(422);
    }

    public function test_can_resend_reminder_from_email_log(): void
    {
        $clinic = $this->makeClinic();
        app()->instance('activeClinic', $clinic);
        $user = $this->makeOwner($clinic);

        $patient = $this->makePatient($clinic);
        $appointment = $this->makeAppointment($clinic, $patient);

        $failedReminder = Reminder::create([
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'channel' => 'email',
            'reminder_type' => '2h',
            'recipient_email' => $patient->email,
            'error_message' => 'Mailbox unavailable',
            'status' => 'failed',
        ]);

        $log = EmailLog::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'reminder_id' => $failedReminder->id,
            'category' => 'reminder_2h',
            'to_email' => $patient->email,
            'status' => 'failed',
            'error_message' => 'Mailbox unavailable',
            'sent_at' => now(),
        ]);

        Notification::fake();
        Mail::fake();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/notifications/' . $log->id . '/resend');

        $response->assertOk()
            ->assertJsonPath('message', 'Recordatorio reenviado correctamente.');

        Notification::assertSentTo(
            Notification::route('mail', $patient->email),
            AppointmentReminderNotification::class,
        );
    }

    public function test_rescheduling_appointment_sends_updated_email_and_logs_it(): void
    {
        $clinic = $this->makeClinic();
        app()->instance('activeClinic', $clinic);
        $user = $this->makeOwner($clinic);

        $this->actingAs($user, 'sanctum');
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);
        $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionAccess::class);

        $patient = $this->makePatient($clinic);
        $start = Carbon::now()->addDay()->setTime(9, 0);

        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'start_time' => $start,
            'end_time' => (clone $start)->addHour(),
            'status' => 'scheduled',
            'payment_status' => 'pending',
            'price' => 50,
            'payment_type' => 'single',
        ]);

        $response = $this->patchJson('/api/appointments/' . $appointment->id, [
            'date' => $start->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'rescheduled',
            'price' => 50,
            'payment_type' => 'single',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'rescheduled',
        ]);

        $this->assertDatabaseHas('email_logs', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'category' => 'appointment_updated',
            'to_email' => $patient->email,
            'status' => 'sent',
        ]);
    }

    public function test_rescheduling_appointment_without_patient_email_logs_failed(): void
    {
        $clinic = $this->makeClinic();
        app()->instance('activeClinic', $clinic);
        $user = $this->makeOwner($clinic);

        $this->actingAs($user, 'sanctum');
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);
        $this->withoutMiddleware(\App\Http\Middleware\CheckSubscriptionAccess::class);

        $patient = $this->makePatient($clinic, '');
        $start = Carbon::now()->addDay()->setTime(9, 0);

        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'start_time' => $start,
            'end_time' => (clone $start)->addHour(),
            'status' => 'scheduled',
            'payment_status' => 'pending',
            'price' => 50,
            'payment_type' => 'single',
        ]);

        $response = $this->patchJson('/api/appointments/' . $appointment->id, [
            'date' => $start->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'rescheduled',
            'price' => 50,
            'payment_type' => 'single',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('email_logs', [
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'category' => 'appointment_updated',
            'status' => 'failed',
        ]);
    }
}
