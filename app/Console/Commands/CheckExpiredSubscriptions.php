<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionExpired;
use App\Notifications\AdminPaymentAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckExpiredSubscriptions extends Command
{
    protected $signature = 'subscriptions:check-expired {--limit=100 : Maximum number of subscriptions to process}';
    protected $description = 'Check and process expired subscriptions';

    public function handle()
    {
        $limit = $this->option('limit');
        
        $this->info("جاري فحص الاشتراكات المنتهية الصلاحية...");

        // البحث عن الاشتراكات النشطة المنتهية الصلاحية
        $expiredSubscriptions = Subscription::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->limit($limit)
            ->get();

        $processedCount = 0;

        foreach ($expiredSubscriptions as $subscription) {
            // إذا كان الاشتراك له فترة سماح، ندخله في فترة السماح أولاً
            if ($subscription->grace_period_days && $subscription->grace_period_days > 0) {
                $subscription->update([
                    'status' => 'grace_period',
                    'grace_period_ends_at' => now()->addDays($subscription->grace_period_days)
                ]);
            } else {
                // تحديث حالة الاشتراك إلى منتهي الصلاحية
                $subscription->update([
                    'status' => 'expired',
                    'expired_at' => now()
                ]);
            }

            // إرسال إشعار انتهاء الصلاحية
            Notification::send($subscription, new SubscriptionExpired($subscription));

            $processedCount++;
        }
        
        // البحث عن الاشتراكات المجدولة للإلغاء في نهاية الفترة
        $scheduledCancellations = Subscription::where('will_cancel_at_period_end', true)
            ->where('expires_at', '<=', now())
            ->limit($limit)
            ->get();

        foreach ($scheduledCancellations as $subscription) {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now()
            ]);
            $processedCount++;
        }
        
        // البحث عن الاشتراكات في فترة السماح المنتهية
        $expiredGracePeriods = Subscription::where('status', 'grace_period')
            ->where('grace_period_ends_at', '<=', now())
            ->limit($limit)
            ->get();

        foreach ($expiredGracePeriods as $subscription) {
            $subscription->update([
                'status' => 'expired',
                'expired_at' => now()
            ]);
            $processedCount++;
        }

        $this->info("تم معالجة {$processedCount} اشتراك منتهي الصلاحية.");
        
        // إرسال تنبيه إداري إذا كان هناك اشتراكات منتهية
        if ($processedCount > 0) {
            $adminUsers = User::where('role', 'admin')->get();
            foreach ($adminUsers as $admin) {
                Notification::send($admin, new AdminPaymentAlert([
                    'alert_type' => 'subscriptions_expired',
                    'count' => $processedCount,
                    'message' => "تم العثور على {$processedCount} اشتراك منتهي الصلاحية."
                ]));
            }
        }
        
        return self::SUCCESS;
    }
}