<?php

namespace Tests\Unit;

use App\Models\Clinic;
use Carbon\Carbon;
use Tests\TestCase;

class ClinicBackofficeStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_active_subscription_with_stale_trial_status_returns_green(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        $clinic = new Clinic([
            'subscription_status' => 'active',
            'status' => 'trial_read_only',
            'plan' => 'basic',
        ]);

        $this->assertSame('green', $clinic->backofficeStatusColor());
    }

    public function test_active_subscription_returns_green(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        $clinic = new Clinic([
            'subscription_status' => 'active',
            'status' => 'active',
            'plan' => 'basic',
        ]);

        $this->assertSame('green', $clinic->backofficeStatusColor());
    }

    public function test_genuine_trial_read_only_returns_red(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        $clinic = new Clinic([
            'subscription_status' => 'trial',
            'status' => 'trial_read_only',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->assertSame('red', $clinic->backofficeStatusColor());
    }

    public function test_expired_trial_returns_blue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 10:00:00'));

        $clinic = new Clinic([
            'subscription_status' => 'trial',
            'status' => 'trial',
            'trial_ends_at' => now()->subDays(8),
        ]);

        $this->assertSame('blue', $clinic->backofficeStatusColor());
    }
}
