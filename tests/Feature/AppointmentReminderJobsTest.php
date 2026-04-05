<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendAppointmentReminder24h;
use App\Jobs\SendAppointmentReminder2h;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Reminder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppointmentReminderJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_24h_reminder_and_prevent_duplicates(): void
    {
        Carbon::setTestNow('2026-04-05 10:00:00');

        $clinic = Clinic::create([
            'name' => 'Clinica Test',
            'timezone' => 'Europe/Madrid',
        ]);

        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'email' => 'ana@example.com',
        ]);

        $appointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'start_time' => Carbon::now()->addHours(24)->addMinutes(30),
            'end_time' => Carbon::now()->addHours(25)->addMinutes(30),
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        Mail::fake();

        app(SendAppointmentReminder24h::class)->handle();

        Mail::assertSent(AppointmentReminderMail::class, function (AppointmentReminderMail $mail) use ($patient): bool {
            return $mail->hasTo($patient->email) && $mail->hoursBefore === 24;
        });

        $this->assertNotNull($appointment->fresh()->reminder_24h_sent_at);

        app(SendAppointmentReminder24h::class)->handle();

        Mail::assertSentCount(1);
        $this->assertDatabaseHas('reminders', [
            'appointment_id' => $appointment->id,
            'reminder_type' => '24h',
            'recipient_email' => $patient->email,
            'status' => 'sent',
        ]);

        Carbon::setTestNow();
    }

    public function test_send_2h_reminder_only_for_scheduled_with_email(): void
    {
        Carbon::setTestNow('2026-04-05 10:00:00');

        $clinic = Clinic::create([
            'name' => 'Clinica Test',
            'timezone' => 'Europe/Madrid',
        ]);

        $withEmail = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Luis',
            'last_name' => 'Santos',
            'email' => 'luis@example.com',
        ]);

        $withoutEmail = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'No',
            'last_name' => 'Email',
            'email' => null,
        ]);

        $validAppointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $withEmail->id,
            'start_time' => Carbon::now()->addHours(2)->addMinutes(30),
            'end_time' => Carbon::now()->addHours(3)->addMinutes(30),
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        $canceledAppointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $withEmail->id,
            'start_time' => Carbon::now()->addHours(2)->addMinutes(10),
            'end_time' => Carbon::now()->addHours(3)->addMinutes(10),
            'status' => 'canceled',
            'payment_status' => 'pending',
        ]);

        $noEmailAppointment = Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $withoutEmail->id,
            'start_time' => Carbon::now()->addHours(2)->addMinutes(20),
            'end_time' => Carbon::now()->addHours(3)->addMinutes(20),
            'status' => 'scheduled',
            'payment_status' => 'pending',
        ]);

        Mail::fake();

        app(SendAppointmentReminder2h::class)->handle();

        Mail::assertSent(AppointmentReminderMail::class, function (AppointmentReminderMail $mail) use ($withEmail): bool {
            return $mail->hasTo($withEmail->email) && $mail->hoursBefore === 2;
        });

        Mail::assertSentCount(1);

        $this->assertNotNull($validAppointment->fresh()->reminder_2h_sent_at);
        $this->assertNull($canceledAppointment->fresh()->reminder_2h_sent_at);
        $this->assertNull($noEmailAppointment->fresh()->reminder_2h_sent_at);
        $this->assertDatabaseHas('reminders', [
            'appointment_id' => $validAppointment->id,
            'reminder_type' => '2h',
            'recipient_email' => $withEmail->email,
            'status' => 'sent',
        ]);
        $this->assertSame(1, Reminder::query()->count());

        Carbon::setTestNow();
    }
}
