<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use Carbon\Carbon;

class RealTimePaymentMonitor extends Command
{
    protected $signature = 'payments:monitor-realtime 
                            {--interval=15 : Check interval in seconds (default: 15)}
                            {--duration=300 : Total monitoring duration in seconds (default: 5 minutes)}
                            {--max-age=0.5 : Maximum age in hours for payments to process (default: 30 minutes)}';

    protected $description = 'Real-time monitoring of pending payments (use carefully - high frequency)';

    public function handle()
    {
        $interval = (int) $this->option('interval');
        $duration = (int) $this->option('duration');
        $maxAge = (float) $this->option('max-age');
        
        $this->warn("🚨 REAL-TIME MONITORING MODE");
        $this->info("⏱️  Checking every {$interval} seconds for {$duration} seconds total");
        $this->info("📅 Processing payments older than {$maxAge} hours");
        $this->newLine();
        
        if (!$this->confirm('This will check payments very frequently. Are you sure?')) {
            $this->info('Cancelled.');
            return 0;
        }
        
        $startTime = time();
        $endTime = $startTime + $duration;
        $checkCount = 0;
        $totalProcessed = 0;
        
        $this->info("🔍 Starting real-time monitoring...");
        $this->newLine();
        
        while (time() < $endTime) {
            $checkCount++;
            $checkTime = now();
            
            // Get recent pending payments
            $cutoffTime = $checkTime->copy()->subHours($maxAge);
            $pendingPayments = Payment::where('status', 'pending')
                ->where('created_at', '<=', $cutoffTime)
                ->limit(5) // Process only 5 at a time to avoid overload
                ->get();
            
            if ($pendingPayments->isNotEmpty()) {
                $this->info("🔍 Check #{$checkCount} at {$checkTime->format('H:i:s')} - Found {$pendingPayments->count()} pending payments");
                
                foreach ($pendingPayments as $payment) {
                    $processed = $this->processPayment($payment);
                    if ($processed) {
                        $totalProcessed++;
                        $this->info("✅ Processed payment #{$payment->id} - {$payment->customer_email}");
                    }
                }
            } else {
                $this->comment("✓ Check #{$checkCount} at {$checkTime->format('H:i:s')} - No pending payments");
            }
            
            // Sleep for the specified interval
            if (time() < $endTime - $interval) {
                sleep($interval);
            }
        }
        
        $this->newLine();
        $this->info("📊 Real-time monitoring completed!");
        $this->info("⏱️  Total checks: {$checkCount}");
        $this->info("✅ Total payments processed: {$totalProcessed}");
        $this->info("⌛ Duration: " . gmdate('i:s', time() - $startTime));
        
        return 0;
    }
    
    private function processPayment(Payment $payment): bool
    {
        try {
            // Use the same processing logic as CheckPendingPayments
            $this->call('subscription:create-for-payment', ['payment_id' => $payment->id]);
            return true;
        } catch (\Exception $e) {
            $this->error("❌ Failed to process payment {$payment->id}: {$e->getMessage()}");
            return false;
        }
    }
}
