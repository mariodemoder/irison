<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClinicLogoTest extends TestCase
{
    use RefreshDatabase;

    private function makeClinic(string $plan): Clinic
    {
        return Clinic::create([
            'name' => 'Clinica Logo Test',
            'email' => 'logo@irison.test',
            'timezone' => 'Europe/Madrid',
            'plan' => $plan,
            'subscription_status' => 'active',
            'trial_ends_at' => now()->addDays(30),
        ]);
    }

    private function makeOwner(Clinic $clinic): User
    {
        return User::create([
            'name' => 'Owner Logo',
            'email' => 'owner.logo@irison.test',
            'password' => 'password',
            'clinic_id' => $clinic->id,
            'role' => 'owner',
        ]);
    }

    public function test_pro_clinic_can_upload_and_remove_logo(): void
    {
        Storage::fake('public');

        $clinic = $this->makeClinic('pro');
        $owner = $this->makeOwner($clinic);
        Sanctum::actingAs($owner);

        $upload = $this->postJson('/api/me/logo', [
            'image' => UploadedFile::fake()->image('logo.png', 300, 100),
        ]);

        $upload->assertOk();
        $clinic->refresh();

        $this->assertNotNull($clinic->logo_path);
        $this->assertTrue(Storage::disk('public')->exists($clinic->logo_path));
        $this->assertNotNull($upload->json('clinic_logo_url'));

        $me = $this->getJson('/api/me')->assertOk();
        $this->assertSame($upload->json('clinic_logo_url'), $me->json('clinic_logo_url'));

        $storedPath = $clinic->logo_path;

        $remove = $this->deleteJson('/api/me/logo')->assertOk();
        $clinic->refresh();

        $this->assertNull($clinic->logo_path);
        $this->assertNull($remove->json('clinic_logo_url'));
        $this->assertFalse(Storage::disk('public')->exists($storedPath));
    }

    public function test_basic_clinic_cannot_upload_logo(): void
    {
        Storage::fake('public');

        $clinic = $this->makeClinic('basic');
        $owner = $this->makeOwner($clinic);
        Sanctum::actingAs($owner);

        $this->postJson('/api/me/logo', [
            'image' => UploadedFile::fake()->image('logo.png', 300, 100),
        ])->assertStatus(403);
    }

    public function test_enterprise_clinic_can_upload_logo(): void
    {
        Storage::fake('public');

        $clinic = $this->makeClinic('enterprise');
        $owner = $this->makeOwner($clinic);
        Sanctum::actingAs($owner);

        $this->postJson('/api/me/logo', [
            'image' => UploadedFile::fake()->image('logo.png', 300, 100),
        ])->assertOk();
    }

    public function test_logo_upload_rejects_oversize_file(): void
    {
        Storage::fake('public');

        $clinic = $this->makeClinic('pro');
        $owner = $this->makeOwner($clinic);
        Sanctum::actingAs($owner);

        $this->postJson('/api/me/logo', [
            'image' => UploadedFile::fake()->image('logo.png', 300, 100)->size(2100),
        ])->assertStatus(422);
    }

    public function test_logo_upload_rejects_non_image(): void
    {
        Storage::fake('public');

        $clinic = $this->makeClinic('pro');
        $owner = $this->makeOwner($clinic);
        Sanctum::actingAs($owner);

        $this->postJson('/api/me/logo', [
            'image' => UploadedFile::fake()->create('logo.txt', 100),
        ])->assertStatus(422);
    }
}
