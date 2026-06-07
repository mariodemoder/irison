<?php

namespace Tests\Feature;

use App\Mail\InvoicePaymentFailedMail;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_session_completed_persists_customer_id_on_clinic(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');

        $clinic = Clinic::create([
            'name' => 'Clinica Checkout',
            'email' => 'checkout-clinic@test.com',
            'subscription_status' => 'trial',
        ]);

        User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Checkout',
            'email' => 'owner-checkout@test.com',
            'password' => 'password',
            'role' => 'owner',
        ]);

        $payload = json_encode([
            'id' => 'evt_checkout_1',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'customer' => 'cus_checkout_123',
                    'subscription' => 'sub_checkout_123',
                    'customer_email' => 'owner-checkout@test.com',
                    'metadata' => (object) [],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->postStripeWebhook($payload, 'whsec_test_secret');

        $response->assertOk();

        $clinic->refresh();
        $this->assertSame('cus_checkout_123', $clinic->stripe_id);
        $this->assertSame('cus_checkout_123', $clinic->stripe_customer_id);
        $this->assertSame('active', $clinic->subscription_status);
    }

    public function test_checkout_session_completed_without_email_sets_subscribed_at_using_metadata_clinic_id(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');

        $clinic = Clinic::create([
            'name' => 'Clinica Metadata',
            'email' => 'metadata-clinic@test.com',
            'subscription_status' => 'trial',
            'subscribed_at' => null,
        ]);

        $payload = json_encode([
            'id' => 'evt_checkout_metadata_1',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_metadata_123',
                    'customer' => 'cus_checkout_metadata_123',
                    'subscription' => 'sub_checkout_metadata_123',
                    'customer_email' => null,
                    'metadata' => [
                        'clinic_id' => (string) $clinic->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->postStripeWebhook($payload, 'whsec_test_secret');

        $response->assertOk();

        $clinic->refresh();
        $this->assertNotNull($clinic->subscribed_at);
        $this->assertSame('active', $clinic->subscription_status);
        $this->assertSame('cus_checkout_metadata_123', $clinic->stripe_id);
        $this->assertSame('cus_checkout_metadata_123', $clinic->stripe_customer_id);
    }

    public function test_invoice_payment_failed_sets_subscription_status_to_past_due_without_canceling(): void
    {
        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');
        config()->set('billing.notify_on_invoice_payment_failed', false);

        $clinic = Clinic::create([
            'name' => 'Clinica Test',
            'email' => 'clinica@test.com',
            'subscription_status' => 'active',
            'subscribed_at' => now(),
            'stripe_id' => 'cus_test_123',
        ]);

        Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'active',
            'stripe_customer_id' => 'cus_test_123',
            'stripe_subscription_id' => 'sub_test_123',
        ]);

        $payload = json_encode([
            'id' => 'evt_test_1',
            'object' => 'event',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_test_1',
                    'customer' => 'cus_test_123',
                    'amount_due' => 2900,
                    'currency' => 'eur',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->postStripeWebhook($payload, 'whsec_test_secret');

        $response->assertOk();

        $clinic->refresh();
        $this->assertSame('past_due', $clinic->subscription_status);
        $this->assertNotSame('canceled', $clinic->subscription_status);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $clinic->saasSubscriptions()->latest('id')->value('id'),
            'status' => 'past_due',
        ]);
    }

    public function test_invoice_payment_failed_sends_optional_email_when_enabled(): void
    {
        Mail::fake();

        config()->set('services.stripe.webhook_secret', 'whsec_test_secret');
        config()->set('billing.notify_on_invoice_payment_failed', true);

        $clinic = Clinic::create([
            'name' => 'Clinica Test',
            'email' => 'clinica@test.com',
            'subscription_status' => 'active',
            'stripe_id' => 'cus_test_456',
        ]);

        User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner Test',
            'email' => 'owner@test.com',
            'password' => 'password',
            'role' => 'owner',
        ]);

        $payload = json_encode([
            'id' => 'evt_test_2',
            'object' => 'event',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_test_2',
                    'customer' => 'cus_test_456',
                    'amount_due' => 2900,
                    'currency' => 'eur',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = $this->postStripeWebhook($payload, 'whsec_test_secret');

        $response->assertOk();

        Mail::assertSent(InvoicePaymentFailedMail::class, function (InvoicePaymentFailedMail $mail) {
            return $mail->hasTo('clinica@test.com') && $mail->hasTo('owner@test.com');
        });
    }

    private function postStripeWebhook(string $payload, string $secret)
    {
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = 't=' . $timestamp . ',v1=' . $signature;

        return $this->call(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $header,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );
    }
}
