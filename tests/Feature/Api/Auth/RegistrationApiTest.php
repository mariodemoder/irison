<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $profiles = [
            ['name' => 'Administrador', 'slug' => 'admin'],
            ['name' => 'Gestor', 'slug' => 'manager'],
            ['name' => 'Profesional', 'slug' => 'professional'],
        ];

        foreach ($profiles as $p) {
            Profile::firstOrCreate(['slug' => $p['slug']], $p);
        }
    }

    public function test_registers_with_valid_data(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'clinic_name' => 'Test Clinic',
            'email' => 'test@example.com',
            'password' => 'password',
            'nif' => '12345678Z',
            'zip' => '28001',
            'phone' => '612345678',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Cuenta creada. Revisa tu correo para activar el periodo de prueba.');

        $this->assertDatabaseHas('clinics', [
            'email' => 'test@example.com',
            'nif' => '12345678Z',
            'zip' => '28001',
            'phone' => '612345678',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_rejects_invalid_nif(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'clinic_name' => 'Test Clinic',
            'email' => 'test@example.com',
            'password' => 'password',
            'nif' => '12345678A',
            'zip' => '28001',
            'phone' => '612345678',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nif');
    }

    public function test_rejects_invalid_zip(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'clinic_name' => 'Test Clinic',
            'email' => 'test@example.com',
            'password' => 'password',
            'nif' => '12345678Z',
            'zip' => '99999',
            'phone' => '612345678',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('zip');
    }

    public function test_rejects_invalid_phone(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'clinic_name' => 'Test Clinic',
            'email' => 'test@example.com',
            'password' => 'password',
            'nif' => '12345678Z',
            'zip' => '28001',
            'phone' => '123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone');
    }

    public function test_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'clinic_name' => 'Test Clinic',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nif', 'zip', 'phone']);
    }
}
