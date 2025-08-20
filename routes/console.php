<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule pending payments check and background verification
app()->booted(function () {
    $schedule = app(Schedule::class);
    
    // Background payment verification - every 2 minutes for recent payments
    $schedule->command('payments:verify-pending --min-age=2 --max-age=60 --limit=20')
        ->everyTwoMinutes()
        ->withoutOverlapping()
        ->runInBackground()
        ->description('Background verification for recent pending payments')
        ->onSuccess(function () {
            \Log::debug('Background payment verification completed successfully');
        })
        ->onFailure(function () {
            \Log::error('Background payment verification failed');
        });
    
    // Background verification for older payments - every 10 minutes  
    $schedule->command('payments:verify-pending --min-age=60 --max-age=480 --limit=50')
        ->everyTenMinutes()
        ->withoutOverlapping()
        ->runInBackground()
        ->description('Background verification for older pending payments (1-8 hours)')
        ->onSuccess(function () {
            \Log::info('Background verification for older payments completed successfully');
        })
        ->onFailure(function () {
            \Log::error('Background verification for older payments failed');
        });
    
    // Final attempt background verification - every hour for very old payments
    $schedule->command('payments:verify-pending --min-age=480 --max-age=1440 --limit=100')
        ->hourly()
        ->withoutOverlapping()
        ->runInBackground()
        ->description('Final background verification attempt for payments (8-24 hours old)')
        ->onSuccess(function () {
            \Log::info('Final background verification attempt completed successfully');
        })
        ->onFailure(function () {
            \Log::error('Final background verification attempt failed');
        });
    
    // Legacy payment verification commands (keeping for compatibility)
    // Check pending payments every minute (for recent payments)
    $schedule->command('payments:check-pending --max-age=0.25 --limit=10 --quiet')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground()
        ->description('Check very recent pending payments (last 15 minutes)')
        ->onSuccess(function () {
            \Log::debug('Recent pending payments check completed successfully');
        })
        ->onFailure(function () {
            \Log::error('Recent pending payments check failed');
        });
    
    // Check older pending payments every 5 minutes  
    $schedule->command('payments:check-pending --max-age=2 --limit=25 --quiet')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->runInBackground()
        ->description('Check pending payments up to 2 hours old')
        ->onSuccess(function () {
            \Log::info('Regular pending payments check completed successfully');
        })
        ->onFailure(function () {
            \Log::error('Regular pending payments check failed');
        });
    
    // Daily cleanup of very old pending payments (older than 24 hours)
    $schedule->command('payments:verify-pending --min-age=1440 --max-age=10080 --limit=200')
        ->daily()
        ->at('02:00')
        ->withoutOverlapping()
        ->description('Daily cleanup and final verification attempt for very old payments')
        ->onSuccess(function () {
            \Log::info('Daily payment cleanup completed successfully');
        })
        ->onFailure(function () {
            \Log::error('Daily payment cleanup failed');
        });
        
    // Weekly detailed report of all pending payments
    $schedule->command('payments:verify-pending --min-age=1 --max-age=10080 --limit=1000')
        ->weekly()
        ->sundays()
        ->at('08:00')
        ->description('Weekly comprehensive payment verification check')
        ->onSuccess(function () {
            \Log::info('Weekly payment verification report completed successfully');
        })
        ->onFailure(function () {
            \Log::error('Weekly payment verification report failed');
        });

    // Lost payment recovery - Quick recovery every minute
    $schedule->command('payments:recover-lost --limit=25 --min-age=30 --max-age=2880')
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground()
        ->description('Recover payments that succeeded in gateway but stuck in system')
        ->onSuccess(function () {
            \Log::info('Quick lost payment recovery completed successfully');
        })
        ->onFailure(function () {
            \Log::error('Quick lost payment recovery failed');
        });

    // Lost payment recovery - Weekly comprehensive recovery  
    $schedule->command('payments:recover-lost --limit=100 --min-age=1440 --max-age=10080')
        ->weekly()
        ->sundays()
        ->at('08:00')
        ->withoutOverlapping()
        ->description('Weekly recovery scan for lost payments')
        ->onSuccess(function () {
            \Log::info('Weekly lost payment recovery completed successfully');
        })
        ->onFailure(function () {
            \Log::error('Weekly lost payment recovery failed');
        });
});
