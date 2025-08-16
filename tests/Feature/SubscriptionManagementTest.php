<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class SubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure no active transactions before starting tests
        if (\DB::transactionLevel() > 0) {
            \DB::rollBack();
        }
        
        $this->subscriptionService = app(SubscriptionService::class);
    }
    
    protected function tearDown(): void
    {
        // Clean up any pending transactions
        while (\DB::transactionLevel() > 0) {
            \DB::rollBack();
        }
        
        parent::tearDown();
    }

    /** @test */
    public function it_can_create_a_subscription_with_trial_period()
    {
        $plan = Plan::factory()->create([
            'subscription_type' => 'recurring',
            'billing_interval' => 'monthly',
            'trial_period_days' => 14,
            'setup_fee' => 0
        ]);

        $subscription = $this->subscriptionService->createSubscription([
            'plan_id' => $plan->id,
            'customer_email' => 'test@example.com',
            'payment_method' => 'stripe'
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'subscription_id' => $subscription->subscription_id,
            'status' => 'trial'
        ]);
        
        // Check trial_ends_at separately with proper date comparison
        $this->assertTrue($subscription->trial_ends_at->format('Y-m-d') === now()->addDays(14)->format('Y-m-d'));

        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'trial_started'
        ]);
    }

    /** @test */
    public function it_can_cancel_subscription()
    {
        $subscription = Subscription::factory()->create([
            'status' => 'active'
        ]);

        $result = $subscription->cancel('Customer request');

        $this->assertTrue($result);
        $this->assertEquals('cancelled', $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->cancelled_at);
        
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'cancelled'
        ]);
    }

    /** @test */
    public function it_can_pause_and_resume_subscription()
    {
        $subscription = Subscription::factory()->create([
            'status' => 'active'
        ]);

        // Test pause
        $result = $subscription->pause('Customer request');
        $this->assertTrue($result);
        $this->assertEquals('paused', $subscription->fresh()->status);

        // Test resume
        $result = $subscription->resume();
        $this->assertTrue($result);
        $this->assertEquals('active', $subscription->fresh()->status);
    }

    /** @test */
    public function it_can_upgrade_subscription_plan()
    {
        $oldPlan = Plan::factory()->create(['price' => 10]);
        $newPlan = Plan::factory()->create(['price' => 20]);
        
        $subscription = Subscription::factory()->create([
            'plan_id' => $oldPlan->id,
            'status' => 'active'
        ]);

        $result = $subscription->upgradePlan($newPlan->id);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals($newPlan->id, $subscription->fresh()->plan_id);
        
        $this->assertDatabaseHas('subscription_events', [
            'subscription_id' => $subscription->id,
            'event_type' => 'plan_upgraded'
        ]);
    }

    /** @test */
    public function it_calculates_mrr_correctly()
    {
        Plan::factory()->create([
            'id' => 1, 
            'price' => 10, 
            'billing_interval' => 'monthly',
            'subscription_type' => 'recurring',
            'billing_interval_count' => 1
        ]);
        Plan::factory()->create([
            'id' => 2, 
            'price' => 100, 
            'billing_interval' => 'yearly',
            'subscription_type' => 'recurring',
            'billing_interval_count' => 1
        ]);
        
        Subscription::factory()->count(5)->create(['plan_id' => 1, 'status' => 'active']);
        Subscription::factory()->count(2)->create(['plan_id' => 2, 'status' => 'active']);

        $mrr = $this->subscriptionService->calculateMRR();
        
        // 5 monthly at $10 + 2 yearly at $100/12 = $50 + $16.67 = $66.67
        $expectedMRR = (5 * 10) + (2 * 100 / 12);
        $this->assertEquals(round($expectedMRR, 2), $mrr);
    }

    /** @test */
    public function it_handles_trial_expiration()
    {
        $subscription = Subscription::factory()->create([
            'status' => 'trial',
            'is_trial' => true,
            'trial_ends_at' => now()->subDay()
        ]);

        $this->assertTrue($subscription->isTrialExpired());
        
        // Simulate trial expiration processing
        $subscription->update(['status' => 'active']);
        
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'active'
        ]);
    }

    /** @test */
    public function it_adds_billing_cycles_correctly()
    {
        $subscription = Subscription::factory()->create([
            'billing_cycle_count' => 1,
            'next_billing_date' => now()->addMonth()
        ]);

        $subscription->addBillingCycle();

        $this->assertEquals(2, $subscription->fresh()->billing_cycle_count);
        $this->assertEquals(
            now()->addMonths(2)->format('Y-m-d'),
            $subscription->fresh()->next_billing_date->format('Y-m-d')
        );
    }
}