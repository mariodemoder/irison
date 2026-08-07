<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Booking\Models\BookingPage;
use Tests\TestCase;

class BookingReadOnlyPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_read_only_clinic_can_view_booking_settings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createReadOnlyClinicAndOwner();

        Sanctum::actingAs($user);

        $this->getJson('/api/booking/settings')->assertOk();
    }

    public function test_read_only_clinic_cannot_update_booking_settings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createReadOnlyClinicAndOwner();

        Sanctum::actingAs($user);

        $this->putJson('/api/booking/settings', ['slug' => 'nuevo-slug'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'CLINIC_READ_ONLY_NO_TRANSACTIONS');
    }

    public function test_read_only_clinic_cannot_create_booking_service(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createReadOnlyClinicAndOwner();

        Sanctum::actingAs($user);

        $this->postJson('/api/booking/services', ['name' => 'Masaje'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'CLINIC_READ_ONLY_NO_TRANSACTIONS');
    }

    public function test_read_only_clinic_cannot_write_patients(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createReadOnlyClinicAndOwner();

        Sanctum::actingAs($user);

        $this->postJson('/api/patients', ['first_name' => 'Ana'])
            ->assertStatus(403)
            ->assertJsonPath('code', 'CLINIC_READ_ONLY_NO_TRANSACTIONS');
    }

    public function test_read_only_clinic_can_download_subscription_backup(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createReadOnlyClinicAndOwner();

        Sanctum::actingAs($user);

        $this->getJson('/api/settings/subscription/backup')->assertOk();
    }

    public function test_read_only_clinic_can_start_paid_plan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createReadOnlyClinicAndOwner();

        Sanctum::actingAs($user);

        $this->postJson('/api/subscribe/fake')->assertOk();
    }

    public function test_public_booking_page_is_still_viewable_in_read_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createReadOnlyClinicAndOwner();

        BookingPage::create([
            'clinic_id' => $clinic->id,
            'slug' => 'mi-clinica-read-only',
            'title' => 'Mi Clinica',
            'is_active' => true,
            'max_horizon_days' => 30,
            'cancellation_hours' => 24,
        ]);

        $this->getJson('/api/booking/mi-clinica-read-only')->assertOk();
    }

    public function test_public_booking_is_blocked_while_clinic_is_read_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createReadOnlyClinicAndOwner();

        BookingPage::create([
            'clinic_id' => $clinic->id,
            'slug' => 'mi-clinica-read-only',
            'title' => 'Mi Clinica',
            'is_active' => true,
            'max_horizon_days' => 30,
            'cancellation_hours' => 24,
        ]);

        $this->postJson('/api/booking', [
            'slug' => 'mi-clinica-read-only',
            'service_id' => 1,
            'professional_id' => 1,
            'date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '10:00',
            'patient' => [
                'first_name' => 'Ana',
                'last_name' => 'Perez',
                'email' => 'ana@test.local',
            ],
        ])->assertStatus(422);
    }

    public function test_public_cancellation_is_blocked_while_clinic_is_read_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createReadOnlyClinicAndOwner();

        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Ana',
            'last_name' => 'Perez',
            'email' => 'ana@test.local',
        ]);

        Appointment::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => 'scheduled',
            'payment_status' => 'pending',
            'booking_source' => 'online',
            'confirmation_token' => 'tok-read-only-001',
        ]);

        $this->postJson('/api/booking/cancel/tok-read-only-001')->assertStatus(422);
    }

    public function test_public_booking_is_allowed_while_clinic_is_active(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        [$clinic, $user] = $this->createActiveClinicAndOwner();

        BookingPage::create([
            'clinic_id' => $clinic->id,
            'slug' => 'mi-clinica-activa',
            'title' => 'Mi Clinica',
            'is_active' => true,
            'max_horizon_days' => 30,
            'cancellation_hours' => 24,
        ]);

        $this->getJson('/api/booking/mi-clinica-activa')->assertOk();
    }

    private function createReadOnlyClinicAndOwner(): array
    {
        return $this->createClinicAndOwner([
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);
    }

    private function createActiveClinicAndOwner(): array
    {
        return $this->createClinicAndOwner([
            'subscription_status' => 'active',
            'trial_ends_at' => null,
            'subscribed_at' => now(),
            'plan' => 'basic',
            'max_users' => 1,
        ]);
    }

    private function createClinicAndOwner(array $overrides = []): array
    {
        $clinic = new Clinic();
        $clinic->forceFill(array_merge([
            'name' => 'Clinica QA Booking',
            'email' => 'clinic-booking@test.local',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(2),
            'subscribed_at' => null,
            'subscription_provider' => 'fake',
        ], $overrides));
        $clinic->save();

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner QA',
            'email' => 'owner-booking-' . $clinic->id . '@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        return [$clinic->refresh(), $user];
    }
}
