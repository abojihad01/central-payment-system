<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\SubscriptionExpiringSoon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyExpiringSubscriptions extends Command
{
    protected $signature = 'subscriptions:notify-expiring 
                            {--days=7 : Number of days before expiration to send notification}
                            {--limit=100 : Maximum number of subscriptions to process}';
    protected $description = 'Send notifications for subscriptions expiring soon';

    public function handle()
    {
        $days = (int) $this->option('days');
        $limit = $this->option('limit');
        
        $this->info("جاري إرسال إشعارات الاشتراكات التي تنتهي خلال {$days} أيام...");

        // البحث عن الاشتراكات النشطة التي تنتهي قريباً
        $expiringSubscriptions = Subscription::where('status', 'active')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now())
            ->limit($limit)
            ->get();

        $notificationCount = 0;

        foreach ($expiringSubscriptions as $subscription) {
            // إرسال إشعار انتهاء الصلاحية قريباً
            Notification::send($subscription, new SubscriptionExpiringSoon($subscription));

            $notificationCount++;
        }

        $this->info("تم إرسال {$notificationCount} إشعار للاشتراكات المنتهية قريباً.");
        
        return self::SUCCESS;
    }
}