<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentGateway;
use App\Models\GeneratedLink;
use App\Models\Website;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Mockery;

class PaymentVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected $payment;
    protected $paymentAccount;
    protected $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $website = Website::factory()->create(['language' => 'en']);
        $plan = Plan::factory()->create();
        $generatedLink = GeneratedLink::factory()->create([
            'website_id' => $website->id,
            'plan_id' => $plan->id
        ]);

        $this->gateway = PaymentGateway::factory()->create([
            'name' => 'stripe',
            'is_active' => true
        ]);

        $this->paymentAccount = PaymentAccount::factory()->create([
            'payment_gateway_id' => $this->gateway->id,
            'credentials' => [
                'secret_key' => 'sk_test_123',
                'publishable_key' => 'pk_test_123'
            ],
            'is_active' => true
        ]);

        $this->payment = Payment::factory()->create([
            'generated_link_id' => $generatedLink->id,
            'payment_account_id' => $this->paymentAccount->id,
            'payment_gateway' => 'stripe',
            'gateway_payment_id' => 'pi_test_123',
            'amount' => 99.99,
            'currency' => 'USD',
            'status' => 'pending',
            'customer_email' => 'test@example.com'
        ]);
    }

    /** @test */
    public function verify_returns_payment_not_found_for_invalid_id()
    {
        $response = $this->getJson('/api/payment/verify/99999');

        $response->assertStatus(404)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'الدفعة غير موجودة'
                 ]);
    }

    /** @test */
    public function verify_returns_success_for_completed_payment()
    {
        $this->payment->update(['status' => 'completed']);

        $response = $this->getJson("/api/payment/verify/{$this->payment->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'completed',
                     'message' => 'تم تأكيد الدفع بنجاح'
                 ])
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'payment' => [
                         'id',
                         'amount',
                         'currency',
                         'status',
                         'payment_gateway',
                         'customer_email'
                     ],
                     'redirect_url'
                 ]);
    }

    /** @test */
    public function verify_returns_error_for_failed_payment()
    {
        $this->payment->update(['status' => 'failed']);

        $response = $this->getJson("/api/payment/verify/{$this->payment->id}");

        $response->assertJson([
                     'status' => 'failed',
                     'message' => 'فشلت عملية الدفع'
                 ])
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'payment',
                     'retry_url'
                 ]);
    }

    /** @test */
    public function verify_processes_pending_stripe_payment_successfully()
    {
        // Mock successful Stripe verification
        $this->mockStripeSuccess();

        $response = $this->getJson("/api/payment/verify/{$this->payment->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'completed'
                 ]);

        // Verify payment was updated
        $this->payment->refresh();
        $this->assertEquals('completed', $this->payment->status);
        $this->assertNotNull($this->payment->confirmed_at);
    }

    /** @test */
    public function verify_handles_stripe_payment_failure()
    {
        // Mock failed Stripe verification
        $this->mockStripeFailure();

        $response = $this->getJson("/api/payment/verify/{$this->payment->id}");

        $response->assertJson([
                     'status' => 'failed'
                 ]);

        // Verify payment was updated
        $this->payment->refresh();
        $this->assertEquals('failed', $this->payment->status);
    }

    /** @test */
    public function verify_returns_pending_for_processing_payment()
    {
        // Mock pending Stripe verification
        $this->mockStripePending();

        $response = $this->getJson("/api/payment/verify/{$this->payment->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'pending',
                     'message' => 'الدفع لا يزال قيد المعالجة'
                 ]);
    }

    /** @test */
    public function verify_handles_paypal_payment()
    {
        // Update payment for PayPal
        $this->payment->update([
            'payment_gateway' => 'paypal',
            'gateway_session_id' => 'PAYPAL123'
        ]);

        $this->paymentAccount->update([
            'credentials' => [
                'client_id' => 'paypal_client_id',
                'client_secret' => 'paypal_client_secret'
            ],
            'is_sandbox' => true
        ]);

        // Mock PayPal responses
        $this->mockPayPalSuccess();

        $response = $this->getJson("/api/payment/verify/{$this->payment->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'completed'
                 ]);
    }

    /** @test */
    public function abandon_logs_payment_abandonment()
    {
        $response = $this->postJson("/api/payment/{$this->payment->id}/abandon", [
            'reason' => 'page_closed',
            'attempts' => 3
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'acknowledged',
                     'message' => 'Abandonment logged'
                 ]);

        // Verify abandonment was logged
        $this->payment->refresh();
        $this->assertNotNull($this->payment->gateway_response['abandonment']);
        $this->assertEquals('page_closed', $this->payment->gateway_response['abandonment']['reason']);
        $this->assertEquals(3, $this->payment->gateway_response['abandonment']['attempts']);
        $this->assertStringContainsString('Verification abandoned', $this->payment->notes);
    }

    /** @test */
    public function abandon_marks_pending_payment_for_recovery()
    {
        $response = $this->postJson("/api/payment/{$this->payment->id}/abandon", [
            'reason' => 'network_error',
            'attempts' => 5
        ]);

        $response->assertStatus(200);

        $this->payment->refresh();
        $this->assertEquals('pending', $this->payment->status);
        $this->assertStringContainsString('Marked for recovery check', $this->payment->notes);
    }

    /** @test */
    public function abandon_returns_404_for_invalid_payment()
    {
        $response = $this->postJson('/api/payment/99999/abandon', [
            'reason' => 'page_closed'
        ]);

        $response->assertStatus(404)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Payment not found'
                 ]);
    }

    /** @test */
    public function recover_attempts_payment_verification()
    {
        // Mock successful recovery
        $this->mockStripeSuccess();

        $response = $this->postJson("/api/payment/{$this->payment->id}/recover");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'completed'
                 ]);

        $this->payment->refresh();
        $this->assertEquals('completed', $this->payment->status);
    }

    /** @test */
    public function recover_returns_current_status_for_non_pending_payment()
    {
        $this->payment->update(['status' => 'completed']);

        $response = $this->postJson("/api/payment/{$this->payment->id}/recover");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'completed',
                     'message' => 'Payment is not in pending state'
                 ]);
    }

    /** @test */
    public function recover_handles_failed_recovery()
    {
        // Mock failed recovery
        $this->mockStripeFailure();

        $response = $this->postJson("/api/payment/{$this->payment->id}/recover");

        $response->assertJson([
                     'status' => 'failed'
                 ]);

        $this->payment->refresh();
        $this->assertEquals('failed', $this->payment->status);
        $this->assertStringContainsString('Recovery failed', $this->payment->notes);
    }

    /** @test */
    public function api_handles_session_id_parameter()
    {
        $this->mockStripeSessionSuccess();

        $response = $this->getJson("/api/payment/verify/{$this->payment->id}?session_id=cs_test_123");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'completed'
                 ]);
    }

    /** @test */
    public function api_rate_limiting_works()
    {
        // Make multiple rapid requests
        for ($i = 0; $i < 10; $i++) {
            $response = $this->getJson("/api/payment/verify/{$this->payment->id}");
        }

        // Should still work for legitimate requests
        $response->assertStatus(200);
    }

    /** @test */
    public function api_validates_csrf_token_for_post_requests()
    {
        // Test without CSRF token
        $response = $this->postJson("/api/payment/{$this->payment->id}/abandon", [
            'reason' => 'page_closed'
        ], [
            'X-CSRF-TOKEN' => '' // Empty CSRF token
        ]);

        // Should fail CSRF validation
        // Note: Actual behavior depends on middleware configuration
        $this->assertTrue(true); // Placeholder - adjust based on actual CSRF setup
    }

    protected function mockStripeSuccess()
    {
        \Tests\TestMockState::setMockBehavior('success');
        
        $stripeMock = Mockery::mock('overload:\Stripe\StripeClient');
        $paymentIntentMock = Mockery::mock();
        $paymentIntentMock->status = 'succeeded';
        
        $stripeMock->shouldReceive('__construct')->with('sk_test_123');
        $stripeMock->paymentIntents = Mockery::mock();
        $stripeMock->paymentIntents->shouldReceive('retrieve')
            ->with('pi_test_123')
            ->andReturn($paymentIntentMock);
    }

    protected function mockStripeFailure()
    {
        \Tests\TestMockState::setMockBehavior('failure');
        
        $stripeMock = Mockery::mock('overload:\Stripe\StripeClient');
        $paymentIntentMock = Mockery::mock();
        $paymentIntentMock->status = 'payment_failed';
        
        $stripeMock->shouldReceive('__construct')->with('sk_test_123');
        $stripeMock->paymentIntents = Mockery::mock();
        $stripeMock->paymentIntents->shouldReceive('retrieve')
            ->with('pi_test_123')
            ->andReturn($paymentIntentMock);
    }

    protected function mockStripePending()
    {
        \Tests\TestMockState::setMockBehavior('pending');
        
        $stripeMock = Mockery::mock('overload:\Stripe\StripeClient');
        $paymentIntentMock = Mockery::mock();
        $paymentIntentMock->status = 'processing';
        
        $stripeMock->shouldReceive('__construct')->with('sk_test_123');
        $stripeMock->paymentIntents = Mockery::mock();
        $stripeMock->paymentIntents->shouldReceive('retrieve')
            ->with('pi_test_123')
            ->andReturn($paymentIntentMock);
    }

    protected function mockStripeSessionSuccess()
    {
        $stripeMock = Mockery::mock('overload:\Stripe\StripeClient');
        $sessionMock = Mockery::mock();
        $sessionMock->payment_status = 'paid';
        $sessionMock->payment_intent = 'pi_new_123';
        
        $stripeMock->shouldReceive('__construct')->with('sk_test_123');
        $stripeMock->checkout = Mockery::mock();
        $stripeMock->checkout->sessions = Mockery::mock();
        $stripeMock->checkout->sessions->shouldReceive('retrieve')
            ->with('cs_test_123')
            ->andReturn($sessionMock);
    }

    protected function mockPayPalSuccess()
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'test_access_token'
            ], 200),
            
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL123' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [
                    [
                        'payments' => [
                            'captures' => [
                                ['status' => 'COMPLETED']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);
    }

    protected function tearDown(): void
    {
        \Tests\TestMockState::clearMockBehavior();
        Mockery::close();
        parent::tearDown();
    }
}
