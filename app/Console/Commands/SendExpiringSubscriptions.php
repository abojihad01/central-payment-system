<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\NotificationLog;
use App\Notifications\SubscriptionExpiring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendExpiringSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-expiring-subscriptions {--batch-size=25}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications for subscriptions that are expiring soon';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $batchSize = $this->option('batch-size');
        
        // Get subscriptions expiring within 7 days
        $expiringSubscriptions = Subscription::where('status', 'active')
            ->where('expires_at', '<=', now()->addDays(7))
            ->where('expires_at', '>', now())
            ->limit($batchSize)
            ->get();

        $count = 0;
        foreach ($expiringSubscriptions as $subscription) {
            $subscription->sendExpiringNotification();
            $count++;
        }

        $this->info("Sent {$count} expiring subscription notifications");
        
        return 0;
    }
}
