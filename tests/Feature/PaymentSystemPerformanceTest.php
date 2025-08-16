<?php

namespace Tests\Feature;

use App\Jobs\ProcessPendingPayment;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentGateway;
use App\Models\GeneratedLink;
use App\Models\Website;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Carbon\Carbon;

class PaymentSystemPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up performance testing environment
        $this->createTestData();
    }

    /** @test */
    public function test_high_volume_payment_processing()
    {
        // Arrange - Create 1000 pending payments
        $payments = collect();
        $batchSize = 100;
        $totalPayments = 1000;
        
        $startTime = microtime(true);
        
        for ($batch = 0; $batch < $totalPayments / $batchSize; $batch++) {
            $batchPayments = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $batchPayments[] = [
                    'generated_link_id' => $this->generatedLink->id,
                    'payment_account_id' => $this->paymentAccount->id,
                    'payment_gateway' => 'stripe',
                    'gateway_payment_id' => 'pi_test_' . ($batch * $batchSize + $i),
                    'amount' => 99.99,
                    'currency' => 'USD',
                    'status' => 'pending',
                    'customer_email' => "test{$batch}_{$i}@example.com",
                    'created_at' => now()->subMinutes(rand(5, 60)),
                    'updated_at' => now()
                ];
            }
            
            Payment::insert($batchPayments);
        }
        
        $creationTime = microtime(true) - $startTime;
        
        // Act - Process all payments via command
        Queue::fake();
        
        $processingStart = microtime(true);
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 120,
            '--limit' => $totalPayments
        ]);
        $processingTime = microtime(true) - $processingStart;
        
        // Assert performance benchmarks
        $this->assertEquals(0, $exitCode);
        $this->assertLessThan(5.0, $creationTime); // Should create 1000 payments in under 5 seconds
        $this->assertLessThan(10.0, $processingTime); // Should queue all in under 10 seconds
        
        // Verify all payments were queued
        Queue::assertPushed(ProcessPendingPayment::class, $totalPayments);
        
        echo "\nPerformance Metrics:\n";
        echo "- Payment Creation: {$creationTime}s for {$totalPayments} payments\n";
        echo "- Command Processing: {$processingTime}s\n";
        echo "- Throughput: " . round($totalPayments / $processingTime, 2) . " payments/sec\n";
    }

    /** @test */
    public function test_concurrent_api_requests()
    {
        // Arrange - Create payments for concurrent testing
        $payments = [];
        for ($i = 0; $i < 50; $i++) {
            $payments[] = Payment::factory()->create([
                'generated_link_id' => $this->generatedLink->id,
                'payment_account_id' => $this->paymentAccount->id,
                'status' => 'pending'
            ]);
        }
        
        $startTime = microtime(true);
        $responses = [];
        
        // Act - Simulate concurrent API requests
        for ($i = 0; $i < 100; $i++) {
            $payment = $payments[array_rand($payments)];
            $responses[] = $this->getJson("/api/payment/verify/{$payment->id}");
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        
        // Assert - All requests should complete successfully
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // Performance assertion
        $this->assertLessThan(30.0, $totalTime); // 100 requests in under 30 seconds
        
        echo "\nConcurrent API Performance:\n";
        echo "- Total Time: {$totalTime}s for 100 requests\n";
        echo "- Average Response Time: " . round($totalTime / 100 * 1000, 2) . "ms\n";
    }

    /** @test */
    public function test_database_query_optimization()
    {
        // Arrange - Create complex scenario with relationships
        $websites = Website::factory()->count(10)->create();
        $plans = Plan::factory()->count(5)->create();
        
        foreach ($websites as $website) {
            foreach ($plans as $plan) {
                $link = GeneratedLink::factory()->create([
                    'website_id' => $website->id,
                    'plan_id' => $plan->id
                ]);
                
                // Create payments for each combination
                Payment::factory()->count(20)->create([
                    'generated_link_id' => $link->id,
                    'payment_account_id' => $this->paymentAccount->id,
                    'status' => 'pending',
                    'created_at' => now()->subMinutes(rand(5, 60))
                ]);
            }
        }
        
        // Act - Test query performance
        DB::enableQueryLog();
        
        $startTime = microtime(true);
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 120,
            '--limit' => 500
        ]);
        $queryTime = microtime(true) - $startTime;
        
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        // Assert query efficiency
        $this->assertEquals(0, $exitCode);
        $this->assertLessThan(50, count($queries)); // Should not execute too many queries
        $this->assertLessThan(5.0, $queryTime); // Should complete quickly
        
        echo "\nDatabase Performance:\n";
        echo "- Query Count: " . count($queries) . "\n";
        echo "- Query Time: {$queryTime}s\n";
        echo "- Average Query Time: " . round($queryTime / count($queries) * 1000, 2) . "ms\n";
    }

    /** @test */
    public function test_memory_usage_efficiency()
    {
        $initialMemory = memory_get_usage(true);
        
        // Create large dataset
        for ($i = 0; $i < 500; $i++) {
            Payment::factory()->create([
                'generated_link_id' => $this->generatedLink->id,
                'payment_account_id' => $this->paymentAccount->id,
                'status' => 'pending',
                'created_at' => now()->subMinutes(rand(5, 60))
            ]);
        }
        
        $afterCreationMemory = memory_get_usage(true);
        
        // Process via command
        Queue::fake();
        Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 120,
            '--limit' => 500
        ]);
        
        $finalMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        // Calculate memory usage
        $creationMemoryMB = ($afterCreationMemory - $initialMemory) / 1024 / 1024;
        $processingMemoryMB = ($finalMemory - $afterCreationMemory) / 1024 / 1024;
        $peakMemoryMB = $peakMemory / 1024 / 1024;
        
        // Assert memory efficiency
        $this->assertLessThan(100, $peakMemoryMB); // Should not exceed 100MB peak
        $this->assertLessThan(50, $processingMemoryMB); // Processing should not add much overhead
        
        echo "\nMemory Usage:\n";
        echo "- Data Creation: {$creationMemoryMB}MB\n";
        echo "- Processing Overhead: {$processingMemoryMB}MB\n";
        echo "- Peak Usage: {$peakMemoryMB}MB\n";
    }

    /** @test */
    public function test_job_queue_performance()
    {
        // Arrange - Create payments
        $payments = [];
        for ($i = 0; $i < 200; $i++) {
            $payments[] = Payment::factory()->create([
                'generated_link_id' => $this->generatedLink->id,
                'payment_account_id' => $this->paymentAccount->id,
                'status' => 'pending'
            ]);
        }
        
        // Act - Test job dispatching performance
        $startTime = microtime(true);
        
        foreach ($payments as $payment) {
            ProcessPendingPayment::dispatch($payment);
        }
        
        $dispatchTime = microtime(true) - $startTime;
        
        // Assert performance
        $this->assertLessThan(5.0, $dispatchTime); // Should dispatch 200 jobs in under 5 seconds
        
        echo "\nJob Queue Performance:\n";
        echo "- Dispatch Time: {$dispatchTime}s for 200 jobs\n";
        echo "- Jobs per second: " . round(200 / $dispatchTime, 2) . "\n";
    }

    /** @test */
    public function test_error_resilience_under_load()
    {
        // Arrange - Create mix of valid and problematic payments
        $validPayments = 0;
        $invalidPayments = 0;
        
        for ($i = 0; $i < 100; $i++) {
            if ($i % 10 === 0) {
                // Create problematic payment (no gateway_payment_id)
                Payment::factory()->create([
                    'generated_link_id' => $this->generatedLink->id,
                    'payment_account_id' => null, // Missing account
                    'status' => 'pending'
                ]);
                $invalidPayments++;
            } else {
                // Create valid payment
                Payment::factory()->create([
                    'generated_link_id' => $this->generatedLink->id,
                    'payment_account_id' => $this->paymentAccount->id,
                    'status' => 'pending',
                    'gateway_payment_id' => 'pi_test_' . $i
                ]);
                $validPayments++;
            }
        }
        
        // Act - Process with errors
        Queue::fake();
        $startTime = microtime(true);
        
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 0,
            '--max-age' => 120,
            '--limit' => 100
        ]);
        
        $processingTime = microtime(true) - $startTime;
        
        // Assert - Should handle errors gracefully
        $this->assertEquals(0, $exitCode); // Command should complete successfully
        $this->assertLessThan(10.0, $processingTime); // Should not be significantly slower
        
        // Should queue valid payments (may queue some invalid ones too, they'll fail in job)
        Queue::assertPushed(ProcessPendingPayment::class, function ($job) {
            return true; // At least some jobs were queued
        });
        
        echo "\nError Resilience:\n";
        echo "- Valid Payments: {$validPayments}\n";
        echo "- Invalid Payments: {$invalidPayments}\n";
        echo "- Processing Time: {$processingTime}s\n";
        echo "- Command Exit Code: {$exitCode}\n";
    }

    /** @test */
    public function test_scalability_limits()
    {
        $results = [];
        $sizes = [100, 500, 1000, 2000];
        
        foreach ($sizes as $size) {
            // Clean up previous data
            Payment::truncate();
            
            // Create batch of payments
            $startTime = microtime(true);
            
            $batchData = [];
            for ($i = 0; $i < $size; $i++) {
                $batchData[] = [
                    'generated_link_id' => $this->generatedLink->id,
                    'payment_account_id' => $this->paymentAccount->id,
                    'payment_gateway' => 'stripe',
                    'gateway_payment_id' => 'pi_test_' . $i,
                    'amount' => 99.99,
                    'currency' => 'USD',
                    'status' => 'pending',
                    'customer_email' => "test{$i}@example.com",
                    'created_at' => now()->subMinutes(rand(5, 60)),
                    'updated_at' => now()
                ];
            }
            
            Payment::insert($batchData);
            $creationTime = microtime(true) - $startTime;
            
            // Process batch
            Queue::fake();
            $processingStart = microtime(true);
            
            Artisan::call('payments:verify-pending', [
                '--min-age' => 2,
                '--max-age' => 120,
                '--limit' => $size
            ]);
            
            $processingTime = microtime(true) - $processingStart;
            
            $results[$size] = [
                'creation_time' => $creationTime,
                'processing_time' => $processingTime,
                'throughput' => round($size / $processingTime, 2)
            ];
        }
        
        // Assert scalability
        foreach ($results as $size => $metrics) {
            $this->assertLessThan(30.0, $metrics['processing_time']); // Reasonable processing time
            $this->assertGreaterThan(10, $metrics['throughput']); // Minimum throughput
        }
        
        echo "\nScalability Analysis:\n";
        foreach ($results as $size => $metrics) {
            echo "Size: {$size} | Creation: {$metrics['creation_time']}s | Processing: {$metrics['processing_time']}s | Throughput: {$metrics['throughput']} payments/s\n";
        }
    }

    protected function createTestData()
    {
        $this->website = Website::factory()->create(['language' => 'en']);
        $this->plan = Plan::factory()->create(['duration_days' => 30]);
        $this->generatedLink = GeneratedLink::factory()->create([
            'website_id' => $this->website->id,
            'plan_id' => $this->plan->id
        ]);
        
        $gateway = PaymentGateway::factory()->create(['name' => 'stripe']);
        $this->paymentAccount = PaymentAccount::factory()->create([
            'payment_gateway_id' => $gateway->id,
            'credentials' => [
                'secret_key' => 'sk_test_123',
                'publishable_key' => 'pk_test_123'
            ]
        ]);
    }
}
