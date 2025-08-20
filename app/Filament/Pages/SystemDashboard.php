<?php

namespace App\Filament\Pages;

use App\Models\Payment;
use App\Jobs\ProcessPendingPayment;
use Filament\Pages\Page;
use Filament\Actions;
use Illuminate\Support\Facades\Artisan;
use Stripe\StripeClient;

class SystemDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'النظام';
    protected static ?string $navigationLabel = 'لوحة تحكم النظام';
    protected static string $view = 'filament.pages.system-dashboard';
    protected static ?int $navigationSort = 1;

    public $lostPayments = [];
    public $healthStats = [];
    public $recentRecoveries = [];
    public $scheduledTasks = [];
    public $queueStats = [];
    public $cronStatus = [];
    public $isScanning = false;

    public function mount()
    {
        $this->loadAllData();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh_all')
                ->label('تحديث جميع البيانات')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function () {
                    $this->loadAllData();
                    \Filament\Notifications\Notification::make()
                        ->title('تم تحديث جميع البيانات')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('run_emergency_recovery')
                ->label('استعادة طارئة')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->action(function () {
                    $this->runEmergencyRecovery();
                })
                ->requiresConfirmation()
                ->modalHeading('تشغيل الاستعادة الطارئة')
                ->modalDescription('سيتم البحث عن جميع الدفعات المفقودة واستعادتها فوراً'),

            Actions\Action::make('system_health_report')
                ->label('تقرير صحة شامل')
                ->icon('heroicon-o-document-chart-bar')
                ->color('success')
                ->action(function () {
                    $this->generateHealthReport();
                }),
        ];
    }

    public function loadAllData()
    {
        $this->loadLostPayments();
        $this->loadHealthStats();
        $this->loadRecentRecoveries();
        $this->loadScheduledTasks();
        $this->loadQueueStats();
        $this->loadCronStatus();
    }

    // === Payment Recovery Functions ===
    public function loadLostPayments()
    {
        $suspiciousPayments = Payment::where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(10))
            ->where('created_at', '>', now()->subHours(48))
            ->whereNotNull('gateway_session_id')
            ->limit(20)
            ->get();

        $this->lostPayments = [];
        
        foreach ($suspiciousPayments as $payment) {
            try {
                $gatewayStatus = $this->checkPaymentInGateway($payment);
                if ($gatewayStatus['status'] === 'completed') {
                    $this->lostPayments[] = [
                        'payment' => $payment,
                        'gateway_status' => $gatewayStatus,
                        'lost_duration' => $payment->created_at->diffForHumans(),
                        'amount' => $payment->amount,
                        'customer_email' => $payment->customer_email,
                    ];
                }
            } catch (\Exception $e) {
                // Skip payments that can't be checked
            }
        }
    }

    public function loadHealthStats()
    {
        $this->healthStats = [
            'total_pending' => Payment::where('status', 'pending')->count(),
            'stuck_payments' => Payment::where('status', 'pending')
                ->where('created_at', '<', now()->subHour())
                ->count(),
            'success_rate_24h' => $this->calculateSuccessRate(24),
            'success_rate_1h' => $this->calculateSuccessRate(1),
            'webhook_failures' => $this->getWebhookFailureCount(),
            'last_recovery_run' => cache('last_recovery_scan', 'Never'),
            'total_payments_today' => Payment::whereDate('created_at', today())->count(),
            'completed_payments_today' => Payment::whereDate('created_at', today())
                ->where('status', 'completed')->count(),
        ];
    }

    public function loadRecentRecoveries()
    {
        $this->recentRecoveries = Payment::where('notes', 'like', '%recovered%')
            ->orWhere('notes', 'like', '%webhook%')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'customer_email' => $payment->customer_email,
                    'recovered_at' => $payment->updated_at->diffForHumans(),
                    'method' => str_contains($payment->notes, 'webhook') ? 'Webhook' : 'Manual Recovery',
                ];
            })->toArray();
    }

    // === Scheduler Functions ===
    public function loadScheduledTasks()
    {
        try {
            $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
            $events = $schedule->events();

            $this->scheduledTasks = collect($events)->map(function ($event) {
                return [
                    'command' => $event->command ?? $event->getSummaryForDisplay(),
                    'expression' => $event->getExpression(),
                    'description' => $event->description ?? 'No description',
                    'next_due' => $event->nextRunDate()->format('Y-m-d H:i:s'),
                    'timezone' => $event->timezone ?? config('app.timezone'),
                ];
            })->take(10)->toArray();

        } catch (\Exception $e) {
            $this->scheduledTasks = [];
        }
    }

    // === Queue Functions ===
    public function loadQueueStats()
    {
        try {
            $this->queueStats = [
                'pending_jobs' => \DB::table('jobs')->whereNull('reserved_at')->count(),
                'processing_jobs' => \DB::table('jobs')->whereNotNull('reserved_at')->count(),
                'failed_jobs' => \DB::table('failed_jobs')->count(),
                'total_jobs_today' => \DB::table('jobs')->whereDate('created_at', today())->count(),
                'avg_processing_time' => '< 1s', // Placeholder
                'queue_workers_active' => $this->checkQueueWorkers(),
            ];
        } catch (\Exception $e) {
            $this->queueStats = [
                'pending_jobs' => 0,
                'processing_jobs' => 0,
                'failed_jobs' => 0,
                'total_jobs_today' => 0,
                'avg_processing_time' => 'N/A',
                'queue_workers_active' => false,
            ];
        }
    }

    // === Cron Functions ===
    public function loadCronStatus()
    {
        $cronStatus = shell_exec('systemctl is-active cron 2>/dev/null') ?: 'inactive';
        
        // Get last run from recovery log files (most reliable)
        $lastSchedulerRun = 'Never';
        $logFiles = [
            '/var/log/payment-health.log',
            '/var/log/payment-recovery.log'
        ];
        
        $latestTime = null;
        foreach ($logFiles as $logFile) {
            if (file_exists($logFile)) {
                $fileTime = filemtime($logFile);
                if ($latestTime === null || $fileTime > $latestTime) {
                    $latestTime = $fileTime;
                }
            }
        }
        
        if ($latestTime) {
            $lastSchedulerRun = date('Y-m-d H:i:s', $latestTime);
        }
        
        $this->cronStatus = [
            'cron_service' => trim($cronStatus) === 'active',
            'last_run' => $lastSchedulerRun,
            'next_run' => now()->addMinute()->second(0)->format('Y-m-d H:i:s'),
            'scheduler_frequency' => 'Every minute',
        ];
    }

    // === Action Functions ===
    public function scanForLostPayments()
    {
        $this->isScanning = true;
        
        try {
            Artisan::call('payments:recover-lost', [
                '--limit' => 50,
                '--min-age' => 10,
                '--max-age' => 2880,
                '--dry-run' => true
            ]);

            cache(['last_recovery_scan' => now()->format('Y-m-d H:i:s')], 3600);
            
            $this->loadLostPayments();
            $this->loadHealthStats();

            \Filament\Notifications\Notification::make()
                ->title('تم البحث عن الدفعات المفقودة')
                ->body('تم العثور على ' . count($this->lostPayments) . ' دفعة مفقودة')
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('خطأ في البحث')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->isScanning = false;
    }

    public function recoverAllFoundPayments()
    {
        $recoveredCount = 0;

        foreach ($this->lostPayments as $lostPayment) {
            try {
                $payment = $lostPayment['payment'];
                $gatewayStatus = $lostPayment['gateway_status'];

                $payment->update([
                    'status' => 'completed',
                    'confirmed_at' => now(),
                    'paid_at' => now(),
                    'gateway_response' => array_merge(
                        $payment->gateway_response ?? [],
                        $gatewayStatus['gateway_data'] ?? [],
                        ['recovered_at' => now(), 'recovery_method' => 'system_dashboard']
                    ),
                    'notes' => trim(($payment->notes ?? '') . "\nPayment recovered via System Dashboard at " . now())
                ]);

                ProcessPendingPayment::dispatch($payment);
                $recoveredCount++;

            } catch (\Exception $e) {
                // Log error but continue
            }
        }

        if ($recoveredCount > 0) {
            \Filament\Notifications\Notification::make()
                ->title('تم استعادة الدفعات')
                ->body("تم استعادة {$recoveredCount} دفعة بنجاح")
                ->success()
                ->send();

            $this->loadAllData();
        }
    }

    public function runEmergencyRecovery()
    {
        try {
            Artisan::call('payments:recover-lost', [
                '--limit' => 100,
                '--min-age' => 5,
                '--max-age' => 2880
            ]);

            Artisan::call('payments:monitor-health', ['--alert-threshold' => 1]);

            $this->loadAllData();

            \Filament\Notifications\Notification::make()
                ->title('تم تشغيل الاستعادة الطارئة')
                ->body('تم فحص واستعادة جميع الدفعات المفقودة')
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('فشل في الاستعادة الطارئة')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function generateHealthReport()
    {
        try {
            Artisan::call('payments:monitor-health', ['--send-email' => true]);

            \Filament\Notifications\Notification::make()
                ->title('تم إنشاء تقرير الصحة الشامل')
                ->body('تم إرسال التقرير عبر البريد الإلكتروني')
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('فشل في إنشاء التقرير')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function runScheduler()
    {
        try {
            Artisan::call('schedule:run');
            cache(['scheduler_last_run' => now()->format('Y-m-d H:i:s')], 3600);
            
            $this->loadCronStatus();
            $this->loadScheduledTasks();

            \Filament\Notifications\Notification::make()
                ->title('تم تشغيل المجدول')
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('فشل تشغيل المجدول')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearFailedJobs()
    {
        try {
            Artisan::call('queue:flush');
            $this->loadQueueStats();

            \Filament\Notifications\Notification::make()
                ->title('تم مسح المهام الفاشلة')
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('فشل في مسح المهام')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // === Helper Functions ===
    protected function checkPaymentInGateway(Payment $payment): array
    {
        if (strtolower($payment->payment_gateway) !== 'stripe') {
            return ['status' => 'unknown', 'message' => 'Gateway not supported'];
        }

        $paymentAccount = $payment->paymentAccount;
        if (!$paymentAccount || !isset($paymentAccount->credentials['secret_key'])) {
            return ['status' => 'error', 'message' => 'Credentials not found'];
        }

        $stripe = new StripeClient($paymentAccount->credentials['secret_key']);

        if ($payment->gateway_session_id && str_starts_with($payment->gateway_session_id, 'cs_')) {
            $session = $stripe->checkout->sessions->retrieve($payment->gateway_session_id);
            
            if ($session->status === 'complete' && $session->payment_status === 'paid') {
                return [
                    'status' => 'completed',
                    'message' => 'Checkout session completed and paid',
                    'gateway_data' => [
                        'session_id' => $session->id,
                        'status' => $session->status,
                        'payment_status' => $session->payment_status,
                        'amount_total' => $session->amount_total,
                    ]
                ];
            }
        }

        return ['status' => 'pending', 'message' => 'Not completed in gateway'];
    }

    protected function calculateSuccessRate(int $hours): float
    {
        $total = Payment::where('created_at', '>=', now()->subHours($hours))->count();
        if ($total === 0) return 100.0;
        
        $successful = Payment::where('created_at', '>=', now()->subHours($hours))
                            ->where('status', 'completed')
                            ->count();
        
        return round(($successful / $total) * 100, 2);
    }

    protected function getWebhookFailureCount(): int
    {
        return 0; // Placeholder
    }

    protected function checkQueueWorkers(): bool
    {
        $processes = shell_exec('ps aux | grep "queue:work" | grep -v grep');
        return !empty($processes);
    }
}