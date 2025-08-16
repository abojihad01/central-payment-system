<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Carbon\Carbon;

class ProcessPlanChanges extends Command
{
    protected $signature = 'subscriptions:process-plan-changes';
    protected $description = 'Process scheduled plan changes for subscriptions';

    public function handle()
    {
        $processedCount = 0;
        
        // Find subscriptions with scheduled plan changes
        $subscriptions = Subscription::whereNotNull('scheduled_plan_change')
            // Allow execution at either the next billing date or the end of the current period
            ->where(function ($q) {
                $q->whereNull('next_billing_date')
                  ->orWhere('next_billing_date', '<=', now())
                  ->orWhere('expires_at', '<=', now());
            })
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($subscription->scheduled_plan_change && $subscription->plan_change_type) {
                $newPlanId = $subscription->scheduled_plan_change;
                
                // Apply the scheduled plan change
                $subscription->update([
                    'plan_id' => $newPlanId,
                    'scheduled_plan_change' => null,
                    'plan_change_type' => null
                ]);
                
                $processedCount++;
            }
        }

        $this->info("Processed {$processedCount} scheduled plan changes");
        return 0;
    }
}