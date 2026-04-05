<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Reminder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_reminders_for_current_clinic(): void
    {
        Carbon::setTestNow('2026-04-06 09:00:00');

        $clinic = Clinic::create(['name' => 'Clinica Test']);
        app()->instance('activeClinic', $clinic);

        $user = User::create([
            'name' => 'Reminder User',
            'email' => 'reminder.user@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
        ]);

        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Mario',
            'last_name' => 'Perez',
            'email' => 'mario@example.com',
        ]);

        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(3),
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        Reminder::create([
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'channel' => 'email',
            'reminder_type' => '2h',
            'recipient_email' => 'mario@example.com',
            'error_message' => 'SMTP timeout',
            'sent_at' => Carbon::now(),
            'status' => 'failed',
        ]);

        $this->actingAs($user, 'sanctum');
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);

        $response = $this->getJson('/api/reminders?status=failed&reminder_type=2h&q=mario&from_date=2026-04-06&to_date=2026-04-06');

        $response->assertOk()
            ->assertJsonPath('summary.count', 1)
            ->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.recipient_email', 'mario@example.com');

        Carbon::setTestNow();
    }

    public function test_can_show_reminder_detail_with_history(): void
    {
        $clinic = Clinic::create(['name' => 'Clinica Test', 'timezone' => 'Europe/Madrid']);
        app()->instance('activeClinic', $clinic);

        $user = User::create([
            'name' => 'Reminder User 3',
            'email' => 'reminder.user3@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
        ]);

        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Nora',
            'last_name' => 'Ruiz',
            'email' => 'nora@example.com',
            'phone' => '600111222',
        ]);

        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'start_time' => now()->addHours(24),
            'end_time' => now()->addHours(25),
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        Reminder::create([
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'channel' => 'email',
            'reminder_type' => '24h',
            'recipient_email' => 'nora@example.com',
            'error_message' => 'SMTP timeout',
            'status' => 'failed',
        ]);

        $reminder = Reminder::create([
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'channel' => 'email',
            'reminder_type' => '24h',
            'recipient_email' => 'nora@example.com',
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        $this->actingAs($user, 'sanctum');
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);

        $response = $this->getJson('/api/reminders/' . $reminder->id);

        $response->assertOk()
            ->assertJsonPath('id', $reminder->id)
            ->assertJsonPath('patient.email', 'nora@example.com')
            ->assertJsonPath('clinic.name', 'Clinica Test');

        $this->assertCount(2, $response->json('history'));
    }

    public function test_can_resend_failed_reminder_and_create_new_sent_attempt(): void
    {
        Carbon::setTestNow('2026-04-06 10:00:00');

        $clinic = Clinic::create([
            'name' => 'Clinica Test',
            'timezone' => 'Europe/Madrid',
        ]);
        app()->instance('activeClinic', $clinic);

        $user = User::create([
            'name' => 'Reminder User 2',
            'email' => 'reminder.user2@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
        ]);

        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Laura',
            'last_name' => 'Diaz',
            'email' => 'laura@example.com',
        ]);

        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'start_time' => now()->addHours(2)->addMinutes(10),
            'end_time' => now()->addHours(3)->addMinutes(10),
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        $failedReminder = Reminder::create([
            'clinic_id' => $clinic->id,
            'appointment_id' => $appointment->id,
            'channel' => 'email',
            'reminder_type' => '2h',
            'recipient_email' => 'laura@example.com',
            'error_message' => 'Mailbox unavailable',
            'status' => 'failed',
        ]);

        Mail::fake();

        $this->actingAs($user, 'sanctum');
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinic::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureClinicIsActive::class);

        $response = $this->postJson('/api/reminders/' . $failedReminder->id . '/resend');

        $response->assertOk()
            ->assertJsonPath('message', 'Recordatorio reenviado correctamente.');

        Mail::assertSent(AppointmentReminderMail::class, function (AppointmentReminderMail $mail) use ($patient): bool {
            return $mail->hasTo($patient->email) && $mail->hoursBefore === 2;
        });

        $this->assertDatabaseHas('reminders', [
            'appointment_id' => $appointment->id,
            'reminder_type' => '2h',
            'recipient_email' => $patient->email,
            'status' => 'sent',
        ]);
        $this->assertSame(2, Reminder::query()->count());
        $this->assertNotNull($appointment->fresh()->reminder_2h_sent_at);

        Carbon::setTestNow();
    }
}
