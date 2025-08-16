<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPendingPayment;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyPendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:verify-pending 
                           {--limit=100 : Maximum number of payments to process}
                           {--min-age=5 : Minimum age in minutes for payments to be processed}
                           {--max-age=1440 : Maximum age in minutes for payments to be processed (24 hours)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue background verification for pending payments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $minAge = $this->option('min-age');
        $maxAge = $this->option('max-age');

        $this->info("Starting background payment verification process...");
        $this->info("Parameters: limit={$limit}, min-age={$minAge}min, max-age={$maxAge}min");

        // Get pending payments that meet the criteria
        $query = Payment::where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($minAge))
            ->where('created_at', '>=', now()->subMinutes($maxAge))
            ->orderBy('created_at', 'desc') // Process newer payments first
            ->limit($limit);

        $pendingPayments = $query->get();

        if ($pendingPayments->isEmpty()) {
            $this->info('No pending payments found matching the criteria.');
            return Command::SUCCESS;
        }

        $this->info("Found {$pendingPayments->count()} pending payments to process.");

        $queued = 0;
        $skipped = 0;

        foreach ($pendingPayments as $payment) {
            try {
                // Check if payment already has a background job running
                if ($this->hasRecentBackgroundAttempt($payment)) {
                    $this->warn("Payment {$payment->id} already has recent background verification, skipping.");
                    $skipped++;
                    continue;
                }

                // Dispatch background job
                ProcessPendingPayment::dispatch($payment);
                
                $this->info("Queued background verification for payment {$payment->id} (Gateway: {$payment->payment_gateway})");
                $queued++;

                // Add small delay to prevent overwhelming the queue
                usleep(100000); // 0.1 seconds

            } catch (\Exception $e) {
                $this->error("Failed to queue payment {$payment->id}: " . $e->getMessage());
                Log::error('Failed to queue background payment verification', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Process completed:");
        $this->info("- Queued: {$queued} payments");
        $this->info("- Skipped: {$skipped} payments");
        $this->info("- Total processed: " . ($queued + $skipped));

        // Log the summary
        Log::info('Background payment verification batch completed', [
            'queued' => $queued,
            'skipped' => $skipped,
            'total' => $queued + $skipped,
            'limit' => $limit,
            'min_age_minutes' => $minAge,
            'max_age_minutes' => $maxAge
        ]);

        return Command::SUCCESS;
    }

    /**
     * Check if payment has recent background verification attempt
     */
    private function hasRecentBackgroundAttempt(Payment $payment): bool
    {
        $notes = $payment->notes ?? '';
        
        // Check if there's a recent background verification log in notes
        $lines = explode("\n", $notes);
        foreach ($lines as $line) {
            if (str_contains($line, 'Background verification') || str_contains($line, 'background verification')) {
                // Extract timestamp if available and check if it's recent (within last 10 minutes)
                if (preg_match('/\d{4}-\d{2}-\d{2}/', $line)) {
                    // For simplicity, assume any background verification note means recent attempt
                    return true;
                }
            }
        }

        // Also check if payment was updated recently (might indicate ongoing processing)
        // Only check if updated_at is significantly different from created_at
        return $payment->updated_at->gt(now()->subMinutes(10)) && 
               $payment->updated_at->diffInMinutes($payment->created_at) > 1;
    }
}
