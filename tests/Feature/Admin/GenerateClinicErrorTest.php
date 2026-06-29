<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\BillingPayment;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateClinicErrorTest extends TestCase
{
    use RefreshDatabase {
        RefreshDatabase::refreshDatabase as baseRefreshDatabase;
    }

    private bool $shouldPersist = false;

    protected function setUp(): void
    {
        if (getenv('CLINIC_ERROR_PERSIST') === 'true') {
            $this->shouldPersist = true;

            $connection = getenv('CLINIC_ERROR_DB') ?: 'pgsql';
            $database = getenv('CLINIC_ERROR_DB_NAME') ?: 'dueleahi';

            putenv("DB_CONNECTION={$connection}");
            putenv("DB_DATABASE={$database}");
        }

        parent::setUp();
    }

    protected function refreshDatabase(): void
    {
        if ($this->shouldPersist) {
            $connection = getenv('CLINIC_ERROR_DB') ?: 'pgsql';

            config()->set('database.default', $connection);
            config()->set("database.connections.{$connection}.database", getenv('CLINIC_ERROR_DB_NAME') ?: getenv('DB_DATABASE') ?: 'dueleahi');
            config()->set("database.connections.{$connection}.host", getenv('CLINIC_ERROR_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1');
            config()->set("database.connections.{$connection}.port", getenv('CLINIC_ERROR_DB_PORT') ?: getenv('DB_PORT') ?: '5432');
            config()->set("database.connections.{$connection}.username", getenv('CLINIC_ERROR_DB_USERNAME') ?: getenv('DB_USERNAME') ?: 'postgres');
            config()->set("database.connections.{$connection}.password", getenv('CLINIC_ERROR_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: '');

            \DB::purge($connection);
            \DB::reconnect($connection);

            $this->artisan('migrate', ['--force' => true, '--database' => $connection]);
            return;
        }

        $this->baseRefreshDatabase();
    }

    public function test_generates_500_error_and_failed_payments_for_clinic(): void
    {
        $seed = now()->format('YmdHisv');

        $clinic = Clinic::create([
            'name' => 'ERROR TEST CLINIC - Demo Backoffice',
            'legal_name' => 'ERROR TEST CLINIC SL',
            'email' => "error-demo-{$seed}@test.example",
            'phone' => '600000000',
            'address' => 'Calle Demo 123',
            'timezone' => 'Europe/Madrid',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(15),
            'plan' => 'basic',
            'status' => 'trial',
        ]);

        $user = User::create([
            'clinic_id' => $clinic->id,
            'name' => 'Doctor Demo',
            'email' => "doctor-{$seed}@error-demo.test",
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        ActivityLog::query()->create([
            'tenant_id' => $clinic->id,
            'user_id' => $user->id,
            'event' => 'system_error_500',
            'description' => 'Error interno 500 en la aplicacion',
            'metadata' => [
                'status_code' => 500,
                'exception' => 'SimulatedException',
                'path' => '/api/test/error',
                'message' => 'Error simulado para verificacion en backoffice',
            ],
            'ip' => '127.0.0.1',
            'created_at' => now(),
        ]);

        foreach (range(1, 3) as $i) {
            BillingPayment::create([
                'clinic_id' => $clinic->id,
                'amount' => 2990,
                'currency' => 'EUR',
                'status' => 'failed',
                'provider' => 'stripe',
                'method' => 'card',
            ]);
        }

        $this->assertDatabaseHas('activity_logs', [
            'tenant_id' => $clinic->id,
            'event' => 'system_error_500',
            'user_id' => $user->id,
        ]);

        $this->assertSame(
            3,
            BillingPayment::query()->where('clinic_id', $clinic->id)->where('status', 'failed')->count()
        );
        $this->assertDatabaseHas('billing_payments', [
            'clinic_id' => $clinic->id,
            'status' => 'failed',
        ]);
    }
}
