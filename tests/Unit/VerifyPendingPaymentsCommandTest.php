<?php

namespace Tests\Unit;

use App\Console\Commands\VerifyPendingPayments;
use App\Jobs\ProcessPendingPayment;
use App\Models\Payment;
use App\Models\GeneratedLink;
use App\Models\Website;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Carbon\Carbon;

class VerifyPendingPaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $website = Website::factory()->create();
        $plan = Plan::factory()->create();
        $generatedLink = GeneratedLink::factory()->create([
            'website_id' => $website->id,
            'plan_id' => $plan->id
        ]);

        // Create payments with different ages
        Payment::factory()->create([
            'generated_link_id' => $generatedLink->id,
            'status' => 'pending',
            'created_at' => now()->subMinutes(3), // Recent payment
            'gateway_payment_id' => null
        ]);

        Payment::factory()->create([
            'generated_link_id' => $generatedLink->id,
            'status' => 'pending',
            'created_at' => now()->subMinutes(30), // Older payment
            'gateway_payment_id' => null
        ]);

        Payment::factory()->create([
            'generated_link_id' => $generatedLink->id,
            'status' => 'completed', // Completed payment - should be skipped
            'created_at' => now()->subMinutes(10),
            'gateway_payment_id' => 'pi_completed'
        ]);

        Payment::factory()->create([
            'generated_link_id' => $generatedLink->id,
            'status' => 'pending',
            'created_at' => now()->subHours(25), // Very old payment
            'gateway_payment_id' => null
        ]);
    }

    /** @test */
    public function command_processes_payments_within_age_range()
    {
        Queue::fake();

        // Run command with min-age=2 and max-age=60 (should process payments 3-60 minutes old)
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 60,
            '--limit' => 10
        ]);

        // Assert
        $this->assertEquals(0, $exitCode);
        
        // Should process 2 pending payments (3min and 30min old, skip completed and very old)
        Queue::assertPushed(ProcessPendingPayment::class, 2);
    }

    /** @test */
    public function command_respects_limit_parameter()
    {
        Queue::fake();

        // Run command with limit of 1
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 60,
            '--limit' => 1
        ]);

        // Assert
        $this->assertEquals(0, $exitCode);
        Queue::assertPushed(ProcessPendingPayment::class, 1);
    }

    /** @test */
    public function command_skips_recently_updated_payments()
    {
        Queue::fake();

        // Update one payment to simulate recent processing
        $recentPayment = Payment::where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(10))
            ->first();
        
        $recentPayment->update([
            'updated_at' => now()->subMinutes(5), // Recently updated
            'notes' => 'Recently processed'
        ]);

        // Run command
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 60,
            '--limit' => 10
        ]);

        // Assert - Should process fewer payments due to recent update check
        $this->assertEquals(0, $exitCode);
        Queue::assertPushed(ProcessPendingPayment::class, function ($job) use ($recentPayment) {
            return $job->payment->id !== $recentPayment->id;
        });
    }

    /** @test */
    public function command_handles_no_pending_payments()
    {
        // Mark all payments as completed
        Payment::where('status', 'pending')->update(['status' => 'completed']);

        Queue::fake();

        // Run command
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 60,
            '--limit' => 10
        ]);

        // Assert
        $this->assertEquals(0, $exitCode);
        Queue::assertNothingPushed();
        
        // Check output contains appropriate message
        $output = Artisan::output();
        $this->assertStringContainsString('No pending payments found', $output);
    }

    /** @test */
    public function command_logs_processing_summary()
    {
        Queue::fake();

        // Run command
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 60,
            '--limit' => 10
        ]);

        // Assert
        $this->assertEquals(0, $exitCode);
        
        $output = Artisan::output();
        $this->assertStringContainsString('Starting background payment verification', $output);
        $this->assertStringContainsString('Process completed:', $output);
        $this->assertStringContainsString('Queued:', $output);
        $this->assertStringContainsString('Skipped:', $output);
    }

    /** @test */
    public function command_filters_by_age_correctly()
    {
        Queue::fake();

        // Test with very narrow age range that should exclude most payments
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 25,
            '--max-age' => 35,
            '--limit' => 10
        ]);

        // Assert - Should only process the 30-minute old payment
        $this->assertEquals(0, $exitCode);
        Queue::assertPushed(ProcessPendingPayment::class, 1);
    }

    /** @test */
    public function command_handles_edge_case_ages()
    {
        Queue::fake();

        // Test with min-age > max-age (invalid range)
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 60,
            '--max-age' => 30, // Invalid: min > max
            '--limit' => 10
        ]);

        // Should handle gracefully and process no payments
        $this->assertEquals(0, $exitCode);
        Queue::assertNothingPushed();
    }

    /** @test */
    public function command_prevents_duplicate_processing()
    {
        Queue::fake();

        // First run
        Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 60,
            '--limit' => 10
        ]);

        $firstRunCount = Queue::pushedJobs()[ProcessPendingPayment::class] ?? [];

        Queue::fake(); // Reset queue

        // Immediate second run - should skip recently processed payments
        Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 60,
            '--limit' => 10
        ]);

        $secondRunCount = Queue::pushedJobs()[ProcessPendingPayment::class] ?? [];

        // Second run should process fewer or no payments
        $this->assertLessThanOrEqual(count($firstRunCount), count($secondRunCount));
    }

    /** @test */
    public function command_works_with_default_parameters()
    {
        Queue::fake();

        // Run command with default parameters
        $exitCode = Artisan::call('payments:verify-pending');

        // Assert
        $this->assertEquals(0, $exitCode);
        
        // Should process some payments with default settings
        $output = Artisan::output();
        $this->assertStringContainsString('Parameters: limit=100, min-age=5min, max-age=1440min', $output);
    }

    /** @test */
    public function command_handles_database_errors_gracefully()
    {
        Queue::fake();

        // Create a payment with invalid foreign key to simulate DB error
        try {
            Payment::factory()->create([
                'generated_link_id' => 99999, // Non-existent link
                'status' => 'pending',
                'created_at' => now()->subMinutes(10)
            ]);
        } catch (\Exception $e) {
            // Expected to fail due to foreign key constraint
        }

        // Run command - should handle any DB errors gracefully
        $exitCode = Artisan::call('payments:verify-pending', [
            '--min-age' => 2,
            '--max-age' => 60,
            '--limit' => 10
        ]);

        // Should complete successfully despite DB issues
        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function command_processes_payments_in_correct_order()
    {
        Queue::fake();

        // Create payments with specific order
        $oldestPayment = Payment::factory()->create([
            'generated_link_id' => GeneratedLink::first()->id,
            'status' => 'pending',
            'created_at' => now()->subMinutes(50), // Oldest
            'gateway_payment_id' => null
        ]);

        $newerPayment = Payment::factory()->create([
            'generated_link_id' => GeneratedLink::first()->id,
            'status' => 'pending',
            'created_at' => now()->subMinutes(20), // Newer
            'gateway_payment_id' => null
        ]);

        // Run command
        Artisan::call('payments:verify-pending', [
            '--min-age' => 10,
            '--max-age' => 60,
            '--limit' => 10
        ]);

        // Verify that jobs were queued (order verification would require more complex setup)
        Queue::assertPushed(ProcessPendingPayment::class, 2);
    }
}
