<?php

namespace Tests\Feature\Api;

use App\Mail\AccountActivationMail;
use App\Models\Clinic;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterActivationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_sends_activation_mail_and_trial_starts_on_activation_link(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Mario Test',
            'clinic_name' => 'Clinica Test',
            'email' => 'mario.activation@test.local',
            'password' => 'Password123',
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'message' => 'Cuenta creada. Revisa tu correo para activar el periodo de prueba.',
            ]);

        $user = User::query()->where('email', 'mario.activation@test.local')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        $clinic = Clinic::query()->find($user->clinic_id);
        $this->assertNotNull($clinic);
        $this->assertNull($clinic->trial_ends_at);

        $this->assertDatabaseCount('subscriptions', 0);

        $activationUrl = null;

        Mail::assertSent(AccountActivationMail::class, function (AccountActivationMail $mail) use (&$activationUrl, $user) {
            $activationUrl = $mail->activationUrl;
            return $mail->hasTo($user->email) && is_string($activationUrl) && str_contains($activationUrl, '/api/register/activate/');
        });

        $this->assertNotNull($activationUrl);

        $this->get($activationUrl)
            ->assertRedirect('http://localhost/login?activation=success&email=mario.activation%40test.local');

        $user->refresh();
        $clinic->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($clinic->trial_ends_at);
        $this->assertTrue($clinic->trial_ends_at->isFuture());

        $subscription = Subscription::query()->where('clinic_id', $clinic->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame('trial', $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->trial_ends_at->isFuture());
    }
}
