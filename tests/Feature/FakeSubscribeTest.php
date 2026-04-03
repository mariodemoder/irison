<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\BillingPayment;
use App\Models\User;
use App\Models\Clinic;
use App\Models\MySaasCounter;
use App\Models\Subscription;
use Carbon\Carbon;

class FakeSubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_subscribe_activates_trial_subscription_and_sets_dates()
    {
        Carbon::setTestNow($now = Carbon::parse('2026-02-03 12:00:00'));

        $clinic = Clinic::create(['name' => 'Test Clinic']);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => bcrypt('password'),
            'clinic_id' => $clinic->id,
        ]);

        $subscription = Subscription::create([
            'clinic_id' => $clinic->id,
            'status' => 'trial',
            'trial_ends_at' => $now->copy()->addDays(7),
            'current_period_end' => null,
            'stripe_customer_id' => null,
            'stripe_subscription_id' => null,
        ]);

        MySaasCounter::create([
            'table_type' => 'payout',
            'prefix' => 'SAS',
            'last_number' => 41,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscribe/fake');

        $response->assertStatus(200)->assertJson(['status' => 'ok']);

        $subscription->refresh();
        $clinic->refresh();
        $payment = BillingPayment::query()->where('clinic_id', $clinic->id)->latest('id')->first();

        $this->assertEquals('active', $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
        $this->assertNotNull($subscription->current_period_end);
        $this->assertEquals('fake', $clinic->subscription_provider);
        $this->assertEquals($subscription->stripe_subscription_id, $clinic->subscription_reference);
        $this->assertNotNull($clinic->subscribed_at);
        $this->assertNotNull($payment);
        $this->assertSame('paid', $payment->status);
        $this->assertSame('fake', $payment->provider);
        $this->assertSame('SAS-000042', $payment->counter);
    }
}
