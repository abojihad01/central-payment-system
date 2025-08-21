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
        Commands\SyncStripePlansAcrossAccounts::class,
        Commands\VerifyPendingPayments::class,
        Commands\RecoverLostPayments::class,
        Commands\MonitorPaymentHealth::class,
        Commands\FixBrokenSubscriptions::class,
        Commands\SendDeviceExpiryReminders::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Log scheduler runs
        $schedule->call(function () {
            \Cache::put('scheduler_last_run', now()->format('Y-m-d H:i:s'), 3600);
        })->everyMinute()->name('update_scheduler_timestamp');
        // Core payment processing schedules
        $schedule->command('payments:verify-pending --min-age=2 --max-age=60 --limit=20')
                ->everyTwoMinutes()
                ->description('Quick verification of recent pending payments');

        $schedule->command('payments:verify-pending --min-age=60 --max-age=480 --limit=50')
                ->everyTenMinutes()
                ->description('Verify older pending payments');

        $schedule->command('payments:verify-pending --min-age=480 --max-age=1440 --limit=100')
                ->hourly()
                ->description('Verify very old pending payments');

        // Lost payment recovery
        $schedule->command('payments:recover-lost --limit=25 --min-age=30 --max-age=2880')
                ->everyTwoMinutes()
                ->description('Recover payments that succeeded in gateway but stuck in system');

        // Health monitoring
        $schedule->command('payments:monitor-health --alert-threshold=5')
                ->everyFifteenMinutes()
                ->description('Monitor payment system health');

        // Daily health report with email alerts
        $schedule->command('payments:monitor-health --alert-threshold=20 --send-email')
                ->dailyAt('09:00')
                ->description('Daily comprehensive health report');

        // Subscription management
        $schedule->command('subscriptions:check-expired')->hourly()
                ->description('Check for expired subscriptions');
        
        $schedule->command('subscriptions:process-plan-changes')->daily()
                ->description('Process pending plan changes');

        // Cleanup and maintenance
        $schedule->command('payments:verify-pending --min-age=1440 --max-age=10080 --limit=200')
                ->dailyAt('02:00')
                ->description('Daily cleanup of very old pending payments');

        $schedule->command('payments:recover-lost --limit=100 --min-age=1440 --max-age=10080')
                ->weekly()
                ->sundays()
                ->at('08:00')
                ->description('Weekly recovery scan for lost payments');

        // Stripe integration
        $schedule->command('stripe:sync-plans')->dailyAt('03:00')
                ->description('Sync Stripe plans across accounts');

        // Gold Panel device management
        $schedule->command('devices:send-expiry-reminders --days=3')
                ->dailyAt('10:00')
                ->description('Send expiry reminders for devices expiring in 3 days');
        
        $schedule->command('devices:send-expiry-reminders --days=1')
                ->dailyAt('10:00')
                ->description('Send expiry reminders for devices expiring tomorrow');

        // Queue maintenance
        $schedule->command('queue:prune-failed --hours=168')
                ->weekly()
                ->description('Clean up old failed jobs (1 week)');

        // Log rotation and cleanup
        $schedule->command('queue:clear')
                ->weekly()
                ->description('Clear processed queue jobs')
                ->when(function () {
                    return \DB::table('jobs')->count() > 10000;
                });
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