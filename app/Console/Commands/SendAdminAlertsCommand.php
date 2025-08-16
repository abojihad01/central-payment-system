<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\User;
use App\Notifications\AdminPaymentAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendAdminAlertsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-admin-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send admin payment alerts for suspicious activities';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for suspicious payment activities...');

        // Find high-value payments from the last 24 hours
        $highValuePayments = Payment::where('amount', '>', 1000)
            ->where('created_at', '>', now()->subDay())
            ->where('status', 'completed')
            ->get();

        // Find failed payments above certain threshold
        $failedPayments = Payment::where('amount', '>', 500)
            ->where('created_at', '>', now()->subDay())
            ->where('status', 'failed')
            ->get();

        $alertCount = 0;

        // Send alerts for high-value payments
        foreach ($highValuePayments as $payment) {
            $this->sendAdminAlert($payment, 'high_value');
            $alertCount++;
        }

        // Send alerts for failed high-value payments
        foreach ($failedPayments as $payment) {
            $this->sendAdminAlert($payment, 'failed_high_value');
            $alertCount++;
        }

        $this->info("Sent {$alertCount} admin alerts.");
        return 0;
    }

    private function sendAdminAlert($payment, $type)
    {
        $admins = User::where('role', 'admin')->get();
        
        foreach ($admins as $admin) {
            Notification::send($admin, new AdminPaymentAlert($payment, $type));
        }
    }
}
