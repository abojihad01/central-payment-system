<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\FraudRule;
use App\Models\Blacklist;
use App\Models\Whitelist;
use App\Models\RiskProfile;
use App\Services\FraudDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FraudDetectionTest extends TestCase
{
    use RefreshDatabase;

    private FraudDetectionService $fraudService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fraudService = app(FraudDetectionService::class);
    }

    /** @test */
    public function it_detects_blacklisted_email()
    {
        Blacklist::create([
            'type' => 'email',
            'value' => 'testfraud@example.org',
            'reason' => 'Known fraudster',
            'is_active' => true,
            'added_by' => 1 // Admin user ID
        ]);

        $analysis = $this->fraudService->analyzePayment(
            'testfraud@example.org',
            '192.168.1.1',
            100.00,
            'USD',
            'US' // country code
        );

        $this->assertEquals('block', $analysis['action']);
        $this->assertGreaterThan(80, $analysis['risk_score']);
        $this->assertContains('blacklisted_email', $analysis['triggered_rules']);
    }

    /** @test */
    public function it_gives_whitelist_bonus()
    {
        Whitelist::create([
            'type' => 'email',
            'value' => 'trusted@test.com',
            'reason' => 'VIP customer',
            'is_active' => true,
            'added_by' => 1 // Admin user ID
        ]);

        $analysis = $this->fraudService->analyzePayment(
            'trusted@test.com',
            '192.168.1.1',
            100.00,
            'USD',
            'US' // country code
        );

        $this->assertEquals('allow', $analysis['action']);
        $this->assertLessThan(10, $analysis['risk_score']);
        $this->assertContains('whitelisted_email', $analysis['triggered_rules']);
    }

    /** @test */
    public function it_detects_high_velocity_transactions()
    {
        // Create generated link for testing
        $link = \App\Models\GeneratedLink::factory()->create();
        
        // Create 4 recent payments to trigger velocity check (>3 threshold)
        for ($i = 0; $i < 4; $i++) {
            \App\Models\Payment::create([
                'customer_email' => 'velocity-test@example.com',
                'amount' => 100,
                'currency' => 'USD',
                'status' => 'completed',
                'payment_gateway' => 'test',
                'generated_link_id' => $link->id,
                'created_at' => now()->subMinutes(5), // Within 10 minute window
            ]);
        }

        $analysis = $this->fraudService->analyzePayment(
            'velocity-test@example.com',
            '192.168.1.1',
            200.00,
            'USD',
            'US' // country code
        );

        // Check that velocity was detected
        $this->assertContains('velocity', $analysis['triggered_rules']);
        $this->assertGreaterThan(20, $analysis['risk_score']);
    }

    /** @test */
    public function it_processes_fraud_rules_correctly()
    {
        FraudRule::create([
            'name' => 'Large Amount',
            'description' => 'Flag transactions over $500',
            'is_active' => true,
            'priority' => 8,
            'conditions' => [
                ['field' => 'amount', 'operator' => '>', 'value' => 500]
            ],
            'action' => 'review',
            'risk_score_impact' => 5
        ]);

        $analysis = $this->fraudService->analyzePayment(
            'fraud-rule-test@example.com',
            '192.168.1.1',
            600.00,
            'USD',
            'US' // country code
        );

        $this->assertEquals('review', $analysis['action']);
        $this->assertGreaterThan(20, $analysis['risk_score']);
        $this->assertContains('Large Amount', $analysis['triggered_rules']);
    }

    /** @test */
    public function it_blocks_high_risk_transactions()
    {
        // Create high-risk conditions
        Blacklist::create([
            'type' => 'ip',
            'value' => '1.2.3.4',
            'reason' => 'Known fraud IP',
            'is_active' => true,
            'added_by' => 1 // Admin user ID
        ]);

        FraudRule::create([
            'name' => 'Suspicious Country',
            'description' => 'Block transactions from high-risk countries',
            'is_active' => true,
            'priority' => 9,
            'conditions' => [
                ['field' => 'country', 'operator' => 'in', 'value' => ['XX']]
            ],
            'action' => 'block',
            'risk_score_impact' => 50
        ]);

        $analysis = $this->fraudService->analyzePayment(
            'test@example.com',
            '1.2.3.4',
            100.00,
            'USD',
            'XX', // country code
            null  // device fingerprint
        );

        $this->assertEquals('block', $analysis['action']);
        $this->assertGreaterThan(80, $analysis['risk_score']);
    }

    /** @test */
    public function it_calculates_fraud_rule_accuracy()
    {
        $rule = FraudRule::create([
            'name' => 'Test Rule',
            'description' => 'Test accuracy calculation',
            'is_active' => true,
            'priority' => 5,
            'conditions' => [],
            'action' => 'review',
            'risk_score_impact' => 10,
            'times_triggered' => 100,
            'false_positives' => 15
        ]);

        $accuracy = $rule->calculateAccuracyRate();
        $this->assertEquals(85.0, $accuracy); // (100-15)/100 * 100 = 85%
    }
}
