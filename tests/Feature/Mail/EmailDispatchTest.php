<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\AccountActivationMail;
use App\Mail\AppointmentReminderMail;
use App\Mail\ContactMail;
use App\Mail\ConsentSignRequestMail;
use App\Mail\TrialLifecycleMail;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ConsentTemplate;
use App\Models\Patient;
use App\Models\PatientConsent;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Modules\Booking\Notifications\BookingConfirmation;
use Modules\Booking\Notifications\NewOnlineBooking;
use Modules\Subscriptions\Infrastructure\Mail\InvoicePaymentFailedMail;
use Modules\Subscriptions\Infrastructure\Mail\SubscriptionActivatedMail;
use Modules\Subscriptions\Infrastructure\Mail\SubscriptionCanceledInternalMail;
use Modules\Subscriptions\Infrastructure\Mail\SubscriptionUpgradedNotificationMail;
use App\Notifications\ResetPasswordNotificationEs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $owner;

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
    }

    private function renderMailMessage($mailMessage): string
    {
        return (string) $mailMessage->render();
    }

    private function renderMailable($mailable): string
    {
        return (string) $mailable->render();
    }

    private function assertHasIrisonLogo(string $html, string $label = ''): void
    {
        $prefix = $label ? "[$label] " : '';
        $this->assertStringContainsString(
            asset('logo.svg'),
            $html,
            $prefix . 'No contiene el logo de Irison'
        );
        $this->assertStringNotContainsString(
            'laravel.com/img/notification-logo',
            $html,
            $prefix . 'Aun contiene el logo de Laravel'
        );
    }

    private function createPatient(array $overrides = []): Patient
    {
        return Patient::create(array_merge([
            'clinic_id' => $this->clinic->id,
            'first_name' => 'Paciente',
            'last_name' => 'Test',
            'email' => 'paciente@test.test',
        ], $overrides));
    }

    private function createProfessional(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Dr. Test',
            'email' => 'doctor@test.test',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
            'role' => 'owner',
        ], $overrides));
    }

    private function createAppointment(Patient $patient, array $overrides = []): Appointment
    {
        return Appointment::create(array_merge([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'status' => 'scheduled',
            'confirmation_token' => 'tok_' . uniqid(),
        ], $overrides));
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

    // ===============================================================
    //  NOTIFICATIONS — envio
    // ===============================================================

    public function test_reset_password_notification_is_sent(): void
    {
        Notification::fake();
        $this->owner->notify(new ResetPasswordNotificationEs('test-token'));
        Notification::assertSentTo($this->owner, ResetPasswordNotificationEs::class);
    }

    public function test_booking_confirmation_notification_is_sent(): void
    {
        Notification::fake();
        $patient = $this->createPatient();
        $professional = $this->createProfessional();
        $appointment = $this->createAppointment($patient, [
            'professional_id' => $professional->id,
        ]);
        Notification::send([$patient], new BookingConfirmation($appointment));
        Notification::assertSentTo($patient, BookingConfirmation::class);
    }

    public function test_new_online_booking_notification_is_sent(): void
    {
        Notification::fake();
        $patient = $this->createPatient(['email' => 'carlos@paciente.test']);
        $appointment = $this->createAppointment($patient, [
            'start_time' => now()->addDay()->setTime(14, 0),
            'end_time' => now()->addDay()->setTime(15, 0),
            'booking_source' => 'online',
        ]);
        Notification::send([$this->owner], new NewOnlineBooking($appointment));
        Notification::assertSentTo($this->owner, NewOnlineBooking::class);
    }

    // ===============================================================
    //  NOTIFICATIONS — logo Irison (via Markdown mail layout)
    // ===============================================================

    public function test_reset_password_notification_renders_irison_logo(): void
    {
        $mail = (new ResetPasswordNotificationEs('test-token'))->toMail($this->owner);
        $this->assertHasIrisonLogo($this->renderMailMessage($mail), 'ResetPasswordNotificationEs');
    }

    public function test_booking_confirmation_notification_renders_irison_logo(): void
    {
        $patient = $this->createPatient();
        $professional = $this->createProfessional();
        $appointment = $this->createAppointment($patient, [
            'professional_id' => $professional->id,
        ]);
        $mail = (new BookingConfirmation($appointment))->toMail($patient);
        $this->assertHasIrisonLogo($this->renderMailMessage($mail), 'BookingConfirmation');
    }

    public function test_new_online_booking_notification_renders_irison_logo(): void
    {
        $patient = $this->createPatient();
        $appointment = $this->createAppointment($patient);
        $mail = (new NewOnlineBooking($appointment))->toMail($this->owner);
        $this->assertHasIrisonLogo($this->renderMailMessage($mail), 'NewOnlineBooking');
    }

    // ===============================================================
    //  MAILABLES — contenido (renderizan sin errores)
    // ===============================================================

    public function test_account_activation_mail_renders_and_contains_expected_content(): void
    {
        $user = User::create([
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@irison.test',
            'email_verified_at' => null,
            'password' => Hash::make('password'),
            'clinic_id' => $this->clinic->id,
        ]);
        $rendered = $this->renderMailable(
            new AccountActivationMail($user, 'https://app.irison.test/activate?token=abc')
        );
        $this->assertStringContainsString('Activa tu cuenta', $rendered);
        $this->assertStringContainsString('Nuevo Usuario', $rendered);
    }

    public function test_subscription_canceled_internal_mail_renders(): void
    {
        $rendered = $this->renderMailable(new SubscriptionCanceledInternalMail(
            clinicName: $this->clinic->name,
            clinicId: $this->clinic->id,
            clinicEmail: $this->clinic->email,
            stripeCustomerId: 'cus_test_123',
            stripeSubscriptionId: 'sub_test_456',
        ));
        $this->assertStringContainsString('cancelada', $rendered);
    }

    public function test_contact_mail_renders(): void
    {
        $rendered = $this->renderMailable(new ContactMail(
            clinicId: $this->clinic->id,
            clinicName: $this->clinic->name,
            senderName: 'Test User',
            senderEmail: 'test@irison.test',
            contactSubject: 'Problema con facturacion',
            body: 'Tengo un error al generar facturas.',
        ));
        $this->assertStringContainsString('facturacion', $rendered);
    }

    public function test_invoice_payment_failed_mail_renders(): void
    {
        $rendered = $this->renderMailable(new InvoicePaymentFailedMail($this->clinic, (object) [
            'id' => 'in_test_123',
            'amount_due' => 4900,
            'currency' => 'eur',
            'next_payment_attempt' => (int) now()->addDays(3)->timestamp,
        ]));
        $this->assertStringContainsString('pendiente', $rendered);
    }

    public function test_consent_sign_request_mail_renders(): void
    {
        $patient = $this->createPatient(['first_name' => 'Laura', 'last_name' => 'Martinez']);
        $template = ConsentTemplate::create([
            'clinic_id' => $this->clinic->id,
            'title' => 'Consentimiento general',
            'content' => '<p>Contenido</p>',
            'version' => 1,
            'status' => 'active',
        ]);
        $consent = PatientConsent::create([
            'clinic_id' => $this->clinic->id,
            'patient_id' => $patient->id,
            'template_id' => $template->id,
            'template_version' => 1,
            'status' => 'pending',
            'token' => 'consent_abc',
            'token_expires_at' => now()->addHours(72),
        ]);
        $rendered = $this->renderMailable(
            new ConsentSignRequestMail($consent, 'https://app.irison.test/consent/sign/consent_abc')
        );
        $this->assertStringContainsString('Laura', $rendered);
        $this->assertStringContainsString('Consentimiento general', $rendered);
    }

    public function test_appointment_reminder_mail_renders(): void
    {
        $patient = $this->createPatient(['first_name' => 'Pedro', 'last_name' => 'Sanchez']);
        $appointment = $this->createAppointment($patient, [
            'start_time' => now()->addHours(24),
            'end_time' => now()->addHours(25),
        ]);
        $rendered = $this->renderMailable(new AppointmentReminderMail($appointment, 24));
        $this->assertStringContainsString('24', $rendered);
        $this->assertStringContainsString('Pedro', $rendered);
    }

    public function test_trial_lifecycle_mail_day_1_renders(): void
    {
        $rendered = $this->renderMailable(new TrialLifecycleMail(
            clinic: $this->clinic,
            milestone: 'day_1',
            subjectLine: 'Bienvenida a tu trial de Irison',
            headline: 'Bienvenido!',
            message: 'Tu periodo de prueba ha comenzado.',
        ));
        $this->assertStringContainsString('DAY_1', $rendered);
        $this->assertStringContainsString('Bienvenido!', $rendered);
    }

    public function test_trial_lifecycle_mail_day_7_renders(): void
    {
        $rendered = $this->renderMailable(new TrialLifecycleMail(
            clinic: $this->clinic,
            milestone: 'day_7',
            subjectLine: 'Tips para aprovechar Irison',
            headline: 'Sigue asi!',
            message: 'Ya llevas una semana con Irison.',
        ));
        $this->assertStringContainsString('DAY_7', $rendered);
        $this->assertStringContainsString('Sigue asi!', $rendered);
    }

    public function test_trial_lifecycle_mail_day_20_renders(): void
    {
        $rendered = $this->renderMailable(new TrialLifecycleMail(
            clinic: $this->clinic,
            milestone: 'day_20',
            subjectLine: 'Tu trial termina pronto',
            headline: 'Quedan pocos dias',
            message: 'Tu trial termina en 10 dias.',
        ));
        $this->assertStringContainsString('DAY_20', $rendered);
        $this->assertStringContainsString('Quedan pocos dias', $rendered);
    }

    public function test_trial_lifecycle_mail_day_27_renders(): void
    {
        $rendered = $this->renderMailable(new TrialLifecycleMail(
            clinic: $this->clinic,
            milestone: 'day_27',
            subjectLine: 'Ultimos dias para convertir tu trial',
            headline: 'No lo dejes pasar!',
            message: 'Tu trial termina en 3 dias.',
        ));
        $this->assertStringContainsString('DAY_27', $rendered);
        $this->assertStringContainsString('No lo dejes pasar!', $rendered);
    }

    public function test_trial_lifecycle_mail_day_30_renders(): void
    {
        $rendered = $this->renderMailable(new TrialLifecycleMail(
            clinic: $this->clinic,
            milestone: 'day_30',
            subjectLine: 'Tu trial llego al limite',
            headline: 'Ultimo dia',
            message: 'Hoy es el ultimo dia de tu trial.',
        ));
        $this->assertStringContainsString('DAY_30', $rendered);
        $this->assertStringContainsString('Ultimo dia', $rendered);
    }

    public function test_subscription_activated_mail_renders(): void
    {
        $rendered = $this->renderMailable(new SubscriptionActivatedMail(
            clinicName: $this->clinic->name,
            plan: 'basic',
            activatedAt: now()->format('d/m/Y H:i'),
            invoiceUrl: 'https://invoice.stripe.com/test',
        ));
        $this->assertStringContainsString('Bienvenido a Irison', $rendered);
        $this->assertStringContainsString('Clinica Irison Test', $rendered);
        $this->assertStringContainsString('Descargar factura', $rendered);
    }

    public function test_subscription_activated_mail_renders_without_invoice(): void
    {
        $rendered = $this->renderMailable(new SubscriptionActivatedMail(
            clinicName: $this->clinic->name,
            plan: 'basic',
            activatedAt: now()->format('d/m/Y H:i'),
        ));
        $this->assertStringContainsString('Bienvenido a Irison', $rendered);
        $this->assertStringNotContainsString('Ver factura', $rendered);
    }

    public function test_subscription_upgraded_notification_mail_renders(): void
    {
        $request = $this->createSubscriptionRequest([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $rendered = $this->renderMailable(new SubscriptionUpgradedNotificationMail(
            $request,
            'https://invoice.stripe.com/test',
        ));
        $this->assertStringContainsString('actualizado', strtolower($rendered));
        $this->assertStringContainsString('Descargar factura', $rendered);
    }

    public function test_subscription_upgraded_notification_mail_renders_without_invoice(): void
    {
        $request = $this->createSubscriptionRequest([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $rendered = $this->renderMailable(new SubscriptionUpgradedNotificationMail($request));
        $this->assertStringContainsString('actualizado', strtolower($rendered));
        $this->assertStringNotContainsString('Ver factura', $rendered);
    }
}
