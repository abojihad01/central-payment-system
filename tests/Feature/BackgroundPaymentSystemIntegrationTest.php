<?php

namespace Tests\Feature;

use App\Jobs\ProcessPendingPayment;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentGateway;
use App\Models\GeneratedLink;
use App\Models\Website;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Mockery;

class BackgroundPaymentSystemIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure no active transactions before starting tests
        if (\DB::transactionLevel() > 0) {
            \DB::rollBack();
        }
        
        // Set up test environment
        config(['queue.default' => 'sync']); // Use sync for testing
        
        // Fake notifications to prevent database notification errors
        Notification::fake();
        
        // Create comprehensive test data
        $this->createTestEnvironment();
    }

    /** @test */
    public function complete_payment_workflow_stripe_success()
    {
        // Arrange
        $payment = $this->createStripePayment();
        $this->mockStripeSuccessfulResponse();
        
        // Act - Simulate payment creation and background processing
        ProcessPendingPayment::dispatch($payment);
        
        // Assert - Verify complete workflow
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->confirmed_at);
        
        // Verify subscription was created
        $subscription = Subscription::where('payment_id', $payment->id)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals($payment->customer_email, $subscription->customer_email);
        
        // Verify customer was created
        $customer = \App\Models\Customer::where('email', $payment->customer_email)->first();
        $this->assertNotNull($customer, 'Customer should be created for Stripe payment');
        $this->assertEquals($payment->customer_email, $customer->email);
        $this->assertEquals('active', $customer->status);
        $this->assertGreaterThan(0, $customer->successful_payments);
        
        // Verify customer events were created
        $customerEvents = \App\Models\CustomerEvent::where('customer_id', $customer->id)->get();
        $this->assertGreaterThan(0, $customerEvents->count(), 'Customer events should be created');
        
        // Verify payment completion event exists
        $paymentEvent = $customerEvents->where('event_type', 'payment_completed')->first();
        $this->assertNotNull($paymentEvent, 'Payment completed event should exist');
        
        // Verify subscription creation event exists
        $subscriptionEvent = $customerEvents->where('event_type', 'subscription_created')->first();
        $this->assertNotNull($subscriptionEvent, 'Subscription created event should exist');
    }

    /** @test */
    public function complete_payment_workflow_paypal_success()
    {
        // Arrange
        $payment = $this->createPayPalPayment();
        $this->mockPayPalSuccessfulResponse();
        
        // Act
        ProcessPendingPayment::dispatch($payment);
        
        // Assert
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        
        // Verify subscription
        $subscription = Subscription::where('payment_id', $payment->id)->first();
        $this->assertNotNull($subscription);
    }

    /** @test */
    public function payment_abandonment_and_recovery_flow()
    {
        // Arrange
        $payment = $this->createStripePayment();
        
        // Act 1 - Abandon payment
        $response = $this->postJson("/api/payment/{$payment->id}/abandon", [
            'reason' => 'page_closed',
            'attempts' => 3
        ]);
        
        $response->assertStatus(200);
        
        // Verify abandonment logged
        $payment->refresh();
        $this->assertNotNull($payment->gateway_response['abandonment']);
        
        // Act 2 - Attempt recovery
        $this->mockStripeSuccessfulResponse();
        $response = $this->postJson("/api/payment/{$payment->id}/recover");
        
        // Assert recovery successful
        $response->assertStatus(200)->assertJson(['status' => 'completed']);
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
    }

    /** @test */
    public function scheduled_command_processes_payments_correctly()
    {
        // Arrange - Create payments of different ages
        $recentPayment = $this->createStripePayment(['created_at' => now()->subMinutes(5)]);
        $oldPayment = $this->createStripePayment(['created_at' => now()->subMinutes(30)]);
        $veryOldPayment = $this->createStripePayment(['created_at' => now()->subHours(25)]);
        
        Queue::fake();
        $this->mockStripeSuccessfulResponse();
        
        // Act - Run command
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 60,
            '--limit' => 10
        ]);
        
        // Assert
        $this->assertEquals(0, $exitCode);
        
        // Should process recent and old payment, but not very old
        Queue::assertPushed(ProcessPendingPayment::class, function ($job) use ($recentPayment, $oldPayment) {
            return in_array($job->payment->id, [$recentPayment->id, $oldPayment->id]);
        });
        
        Queue::assertNotPushed(ProcessPendingPayment::class, function ($job) use ($veryOldPayment) {
            return $job->payment->id === $veryOldPayment->id;
        });
    }

    /** @test */
    public function retry_mechanism_works_with_exponential_backoff()
    {
        // Arrange
        $payment = $this->createStripePayment();
        
        Queue::fake();
        
        // Set mock behavior to trigger retry
        \Tests\TestMockState::setMockBehavior('pending');
        
        // Act - Initial job should trigger retry due to pending behavior
        $job = new ProcessPendingPayment($payment);
        $job->handle();
        
        // Verify payment is still pending after first attempt
        $payment->refresh();
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals(1, $payment->attempts);
        
        // Assert - Should schedule retry
        Queue::assertPushed(ProcessPendingPayment::class);
        
        // Simulate retry with successful response
        \Tests\TestMockState::clearMockBehavior();
        $retryJob = new ProcessPendingPayment($payment);
        $retryJob->handle();
        
        // Should succeed on retry
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        
        // Cleanup
        \Tests\TestMockState::clearMockBehavior();
    }

    /** @test */
    public function payment_expiration_after_24_hours()
    {
        // Arrange
        $oldPayment = $this->createStripePayment([
            'created_at' => now()->subHours(25)
        ]);
        
        // Act
        $job = new ProcessPendingPayment($oldPayment);
        $job->handle();
        
        // Assert
        $oldPayment->refresh();
        $this->assertEquals('failed', $oldPayment->status);
        $this->assertStringContainsString('Payment expired after 24 hours', $oldPayment->notes);
    }

    /** @test */
    public function concurrent_job_processing_prevents_duplicates()
    {
        // Arrange
        $payment = $this->createStripePayment();
        $this->mockStripeSuccessfulResponse();
        
        // Act - Simulate concurrent processing
        $job1 = new ProcessPendingPayment($payment);
        $job2 = new ProcessPendingPayment($payment);
        
        $job1->handle();
        
        // Payment should now be completed
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        
        // Second job should skip processing
        $job2->handle();
        
        // Assert - Should not cause issues
        $this->assertEquals('completed', $payment->status);
    }

    /** @test */
    public function error_handling_and_logging_works()
    {
        // Arrange
        $payment = $this->createStripePayment();
        
        Log::spy();
        
        // Mock Stripe to throw exception
        $this->mockStripeException();
        
        // Act & Assert
        $job = new ProcessPendingPayment($payment);
        
        $this->expectException(\Exception::class);
        $job->handle();
        
        // Verify logging occurred
        Log::shouldHaveReceived('error')
            ->with('Background payment processing error: Stripe API Error', Mockery::any());
    }

    /** @test */
    public function failed_job_marks_payment_appropriately()
    {
        // Arrange
        $payment = $this->createStripePayment();
        $job = new ProcessPendingPayment($payment);
        $exception = new \Exception('Maximum attempts exceeded');
        
        // Act
        $job->failed($exception);
        
        // Assert
        $payment->refresh();
        $this->assertStringContainsString('Background verification failed after', $payment->notes);
        $this->assertStringContainsString('Maximum attempts exceeded', $payment->notes);
    }

    /** @test */
    public function frontend_javascript_recovery_integration()
    {
        // Arrange
        $payment = $this->createStripePayment();
        
        // Simulate frontend verification page
        $response = $this->get("/payment/verify/{$payment->id}");
        $response->assertStatus(200);
        
        // Test abandonment API call (simulating JavaScript)
        $abandonResponse = $this->postJson("/api/payment/{$payment->id}/abandon", [
            'reason' => 'page_closed',
            'attempts' => 2
        ]);
        
        $abandonResponse->assertStatus(200);
        
        // Test recovery API call
        $this->mockStripeSuccessfulResponse();
        $recoverResponse = $this->postJson("/api/payment/{$payment->id}/recover");
        
        $recoverResponse->assertStatus(200)->assertJson(['status' => 'completed']);
    }

    /** @test */
    public function multi_gateway_fallback_scenario()
    {
        // Arrange - Create payment accounts for multiple gateways
        $stripeAccount = $this->createPaymentAccount('stripe');
        $paypalAccount = $this->createPaymentAccount('paypal');
        
        $payment = $this->createStripePayment(['payment_account_id' => $stripeAccount->id]);
        
        // Set mock behavior to fail for Stripe
        \Tests\TestMockState::setMockBehavior('failure');
        
        // Act - Process with Stripe (should fail but keep pending for fallback)
        $job = new ProcessPendingPayment($payment);
        $job->handle();
        
        $payment->refresh();
        $this->assertEquals('pending', $payment->status); // Should still be pending after Stripe failure
        
        // Switch to PayPal and retry
        $payment->update([
            'payment_gateway' => 'paypal',
            'payment_account_id' => $paypalAccount->id,
            'gateway_session_id' => 'PAYPAL_ORDER_123'
        ]);
        
        // Clear mock behavior for PayPal success
        \Tests\TestMockState::clearMockBehavior();
        $retryJob = new ProcessPendingPayment($payment);
        $retryJob->handle();
        
        // Assert - Should succeed with PayPal
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        
        // Cleanup
        \Tests\TestMockState::clearMockBehavior();
    }

    /** @test */
    public function load_testing_simulation()
    {
        // Arrange - Create multiple payments
        $payments = collect();
        for ($i = 0; $i < 50; $i++) {
            $payments->push($this->createStripePayment([
                'created_at' => now()->subMinutes(rand(5, 30))
            ]));
        }
        
        $this->mockStripeSuccessfulResponse();
        Queue::fake();
        
        // Act - Process all payments via command
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 60,
            '--limit' => 100
        ]);
        
        // Assert
        $this->assertEquals(0, $exitCode);
        Queue::assertPushed(ProcessPendingPayment::class, 50);
    }

    // Helper methods
    protected function createTestEnvironment()
    {
        $this->website = Website::factory()->create(['language' => 'en']);
        $this->plan = Plan::factory()->create(['duration_days' => 30]);
        $this->generatedLink = GeneratedLink::factory()->create([
            'website_id' => $this->website->id,
            'plan_id' => $this->plan->id
        ]);
    }

    protected function createStripePayment($attributes = [])
    {
        $account = $this->createPaymentAccount('stripe');
        
        return Payment::factory()->create(array_merge([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $account->id,
            'payment_gateway' => 'stripe',
            'gateway_payment_id' => 'pi_test_' . rand(1000, 9999),
            'status' => 'pending'
        ], $attributes));
    }

    protected function createPayPalPayment($attributes = [])
    {
        $account = $this->createPaymentAccount('paypal');
        
        return Payment::factory()->create(array_merge([
            'generated_link_id' => $this->generatedLink->id,
            'payment_account_id' => $account->id,
            'payment_gateway' => 'paypal',
            'gateway_session_id' => 'PAYPAL_' . rand(1000, 9999),
            'status' => 'pending'
        ], $attributes));
    }

    protected function createPaymentAccount($gateway)
    {
        $gatewayModel = PaymentGateway::factory()->create(['name' => $gateway]);
        
        $credentials = $gateway === 'stripe' 
            ? ['secret_key' => 'sk_test_123', 'publishable_key' => 'pk_test_123']
            : ['client_id' => 'paypal_client', 'client_secret' => 'paypal_secret'];
            
        return PaymentAccount::factory()->create([
            'payment_gateway_id' => $gatewayModel->id,
            'credentials' => $credentials,
            'is_sandbox' => true
        ]);
    }

    protected function mockStripeSuccessfulResponse()
    {
        $stripeMock = Mockery::mock('overload:\Stripe\StripeClient');
        $paymentIntentMock = Mockery::mock();
        $paymentIntentMock->status = 'succeeded';
        
        $stripeMock->shouldReceive('__construct');
        $stripeMock->paymentIntents = Mockery::mock();
        $stripeMock->paymentIntents->shouldReceive('retrieve')->andReturn($paymentIntentMock);
    }

    protected function mockStripeFailure()
    {
        $stripeMock = Mockery::mock('overload:\Stripe\StripeClient');
        $paymentIntentMock = Mockery::mock();
        $paymentIntentMock->status = 'payment_failed';
        
        $stripeMock->shouldReceive('__construct');
        $stripeMock->paymentIntents = Mockery::mock();
        $stripeMock->paymentIntents->shouldReceive('retrieve')->andReturn($paymentIntentMock);
    }

    protected function mockStripeException()
    {
        $stripeMock = Mockery::mock('overload:\Stripe\StripeClient');
        $stripeMock->shouldReceive('__construct')->andThrow(new \Exception('Stripe API Error'));
    }

    protected function mockStripeRetryScenario()
    {
        $stripeMock = Mockery::mock('overload:\Stripe\StripeClient');
        $pendingMock = Mockery::mock();
        $pendingMock->status = 'processing';
        $pendingMock->id = 'pi_test_processing';
        
        $stripeMock->shouldReceive('__construct');
        $stripeMock->paymentIntents = Mockery::mock();
        $stripeMock->paymentIntents->shouldReceive('retrieve')->andReturn($pendingMock);
    }

    protected function mockPayPalSuccessfulResponse()
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'test_token'
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/*' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [
                    ['payments' => ['captures' => [['status' => 'COMPLETED']]]]
                ]
            ], 200)
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up any pending transactions
        while (\DB::transactionLevel() > 0) {
            \DB::rollBack();
        }
        
        Mockery::close();
        parent::tearDown();
    }
}
