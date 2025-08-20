<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MonitorPaymentHealth extends Command
{
    protected $signature = 'payments:monitor-health 
                           {--alert-threshold=10 : Number of stuck payments before sending alert}
                           {--send-email : Send email alerts}
                           {--webhook-url= : Webhook URL to send alerts}';

    protected $description = 'Monitor payment system health and send alerts for issues';

    public function handle()
    {
        $alertThreshold = $this->option('alert-threshold');
        $sendEmail = $this->option('send-email');
        $webhookUrl = $this->option('webhook-url');

        $this->info("🩺 Monitoring payment system health...");

        $healthReport = $this->generateHealthReport();
        
        $this->displayHealthReport($healthReport);
        
        // Check for critical issues
        $criticalIssues = $this->identifyCriticalIssues($healthReport, $alertThreshold);
        
        if (!empty($criticalIssues)) {
            $this->error("🚨 Critical issues detected!");
            foreach ($criticalIssues as $issue) {
                $this->error("  - " . $issue);
            }
            
            // Send alerts
            $this->sendAlerts($criticalIssues, $healthReport, $sendEmail, $webhookUrl);
        } else {
            $this->info("✅ Payment system health is good!");
        }

        return Command::SUCCESS;
    }

    protected function generateHealthReport(): array
    {
        $report = [];

        // Payment statistics
        $report['payments'] = [
            'total' => Payment::count(),
            'pending' => Payment::where('status', 'pending')->count(),
            'completed' => Payment::where('status', 'completed')->count(),
            'failed' => Payment::where('status', 'failed')->count(),
            'cancelled' => Payment::where('status', 'cancelled')->count(),
        ];

        // Stuck payments (pending for more than 1 hour)
        $report['stuck_payments'] = [
            'count' => Payment::where('status', 'pending')
                ->where('created_at', '<', now()->subHour())
                ->count(),
            'oldest' => Payment::where('status', 'pending')
                ->orderBy('created_at')
                ->first()?->created_at,
        ];

        // Recent payments (last 24 hours)
        $report['recent_activity'] = [
            'last_24h' => Payment::where('created_at', '>=', now()->subDay())->count(),
            'last_1h' => Payment::where('created_at', '>=', now()->subHour())->count(),
            'success_rate_24h' => $this->calculateSuccessRate(24),
            'success_rate_1h' => $this->calculateSuccessRate(1),
        ];

        // Gateway health
        $report['gateways'] = [
            'stripe' => $this->getGatewayHealth('stripe'),
            'paypal' => $this->getGatewayHealth('paypal'),
        ];

        // Queue health
        $report['queue'] = [
            'total_jobs' => \DB::table('jobs')->count(),
            'pending_jobs' => \DB::table('jobs')->whereNull('reserved_at')->count(),
            'processing_jobs' => \DB::table('jobs')->whereNotNull('reserved_at')->count(),
            'failed_jobs' => \DB::table('failed_jobs')->count(),
        ];

        // System resources
        $report['system'] = [
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'disk_free' => round(disk_free_space('/') / 1024 / 1024 / 1024, 2) . ' GB',
            'load_average' => sys_getloadavg()[0] ?? 'N/A',
        ];

        // Database health
        $report['database'] = [
            'connection' => $this->testDatabaseConnection(),
            'slow_queries' => $this->getSlowQueryCount(),
        ];

        return $report;
    }

    protected function calculateSuccessRate(int $hours): float
    {
        $total = Payment::where('created_at', '>=', now()->subHours($hours))->count();
        
        if ($total === 0) {
            return 100.0;
        }

        $successful = Payment::where('created_at', '>=', now()->subHours($hours))
                            ->where('status', 'completed')
                            ->count();

        return round(($successful / $total) * 100, 2);
    }

    protected function getGatewayHealth(string $gateway): array
    {
        $last24h = Payment::where('payment_gateway', $gateway)
                         ->where('created_at', '>=', now()->subDay())
                         ->count();

        $successful24h = Payment::where('payment_gateway', $gateway)
                               ->where('status', 'completed')
                               ->where('created_at', '>=', now()->subDay())
                               ->count();

        $failed24h = Payment::where('payment_gateway', $gateway)
                           ->where('status', 'failed')
                           ->where('created_at', '>=', now()->subDay())
                           ->count();

        $successRate = $last24h > 0 ? round(($successful24h / $last24h) * 100, 2) : 100.0;

        return [
            'total_24h' => $last24h,
            'successful_24h' => $successful24h,
            'failed_24h' => $failed24h,
            'success_rate' => $successRate,
            'avg_processing_time' => $this->getAverageProcessingTime($gateway),
        ];
    }

    protected function getAverageProcessingTime(string $gateway): float
    {
        $payments = Payment::where('payment_gateway', $gateway)
                          ->where('status', 'completed')
                          ->whereNotNull('confirmed_at')
                          ->where('created_at', '>=', now()->subDay())
                          ->get();

        if ($payments->isEmpty()) {
            return 0.0;
        }

        $totalTime = 0;
        $count = 0;

        foreach ($payments as $payment) {
            if ($payment->confirmed_at && $payment->created_at) {
                $totalTime += $payment->confirmed_at->diffInSeconds($payment->created_at);
                $count++;
            }
        }

        return $count > 0 ? round($totalTime / $count, 2) : 0.0;
    }

    protected function testDatabaseConnection(): bool
    {
        try {
            \DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getSlowQueryCount(): int
    {
        // This is a simplified implementation
        // In production, you'd want to check actual slow query logs
        return 0;
    }

    protected function displayHealthReport(array $report): void
    {
        $this->info("📊 Payment System Health Report");
        $this->line("Generated at: " . now()->format('Y-m-d H:i:s'));
        $this->newLine();

        // Payment statistics
        $this->info("💳 Payments:");
        $this->line("  Total: {$report['payments']['total']}");
        $this->line("  Pending: {$report['payments']['pending']}");
        $this->line("  Completed: {$report['payments']['completed']}");
        $this->line("  Failed: {$report['payments']['failed']}");
        $this->newLine();

        // Stuck payments
        $this->info("⏰ Stuck Payments:");
        $this->line("  Count: {$report['stuck_payments']['count']}");
        if ($report['stuck_payments']['oldest']) {
            $this->line("  Oldest: {$report['stuck_payments']['oldest']->diffForHumans()}");
        }
        $this->newLine();

        // Recent activity
        $this->info("📈 Recent Activity:");
        $this->line("  Last 24h: {$report['recent_activity']['last_24h']} payments");
        $this->line("  Last 1h: {$report['recent_activity']['last_1h']} payments");
        $this->line("  Success rate (24h): {$report['recent_activity']['success_rate_24h']}%");
        $this->line("  Success rate (1h): {$report['recent_activity']['success_rate_1h']}%");
        $this->newLine();

        // Gateway health
        $this->info("🌐 Gateway Health:");
        foreach ($report['gateways'] as $gateway => $health) {
            $this->line("  {$gateway}:");
            $this->line("    Payments (24h): {$health['total_24h']}");
            $this->line("    Success rate: {$health['success_rate']}%");
            $this->line("    Avg processing time: {$health['avg_processing_time']}s");
        }
        $this->newLine();

        // Queue health
        $this->info("🔄 Queue Health:");
        $this->line("  Total jobs: {$report['queue']['total_jobs']}");
        $this->line("  Pending: {$report['queue']['pending_jobs']}");
        $this->line("  Processing: {$report['queue']['processing_jobs']}");
        $this->line("  Failed: {$report['queue']['failed_jobs']}");
        $this->newLine();
    }

    protected function identifyCriticalIssues(array $report, int $threshold): array
    {
        $issues = [];

        // Check for stuck payments
        if ($report['stuck_payments']['count'] >= $threshold) {
            $issues[] = "High number of stuck payments: {$report['stuck_payments']['count']} (threshold: {$threshold})";
        }

        // Check success rates
        if ($report['recent_activity']['success_rate_24h'] < 90) {
            $issues[] = "Low success rate in last 24h: {$report['recent_activity']['success_rate_24h']}%";
        }

        if ($report['recent_activity']['success_rate_1h'] < 80) {
            $issues[] = "Very low success rate in last hour: {$report['recent_activity']['success_rate_1h']}%";
        }

        // Check gateway health
        foreach ($report['gateways'] as $gateway => $health) {
            if ($health['success_rate'] < 85) {
                $issues[] = "Low {$gateway} success rate: {$health['success_rate']}%";
            }
        }

        // Check queue health
        if ($report['queue']['failed_jobs'] > 100) {
            $issues[] = "High number of failed jobs: {$report['queue']['failed_jobs']}";
        }

        if ($report['queue']['pending_jobs'] > 1000) {
            $issues[] = "High number of pending jobs: {$report['queue']['pending_jobs']}";
        }

        // Check database
        if (!$report['database']['connection']) {
            $issues[] = "Database connection failed";
        }

        return $issues;
    }

    protected function sendAlerts(array $issues, array $report, bool $sendEmail, ?string $webhookUrl): void
    {
        $message = "🚨 Payment System Alert\n\n";
        $message .= "Critical issues detected:\n";
        foreach ($issues as $issue) {
            $message .= "- {$issue}\n";
        }
        $message .= "\nGenerated at: " . now()->format('Y-m-d H:i:s');

        // Log the alert
        Log::critical('Payment system health alert', [
            'issues' => $issues,
            'report' => $report
        ]);

        // Send email alert
        if ($sendEmail) {
            try {
                $this->sendEmailAlert($message, $issues, $report);
                $this->info("📧 Email alert sent");
            } catch (\Exception $e) {
                $this->error("Failed to send email alert: " . $e->getMessage());
            }
        }

        // Send webhook alert
        if ($webhookUrl) {
            try {
                $this->sendWebhookAlert($webhookUrl, $message, $issues, $report);
                $this->info("🔗 Webhook alert sent");
            } catch (\Exception $e) {
                $this->error("Failed to send webhook alert: " . $e->getMessage());
            }
        }
    }

    protected function sendEmailAlert(string $message, array $issues, array $report): void
    {
        // Implement email sending logic
        // This would typically use Laravel's mail system
    }

    protected function sendWebhookAlert(string $url, string $message, array $issues, array $report): void
    {
        $payload = [
            'text' => $message,
            'issues' => $issues,
            'report' => $report,
            'timestamp' => now()->toISOString(),
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}