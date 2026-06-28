<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Http\Middleware\CheckSubscriptionAccess;
use App\Http\Middleware\EnsureClinic;
use App\Http\Middleware\EnsureClinicIsActive;
use App\Models\Clinic;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeamUserLoginTest extends TestCase
{
    use RefreshDatabase;

    private Clinic $clinic;
    private User $owner;
    private Profile $professionalProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureClinic::class,
            EnsureClinicIsActive::class,
            CheckSubscriptionAccess::class,
        ]);

        $this->seedProfiles();

        $this->clinic = Clinic::create([
            'name' => 'Test Clinic',
            'subscription_status' => 'trial',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
        ]);

        $this->owner = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'owner',
            'profile_id' => Profile::where('slug', 'admin')->first()->id,
        ]);

        $this->professionalProfile = Profile::where('slug', 'professional')->first();
    }

    public function test_professional_created_from_team_can_login(): void
    {
        $password = 'Profesional123!';

        // 1. Owner crea un profesional desde el panel de equipo
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/team/users', [
            'name' => 'Profesional Test',
            'email' => 'profesional@test.com',
            'password' => $password,
            'profile_id' => $this->professionalProfile->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('email', 'profesional@test.com');

        // 2. Nos deslogueamos como owner
        $this->app->get('auth')->forgetGuards();

        // 3. Intentamos loguearnos como el profesional
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'profesional@test.com',
            'password' => $password,
        ]);

        $loginResponse->assertStatus(200);
        $loginResponse->assertJsonStructure([
            'access_token',
            'token_type',
        ]);
    }

    public function test_unverified_user_cannot_login(): void
    {
        // Crear usuario sin verificar
        $user = User::create([
            'clinic_id' => $this->clinic->id,
            'name' => 'Unverified',
            'email' => 'unverified@test.com',
            'email_verified_at' => null,
            'password' => Hash::make('password'),
            'role' => 'user',
            'profile_id' => $this->professionalProfile->id,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'unverified@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Debes activar tu cuenta desde el correo antes de iniciar sesión.');
    }

    private function seedProfiles(): void
    {
        $profiles = [
            ['name' => 'Administrador', 'slug' => 'admin'],
            ['name' => 'Gestor', 'slug' => 'manager'],
            ['name' => 'Profesional', 'slug' => 'professional'],
        ];

        foreach ($profiles as $p) {
            Profile::firstOrCreate(['slug' => $p['slug']], $p);
        }
    }
}
