<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payment;
use Carbon\Carbon;

class PendingPaymentsReport extends Command
{
    protected $signature = 'payments:report 
                            {--email= : Email address to send report to}
                            {--format=table : Output format (table, json, csv)}';

    protected $description = 'Generate a report of pending payments statistics';

    public function handle()
    {
        $this->info("📊 Generating Pending Payments Report...");
        
        $stats = $this->gatherStatistics();
        $format = $this->option('format');
        
        switch ($format) {
            case 'json':
                $this->outputJson($stats);
                break;
            case 'csv':
                $this->outputCsv($stats);
                break;
            default:
                $this->outputTable($stats);
                break;
        }
        
        if ($this->option('email')) {
            $this->sendEmailReport($stats);
        }
        
        return 0;
    }
    
    private function gatherStatistics(): array
    {
        $now = Carbon::now();
        
        return [
            'total_pending' => Payment::where('status', 'pending')->count(),
            'pending_last_hour' => Payment::where('status', 'pending')
                ->where('created_at', '>=', $now->subHour())
                ->count(),
            'pending_last_24h' => Payment::where('status', 'pending')
                ->where('created_at', '>=', $now->subDay())
                ->count(),
            'pending_older_24h' => Payment::where('status', 'pending')
                ->where('created_at', '<', $now->subDay())
                ->count(),
            'pending_older_7d' => Payment::where('status', 'pending')
                ->where('created_at', '<', $now->subDays(7))
                ->count(),
            'total_amount_pending' => Payment::where('status', 'pending')->sum('amount'),
            'avg_pending_age_hours' => $this->calculateAveragePendingAge(),
            'by_gateway' => Payment::where('status', 'pending')
                ->selectRaw('payment_gateway, COUNT(*) as count, SUM(amount) as total_amount')
                ->groupBy('payment_gateway')
                ->get()
                ->keyBy('payment_gateway')
                ->toArray(),
            'recent_pending' => Payment::where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['id', 'amount', 'currency', 'customer_email', 'created_at'])
                ->toArray(),
            'generated_at' => $now->toISOString()
        ];
    }
    
    private function calculateAveragePendingAge(): float
    {
        $pendingPayments = Payment::where('status', 'pending')
            ->get(['created_at']);
            
        if ($pendingPayments->isEmpty()) {
            return 0;
        }
        
        $totalHours = 0;
        $now = Carbon::now();
        
        foreach ($pendingPayments as $payment) {
            $totalHours += $now->diffInHours($payment->created_at);
        }
        
        return round($totalHours / $pendingPayments->count(), 2);
    }
    
    private function outputTable(array $stats): void
    {
        $this->info("📈 Pending Payments Statistics Report");
        $this->newLine();
        
        // Summary table
        $summaryData = [
            ['Metric', 'Value'],
            ['Total Pending', $stats['total_pending']],
            ['Last Hour', $stats['pending_last_hour']],
            ['Last 24 Hours', $stats['pending_last_24h']],
            ['Older than 24h', $stats['pending_older_24h']],
            ['Older than 7 days', $stats['pending_older_7d']],
            ['Total Amount Pending', '$' . number_format($stats['total_amount_pending'], 2)],
            ['Average Age (hours)', $stats['avg_pending_age_hours']],
        ];
        
        $this->table($summaryData[0], array_slice($summaryData, 1));
        
        // By gateway
        if (!empty($stats['by_gateway'])) {
            $this->newLine();
            $this->info("📊 By Payment Gateway:");
            
            $gatewayData = [['Gateway', 'Count', 'Total Amount']];
            foreach ($stats['by_gateway'] as $gateway => $data) {
                $gatewayData[] = [
                    $gateway ?: 'Unknown',
                    $data['count'],
                    '$' . number_format($data['total_amount'], 2)
                ];
            }
            
            $this->table($gatewayData[0], array_slice($gatewayData, 1));
        }
        
        // Recent pending
        if (!empty($stats['recent_pending'])) {
            $this->newLine();
            $this->info("🕐 Most Recent Pending Payments (Last 10):");
            
            $recentData = [['ID', 'Amount', 'Email', 'Age']];
            foreach ($stats['recent_pending'] as $payment) {
                $age = Carbon::parse($payment['created_at'])->diffForHumans();
                $recentData[] = [
                    $payment['id'],
                    $payment['amount'] . ' ' . $payment['currency'],
                    $payment['customer_email'],
                    $age
                ];
            }
            
            $this->table($recentData[0], array_slice($recentData, 1));
        }
        
        $this->newLine();
        $this->comment("Report generated at: " . $stats['generated_at']);
    }
    
    private function outputJson(array $stats): void
    {
        $this->line(json_encode($stats, JSON_PRETTY_PRINT));
    }
    
    private function outputCsv(array $stats): void
    {
        // Output CSV format to stdout
        $this->line("Metric,Value");
        $this->line("Total Pending,{$stats['total_pending']}");
        $this->line("Last Hour,{$stats['pending_last_hour']}");
        $this->line("Last 24 Hours,{$stats['pending_last_24h']}");
        $this->line("Older than 24h,{$stats['pending_older_24h']}");
        $this->line("Older than 7 days,{$stats['pending_older_7d']}");
        $this->line("Total Amount Pending,{$stats['total_amount_pending']}");
        $this->line("Average Age Hours,{$stats['avg_pending_age_hours']}");
    }
    
    private function sendEmailReport(array $stats): void
    {
        // Here you can implement email sending logic
        $this->info("📧 Email reporting not implemented yet. Report ready for: " . $this->option('email'));
        $this->comment("You can implement email sending using Laravel's Mail facade here.");
    }
}
