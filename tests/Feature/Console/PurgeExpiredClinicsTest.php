<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PurgeExpiredClinicsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestingConnection();
    }

    public function test_dry_run_lists_candidates_without_deleting(): void
    {
        $clinic = $this->createExpiredTrialClinic();

        $this->artisan('clinics:purge-expired', ['--dry-run' => true])
            ->expectsTable(['ID', 'Nombre', 'Estado', 'Trial ends at'], [
                [(string) $clinic->id, $clinic->name, 'trial', $clinic->trial_ends_at?->toDateString() ?? '-'],
            ])
            ->expectsOutputToContain('Modo --dry-run')
            ->assertSuccessful();
    }

    public function test_purges_expired_trial_clinic(): void
    {
        $clinic = $this->createExpiredTrialClinic();
        $owner = $this->createOwner($clinic);

        $patient = Patient::query()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Test',
            'last_name' => 'Patient',
        ]);

        $this->assertNull($clinic->fresh()->functional_data_deleted_at);
        $this->assertNotNull(Patient::find($patient->id));

        $this->artisan('clinics:purge-expired')
            ->expectsOutputToContain('Purga finalizada')
            ->assertSuccessful();

        $clinic->refresh();

        $this->assertNotNull($clinic->functional_data_deleted_at);
        $this->assertNull(Patient::find($patient->id));
        $this->assertNull(User::find($owner->id));

        // Registro clínica preservado
        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
        $this->assertSame('inactive', $clinic->status);
    }

    public function test_purges_expired_canceled_clinic(): void
    {
        $clinic = $this->createExpiredCanceledClinic();
        $owner = $this->createOwner($clinic);

        Patient::query()->create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Canceled',
            'last_name' => 'Patient',
        ]);

        $this->artisan('clinics:purge-expired')
            ->expectsOutputToContain('Purga finalizada')
            ->assertSuccessful();

        $clinic->refresh();
        $this->assertNotNull($clinic->functional_data_deleted_at);
    }

    public function test_skips_active_clinic(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Active Clinic',
            'subscription_status' => 'active',
            'status' => 'active',
            'subscribed_at' => now(),
            'subscription_provider' => 'fake',
        ]);

        $this->createOwner($clinic);

        $this->artisan('clinics:purge-expired')
            ->expectsOutput('No hay clínicas elegibles para purgar.')
            ->assertSuccessful();

        $this->assertNull($clinic->fresh()->functional_data_deleted_at);
    }

    public function test_skips_clinic_with_active_trial(): void
    {
        $clinic = Clinic::query()->create([
            'name' => 'Active Trial',
            'subscription_status' => 'trial',
            'status' => 'active',
            'trial_ends_at' => now()->addDays(5),
        ]);

        $this->createOwner($clinic);

        $this->artisan('clinics:purge-expired')
            ->expectsOutput('No hay clínicas elegibles para purgar.')
            ->assertSuccessful();

        $this->assertNull($clinic->fresh()->functional_data_deleted_at);
    }

    public function test_skips_clinic_already_cleaned(): void
    {
        $clinic = $this->createExpiredTrialClinic();
        $clinic->forceFill(['functional_data_deleted_at' => now()])->save();

        $this->artisan('clinics:purge-expired')
            ->expectsOutput('No hay clínicas elegibles para purgar.')
            ->assertSuccessful();
    }

    public function test_preserves_billing_data_after_purge(): void
    {
        $clinic = $this->createExpiredTrialClinic();
        $this->createOwner($clinic);

        // Preservar datos de billing
        Subscription::query()->create([
            'clinic_id' => $clinic->id,
            'status' => 'canceled',
            'current_period_end' => now()->subDays(7),
        ]);

        $this->artisan('clinics:purge-expired')->assertSuccessful();

        $this->assertDatabaseHas('subscriptions', ['clinic_id' => $clinic->id]);
        $this->assertDatabaseHas('clinics', ['id' => $clinic->id, 'name' => $clinic->name]);
    }

    private function createExpiredTrialClinic(): Clinic
    {
        $clinic = Clinic::query()->create([
            'name' => 'Expired Trial Clinic',
            'subscription_status' => 'trial',
            'status' => 'churned',
            'trial_ends_at' => Carbon::now()->subDays(10),
            'churned_at' => Carbon::now()->subDays(3),
        ]);

        return $clinic;
    }

    private function createExpiredCanceledClinic(): Clinic
    {
        $clinic = Clinic::query()->create([
            'name' => 'Expired Canceled Clinic',
            'subscription_status' => 'canceled',
            'status' => 'active',
            'trial_ends_at' => null,
        ]);

        Subscription::query()->create([
            'clinic_id' => $clinic->id,
            'status' => 'canceled',
            'current_period_end' => Carbon::now()->subDays(10),
        ]);

        return $clinic;
    }

    private function createOwner(Clinic $clinic): User
    {
        return User::query()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Owner ' . $clinic->id,
            'email' => 'owner-' . $clinic->id . '@test.local',
            'password' => 'password123',
            'role' => 'owner',
        ]);
    }

    private function assertSafeTestingConnection(): void
    {
        $defaultConnection = (string) config('database.default');
        $sqliteDatabase = (string) config('database.connections.sqlite.database');

        if (! app()->environment('testing') || $defaultConnection !== 'sqlite' || $sqliteDatabase !== ':memory:') {
            throw new RuntimeException('Este test solo se permite en APP_ENV=testing con sqlite :memory:.');
        }
    }
}
