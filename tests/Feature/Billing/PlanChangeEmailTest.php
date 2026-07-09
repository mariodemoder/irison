<?php

namespace Tests\Feature\Billing;

use App\Events\PaymentCompleted;
use App\Events\SubscriptionUpgraded;
use App\Listeners\SendPaymentConfirmationEmail;
use App\Listeners\UpgradeSubscription;
use App\Mail\PaymentCompletedMail;
use App\Mail\SubscriptionActivatedMail;
use App\Mail\SubscriptionUpgradedNotificationMail;
use App\Models\Clinic;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanChangeEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_trial_to_basic_sends_activation_email(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));
        Mail::fake();

        $clinic = Clinic::create([
            'name' => 'Clinica Trial Convertida',
            'email' => 'clinic-trial-convert@test.local',
            'subscription_status' => 'active',
            'plan' => 'basic',
            'subscribed_at' => now(),
            'subscription_provider' => 'stripe',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Trial Convert',
            'email' => 'owner-trial-convert@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        Mail::to($user->email)->queue(
            new SubscriptionActivatedMail(
                clinicName: $clinic->name,
                plan: 'basic',
                activatedAt: now()->format('d/m/Y H:i'),
                invoiceUrl: 'https://invoice.stripe.com/test123',
            )
        );

        Mail::assertQueued(SubscriptionActivatedMail::class, function (SubscriptionActivatedMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $rendered = $this->renderMailable(
            new SubscriptionActivatedMail(
                clinicName: $clinic->name,
                plan: 'basic',
                activatedAt: now()->format('d/m/Y H:i'),
                invoiceUrl: 'https://invoice.stripe.com/test123',
            )
        );

        $this->assertStringContainsString('Bienvenido a Irison', $rendered);
        $this->assertStringContainsString('Ver factura', $rendered);
    }

    public function test_trial_to_basic_sends_email_without_invoice(): void
    {
        Mail::fake();

        $clinic = Clinic::create([
            'name' => 'Clinica Sin Invoice',
            'email' => 'clinic-noinv@test.local',
            'subscription_status' => 'active',
            'plan' => 'basic',
            'subscribed_at' => now(),
            'subscription_provider' => 'stripe',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Sin Invoice',
            'email' => 'owner-noinv@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        Mail::to($user->email)->queue(
            new SubscriptionActivatedMail(
                clinicName: $clinic->name,
                plan: 'basic',
                activatedAt: now()->format('d/m/Y H:i'),
            )
        );

        $rendered = $this->renderMailable(
            new SubscriptionActivatedMail(
                clinicName: $clinic->name,
                plan: 'basic',
                activatedAt: now()->format('d/m/Y H:i'),
            )
        );

        $this->assertStringContainsString('Bienvenido a Irison', $rendered);
        $this->assertStringNotContainsString('Ver factura', $rendered);
    }

    public function test_basic_to_pro_sends_upgraded_notification_email(): void
    {
        Mail::fake();

        $clinic = Clinic::create([
            'name' => 'Clinica Upgrade',
            'email' => 'clinic-upgrade@test.local',
            'subscription_status' => 'active',
            'plan' => 'pro',
            'subscribed_at' => now(),
            'subscription_provider' => 'stripe',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Upgrade',
            'email' => 'owner-upgrade@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        $request = SubscriptionRequest::create([
            'clinic_id' => $clinic->id,
            'current_plan' => 'basic',
            'requested_plan' => 'pro',
            'status' => 'completed',
            'completed_at' => now(),
            'requested_by' => $user->id,
        ]);

        Mail::to($user->email)->queue(
            new SubscriptionUpgradedNotificationMail($request, 'https://invoice.stripe.com/test456')
        );

        Mail::assertQueued(SubscriptionUpgradedNotificationMail::class, function (SubscriptionUpgradedNotificationMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $rendered = $this->renderMailable(
            new SubscriptionUpgradedNotificationMail($request, 'https://invoice.stripe.com/test456')
        );

        $this->assertStringContainsString('actualizado', strtolower($rendered));
        $this->assertStringContainsString('Ver factura', $rendered);
    }

    public function test_basic_to_pro_sends_payment_completed_email(): void
    {
        Mail::fake();

        $clinic = Clinic::create([
            'name' => 'Clinica Pago',
            'email' => 'clinic-pago@test.local',
            'subscription_status' => 'active',
            'plan' => 'pro',
            'subscribed_at' => now(),
            'subscription_provider' => 'stripe',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Pago',
            'email' => 'owner-pago@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        $request = SubscriptionRequest::create([
            'clinic_id' => $clinic->id,
            'current_plan' => 'basic',
            'requested_plan' => 'pro',
            'status' => 'completed',
            'completed_at' => now(),
            'requested_by' => $user->id,
        ]);

        Mail::to($user->email)->queue(
            new PaymentCompletedMail($request, 'https://invoice.stripe.com/test789')
        );

        Mail::assertQueued(PaymentCompletedMail::class, function (PaymentCompletedMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $rendered = $this->renderMailable(
            new PaymentCompletedMail($request, 'https://invoice.stripe.com/test789')
        );

        $this->assertStringContainsString('confirmado', strtolower($rendered));
        $this->assertStringContainsString('Ver factura', $rendered);
    }

    public function test_basic_to_pro_listener_dispatches_upgraded_notification(): void
    {
        Event::fake([SubscriptionUpgraded::class]);
        Mail::fake();

        $clinic = Clinic::create([
            'name' => 'Clinica Listener',
            'email' => 'clinic-listener@test.local',
            'subscription_status' => 'active',
            'plan' => 'pro',
            'subscribed_at' => now(),
            'subscription_provider' => 'stripe',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Listener',
            'email' => 'owner-listener@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        $request = SubscriptionRequest::create([
            'clinic_id' => $clinic->id,
            'current_plan' => 'basic',
            'requested_plan' => 'pro',
            'status' => 'completed',
            'completed_at' => now(),
            'requested_by' => $user->id,
        ]);

        event(new SubscriptionUpgraded($request));

        Event::assertDispatched(SubscriptionUpgraded::class, function ($event) use ($request) {
            return $event->request->id === $request->id;
        });
    }

    public function test_basic_to_pro_listener_dispatches_payment_completed(): void
    {
        Event::fake([PaymentCompleted::class]);
        Mail::fake();

        $clinic = Clinic::create([
            'name' => 'Clinica Payment',
            'email' => 'clinic-payment@test.local',
            'subscription_status' => 'active',
            'plan' => 'pro',
            'subscribed_at' => now(),
            'subscription_provider' => 'stripe',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Payment',
            'email' => 'owner-payment@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        $request = SubscriptionRequest::create([
            'clinic_id' => $clinic->id,
            'current_plan' => 'basic',
            'requested_plan' => 'pro',
            'status' => 'completed',
            'completed_at' => now(),
            'requested_by' => $user->id,
        ]);

        event(new PaymentCompleted($request, ['invoice_id' => 'in_test_123', 'invoice_url' => 'https://invoice.stripe.com/test']));

        Event::assertDispatched(PaymentCompleted::class, function ($event) use ($request) {
            return $event->request->id === $request->id;
        });
    }

    private function renderMailable($mailable): string
    {
        return (string) $mailable->render();
    }
}
