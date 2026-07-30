<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Hash;
use Modules\Notifications\Backoffice\Notifications\CheckoutLinkGeneratedNotification;
use Modules\Notifications\Backoffice\Notifications\PaymentCompletedNotification;
use Modules\Notifications\Backoffice\Notifications\SubscriptionUpgradeRequestedNotification;
use Modules\Notifications\Backoffice\Notifications\SubscriptionUpgradedNotification;
use Modules\Notifications\Domain\Enums\ReminderType;
use Modules\Notifications\Patient\Notifications\AppointmentCancelledNotification;
use Modules\Notifications\Patient\Notifications\AppointmentCreatedNotification;
use Modules\Notifications\Patient\Notifications\AppointmentReminderNotification;
use Modules\Notifications\Patient\Notifications\AppointmentUpdatedNotification;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private Patient $patient;
    private User $owner;
    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = Clinic::create([
            'name' => 'Clinica Irison Test',
            'email' => 'clinica@irison.test',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'active',
            'plan' => 'pro',
            'trial_ends_at' => now()->addDays(30),
        ]);

        $this->owner = User::create([
            'name' => 'Owner Test',
            'email' => 'owner@irison.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'owner',
        ]);

        $this->patient = Patient::create([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Laura',
            'last_name' => 'Martinez',
            'email' => 'laura@paciente.test',
        ]);

        $this->appointment = Appointment::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $this->patient->id,
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'status' => 'scheduled',
            'confirmation_token' => 'tok_' . uniqid(),
        ]);
    }

    private function createSubscriptionRequest(array $overrides = []): SubscriptionRequest
    {
        return SubscriptionRequest::create(array_merge([
            'clinic_id' => $this->clinic->id,
            'current_plan' => 'basic',
            'requested_plan' => 'pro',
            'status' => 'pending',
            'requested_by' => $this->owner->id,
        ], $overrides));
    }

    private function renderMailMessage(MailMessage $mailMessage): string
    {
        return (string) $mailMessage->render();
    }

    // ===============================================================
    //  PATIENT NOTIFICATIONS — Appointment status
    // ===============================================================

    public function test_appointment_created_notification_renders(): void
    {
        $notification = new AppointmentCreatedNotification($this->appointment);
        $mail = $notification->toMail($this->patient);
        $rendered = $this->renderMailMessage($mail);

        $this->assertStringContainsString('Nueva cita', $mail->subject);
        $this->assertStringContainsString('se ha creado una nueva cita', strtolower($rendered));
        $this->assertStringContainsString('Laura', $rendered);
        $this->assertStringContainsString('Clinica Irison Test', $rendered);
    }

    public function test_appointment_updated_notification_renders_with_changes(): void
    {
        $changed = ['start_time' => now()->addDays(2)->setTime(14, 0), 'status' => 'rescheduled'];
        $notification = new AppointmentUpdatedNotification($this->appointment, $changed);
        $mail = $notification->toMail($this->patient);
        $rendered = $this->renderMailMessage($mail);

        $this->assertStringContainsString('Cita actualizada', $mail->subject);
        $this->assertStringContainsString('fecha/hora', $rendered);
        $this->assertStringContainsString('estado', $rendered);
        $this->assertStringContainsString('Laura', $rendered);
        $this->assertStringContainsString('Clinica Irison Test', $rendered);
    }

    public function test_appointment_updated_notification_renders_without_changes(): void
    {
        $notification = new AppointmentUpdatedNotification($this->appointment, []);
        $mail = $notification->toMail($this->patient);
        $rendered = $this->renderMailMessage($mail);

        $this->assertStringContainsString('Cita actualizada', $mail->subject);
        $this->assertStringContainsString('ha sido modificada', $rendered);
        $this->assertStringNotContainsString('fecha/hora', $rendered);
        $this->assertStringNotContainsString('estado', $rendered);
        $this->assertStringContainsString('Laura', $rendered);
    }

    public function test_appointment_cancelled_notification_renders(): void
    {
        $notification = new AppointmentCancelledNotification($this->appointment);
        $mail = $notification->toMail($this->patient);
        $rendered = $this->renderMailMessage($mail);

        $this->assertStringContainsString('Cita cancelada', $mail->subject);
        $this->assertStringContainsString('ha sido cancelada', $rendered);
        $this->assertStringContainsString('Laura', $rendered);
        $this->assertStringContainsString('Clinica Irison Test', $rendered);
    }

    // ===============================================================
    //  PATIENT NOTIFICATIONS — Appointment reminder
    // ===============================================================

    public function test_appointment_reminder_24h_notification_renders(): void
    {
        $notification = new AppointmentReminderNotification($this->appointment, ReminderType::TwentyFourHours);
        $mail = $notification->toMail($this->patient);
        $rendered = $this->renderMailMessage($mail);

        $this->assertStringContainsString('Recordatorio de cita', $mail->subject);
        $this->assertStringContainsString('Laura', $rendered);
        $this->assertStringContainsString('Clinica Irison Test', $rendered);
        $this->assertStringContainsString('24', $rendered);
    }

    public function test_appointment_reminder_2h_notification_renders(): void
    {
        $notification = new AppointmentReminderNotification($this->appointment, ReminderType::TwoHours);
        $mail = $notification->toMail($this->patient);
        $rendered = $this->renderMailMessage($mail);

        $this->assertStringContainsString('Recordatorio de cita', $mail->subject);
        $this->assertStringContainsString('Laura', $rendered);
        $this->assertStringContainsString('Clinica Irison Test', $rendered);
        $this->assertStringContainsString('2', $rendered);
    }

    // ===============================================================
    //  BACKOFFICE NOTIFICATIONS — Checkout link
    // ===============================================================

    public function test_checkout_link_generated_notification_to_database(): void
    {
        $request = $this->createSubscriptionRequest([
            'status' => 'waiting_payment',
            'stripe_checkout_session_id' => 'cs_test',
            'checkout_url' => 'https://checkout.stripe.com/pay/cs_test',
        ]);
        $notification = new CheckoutLinkGeneratedNotification($request);
        $msg = $notification->toDatabase($this->owner);

        $this->assertInstanceOf(DatabaseMessage::class, $msg);
        $this->assertSame('checkout_link_generated', $msg->data['type']);
        $this->assertSame('https://checkout.stripe.com/pay/cs_test', $msg->data['checkout_url']);
        $this->assertSame($request->id, $msg->data['request_id']);
    }

    public function test_checkout_link_generated_notification_to_mail(): void
    {
        $request = $this->createSubscriptionRequest([
            'status' => 'waiting_payment',
            'stripe_checkout_session_id' => 'cs_test',
            'checkout_url' => 'https://checkout.stripe.com/pay/cs_test',
        ]);
        $notification = new CheckoutLinkGeneratedNotification($request);
        $mail = $notification->toMail($this->owner);
        $rendered = $this->renderMailMessage($mail);

        $this->assertStringContainsString('Enlace de pago', $mail->subject);
        $this->assertStringContainsString('checkout.stripe.com', $rendered);
        $this->assertStringContainsString('Clinica Irison Test', $rendered);
    }

    // ===============================================================
    //  BACKOFFICE NOTIFICATIONS — Payment completed
    // ===============================================================

    public function test_payment_completed_notification_to_database(): void
    {
        $request = $this->createSubscriptionRequest([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $notification = new PaymentCompletedNotification($request);
        $msg = $notification->toDatabase($this->owner);

        $this->assertInstanceOf(DatabaseMessage::class, $msg);
        $this->assertSame('payment_completed', $msg->data['type']);
        $this->assertSame($request->id, $msg->data['request_id']);
        $this->assertStringContainsString('pago', strtolower($msg->data['message']));
    }

    public function test_payment_completed_notification_to_mail(): void
    {
        $request = $this->createSubscriptionRequest([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $notification = new PaymentCompletedNotification($request);
        $mail = $notification->toMail($this->owner);
        $rendered = $this->renderMailMessage($mail);

        $this->assertStringContainsString('Pago completado', $mail->subject);
        $this->assertStringContainsString('Pago confirmado', $rendered);
        $this->assertStringContainsString('Clinica Irison Test', $rendered);
    }

    // ===============================================================
    //  BACKOFFICE NOTIFICATIONS — Subscription upgrade requested
    // ===============================================================

    public function test_subscription_upgrade_requested_notification_to_database(): void
    {
        $request = $this->createSubscriptionRequest([
            'status' => 'pending',
            'requested_plan' => 'enterprise',
        ]);
        $notification = new SubscriptionUpgradeRequestedNotification($request);
        $msg = $notification->toDatabase($this->owner);

        $this->assertInstanceOf(DatabaseMessage::class, $msg);
        $this->assertSame('upgrade_requested', $msg->data['type']);
        $this->assertSame($request->id, $msg->data['request_id']);
        $this->assertSame('enterprise', $msg->data['plan']);
        $this->assertSame('Clinica Irison Test', $msg->data['clinic_name']);
        $this->assertSame('Owner Test', $msg->data['requester_name']);
    }

    // ===============================================================
    //  BACKOFFICE NOTIFICATIONS — Subscription upgraded
    // ===============================================================

    public function test_subscription_upgraded_notification_to_database(): void
    {
        $request = $this->createSubscriptionRequest([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $notification = new SubscriptionUpgradedNotification($request);
        $msg = $notification->toDatabase($this->owner);

        $this->assertInstanceOf(DatabaseMessage::class, $msg);
        $this->assertSame('subscription_upgraded', $msg->data['type']);
        $this->assertSame($request->id, $msg->data['request_id']);
        $this->assertSame($request->requested_plan, $msg->data['plan']);
        $this->assertStringContainsString('actualizado', strtolower($msg->data['message']));
    }

    public function test_subscription_upgraded_notification_to_mail(): void
    {
        $request = $this->createSubscriptionRequest([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $notification = new SubscriptionUpgradedNotification($request);
        $mail = $notification->toMail($this->owner);
        $rendered = $this->renderMailMessage($mail);

        $this->assertStringContainsString('Suscripción actualizada', $mail->subject);
        $this->assertStringContainsString('plan ha sido actualizado', $rendered);
        $this->assertStringContainsString('Clinica Irison Test', $rendered);
    }
}
