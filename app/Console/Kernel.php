<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\CheckExpiredSubscriptions::class,
        Commands\CheckPendingPayments::class,
        Commands\CreateSubscriptionForPayment::class,
        Commands\NotifyExpiringSubscriptions::class,
        Commands\PendingPaymentsReport::class,
        Commands\ProcessPendingPayments::class,
        Commands\ProcessPlanChanges::class,
        Commands\RealTimePaymentMonitor::class,
        Commands\SyncStripeProducts::class,
        Commands\VerifyPendingPayments::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Check for expired subscriptions every hour
        $schedule->command('subscriptions:check-expired')->hourly();
        
        // Process plan changes daily at midnight
        $schedule->command('subscriptions:process-plan-changes')->daily();
        
        // Check pending payments every 30 minutes
        $schedule->command('payments:verify-pending')->everyThirtyMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}