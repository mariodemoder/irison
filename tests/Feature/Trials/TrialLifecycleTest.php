<?php

declare(strict_types=1);

namespace Tests\Feature\Trials;

use App\Models\Clinic;
use App\Models\TrialJourneyEvent;
use App\Services\Trials\TrialLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TrialLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function ensureSafeTestEnvironment(): void
    {
        if (app()->environment('testing') !== true) {
            throw new \RuntimeException('Backoffice tests must run only in APP_ENV=testing.');
        }

        if (config('database.default') !== 'sqlite') {
            throw new \RuntimeException('Backoffice tests must run with sqlite as default connection.');
        }

        if (config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Backoffice tests must run with sqlite :memory: database.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSafeTestEnvironment();
    }

    public function test_processes_day_1_and_is_idempotent(): void
    {
        Mail::fake();

        $clinic = $this->makeTrialClinic(daysSinceCreate: 1, trialEndsInDays: 29);

        $service = app(TrialLifecycleService::class);
        $statsFirst = $service->process(now());
        $statsSecond = $service->process(now());

        $this->assertSame(1, $statsFirst['emails_sent']);
        $this->assertSame(0, $statsSecond['emails_sent']);
        $this->assertDatabaseCount('trial_journey_events', 1);
        $this->assertTrue(
            TrialJourneyEvent::query()
                ->where('clinic_id', $clinic->id)
                ->where('event_key', 'trial_day_1')
                ->exists()
        );
    }

    public function test_moves_to_trial_warning_in_day_20(): void
    {
        Mail::fake();

        $clinic = $this->makeTrialClinic(daysSinceCreate: 20, trialEndsInDays: 10);
        $clinic->subscription_status = 'trial';
        $clinic->save();

        app(TrialLifecycleService::class)->process(now());

        $clinic->refresh();
        $this->assertSame('trial_warning', $clinic->subscription_status);
        $this->assertSame('trial_warning', $clinic->status);
        $this->assertDatabaseHas('trial_journey_events', [
            'clinic_id' => $clinic->id,
            'event_key' => 'trial_day_20',
        ]);
    }

    public function test_activates_read_only_on_day_30(): void
    {
        Mail::fake();

        $clinic = $this->makeTrialClinic(daysSinceCreate: 30, trialEndsInDays: 0);
        $clinic->subscription_status = 'trial_warning';
        $clinic->status = 'trial_warning';
        $clinic->save();

        app(TrialLifecycleService::class)->process(now());

        $clinic->refresh();
        $this->assertSame('trial_read_only', $clinic->status);
    }

    public function test_marks_churn_after_grace_window(): void
    {
        Mail::fake();

        $clinic = $this->makeTrialClinic(daysSinceCreate: 40, trialEndsInDays: -8);
        $clinic->subscription_status = 'trial_warning';
        $clinic->status = 'trial_read_only';
        $clinic->save();

        app(TrialLifecycleService::class)->process(now());

        $clinic->refresh();
        $this->assertSame('churned', $clinic->status);
        $this->assertSame('inactive', $clinic->subscription_status);
        $this->assertNotNull($clinic->churned_at);
    }

    public function test_skips_milestones_for_active_clinic(): void
    {
        Mail::fake();

        $clinic = $this->makeTrialClinic(daysSinceCreate: 7, trialEndsInDays: 23);
        $clinic->subscription_status = 'active';
        $clinic->status = 'active';
        $clinic->save();

        $stats = app(TrialLifecycleService::class)->process(now());

        $this->assertSame(0, $stats['emails_sent']);
        $this->assertDatabaseMissing('trial_journey_events', [
            'clinic_id' => $clinic->id,
            'event_key' => 'trial_day_7',
        ]);
    }

    private function makeTrialClinic(int $daysSinceCreate, int $trialEndsInDays): Clinic
    {
        $createdAt = Carbon::now()->subDays($daysSinceCreate);

        $clinic = Clinic::query()->create([
            'name' => 'Clinic '.$daysSinceCreate,
            'legal_name' => 'Clinic '.$daysSinceCreate,
            'email' => 'clinic'.$daysSinceCreate.'@example.test',
            'trial_ends_at' => Carbon::now()->addDays($trialEndsInDays),
            'subscription_status' => 'trial',
            'status' => 'trial',
        ]);

        $clinic->created_at = $createdAt;
        $clinic->updated_at = $createdAt;
        $clinic->save();

        return $clinic;
    }
}
