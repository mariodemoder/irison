<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\Clinic;
use App\Models\User;
use App\Notifications\ResetPasswordNotificationEs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $email, string $password = 'PasswordVieja123!'): User
    {
        $clinic = Clinic::query()->create([
            'name' => 'Clinic QA',
            'email' => 'clinic-qa@test.local',
            'subscription_status' => 'trial',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(15),
        ]);

        return User::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner QA',
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'role' => 'owner',
        ]);
    }

    public function test_it_returns_neutral_message_and_sends_email_for_existing_user(): void
    {
        Notification::fake();

        $user = $this->createUser('owner@test.local');

        $response = $this->postJson('/api/password/forgot', [
            'email' => 'owner@test.local',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Si el email existe, te hemos enviado instrucciones para recuperar tu contrasena.');

        Notification::assertSentTo($user, ResetPasswordNotificationEs::class);
    }

    public function test_reset_email_uses_spanish_subject(): void
    {
        Notification::fake();

        $user = $this->createUser('owner@test.local');

        $this->postJson('/api/password/forgot', [
            'email' => 'owner@test.local',
        ]);

        Notification::assertSentTo($user, ResetPasswordNotificationEs::class, function (ResetPasswordNotificationEs $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Restablecer contrasena';
        });
    }

    public function test_it_returns_neutral_message_for_unknown_email_without_leaking_existence(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/password/forgot', [
            'email' => 'unknown@test.local',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Si el email existe, te hemos enviado instrucciones para recuperar tu contrasena.');

        Notification::assertNothingSent();
    }

    public function test_it_resets_password_with_valid_token(): void
    {
        $user = $this->createUser('owner@test.local', 'PasswordVieja123!');

        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'PasswordNueva123!',
            'password_confirmation' => 'PasswordNueva123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Contrasena actualizada correctamente. Ya puedes iniciar sesion.');

        $this->assertTrue(Hash::check('PasswordNueva123!', $user->fresh()->password));
    }

    public function test_it_rejects_reset_with_invalid_token(): void
    {
        $user = $this->createUser('owner@test.local');

        $response = $this->postJson('/api/password/reset', [
            'token' => 'token-invalido',
            'email' => $user->email,
            'password' => 'PasswordNueva123!',
            'password_confirmation' => 'PasswordNueva123!',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }
}
