<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MonitorBotsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:monitor {--hours=24 : Hours to look back} {--export : Export results to file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor and analyze bot activity from logs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $export = $this->option('export');
        
        $this->info("Analyzing bot activity for the last {$hours} hours...");
        
        $stats = $this->analyzeBotActivity($hours);
        
        $this->displayStats($stats);
        
        if ($export) {
            $this->exportStats($stats);
        }
        
        return 0;
    }
    
    private function analyzeBotActivity($hours)
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!file_exists($logFile)) {
            $this->error('Log file not found');
            return [];
        }
        
        $cutoffTime = Carbon::now()->subHours($hours);
        $botDetections = [];
        $blockedIps = [];
        $userAgents = [];
        $honeypotTriggers = [];
        $rateLimitHits = [];
        
        $handle = fopen($logFile, 'r');
        if (!$handle) {
            $this->error('Cannot read log file');
            return [];
        }
        
        while (($line = fgets($handle)) !== false) {
            if (strpos($line, 'Bot detected') !== false || 
                strpos($line, 'Honeypot field filled') !== false ||
                strpos($line, 'Form submitted too quickly') !== false ||
                strpos($line, 'Rate limit exceeded') !== false) {
                
                $data = $this->parseLogLine($line);
                if ($data && $data['timestamp'] > $cutoffTime) {
                    
                    if (strpos($line, 'Bot detected') !== false) {
                        $botDetections[] = $data;
                        $blockedIps[$data['ip']] = ($blockedIps[$data['ip']] ?? 0) + 1;
                        $userAgents[$data['user_agent']] = ($userAgents[$data['user_agent']] ?? 0) + 1;
                    }
                    
                    if (strpos($line, 'Honeypot') !== false || strpos($line, 'Form submitted too quickly') !== false) {
                        $honeypotTriggers[] = $data;
                    }
                    
                    if (strpos($line, 'Rate limit exceeded') !== false) {
                        $rateLimitHits[] = $data;
                    }
                }
            }
        }
        
        fclose($handle);
        
        return [
            'total_bot_detections' => count($botDetections),
            'total_honeypot_triggers' => count($honeypotTriggers),
            'total_rate_limit_hits' => count($rateLimitHits),
            'unique_blocked_ips' => count($blockedIps),
            'top_blocked_ips' => array_slice(arsort($blockedIps) ? $blockedIps : [], 0, 10, true),
            'top_user_agents' => array_slice(arsort($userAgents) ? $userAgents : [], 0, 10, true),
            'bot_detections' => $botDetections,
            'honeypot_triggers' => $honeypotTriggers,
            'rate_limit_hits' => $rateLimitHits,
            'analysis_period' => $hours,
            'cutoff_time' => $cutoffTime
        ];
    }
    
    private function parseLogLine($line)
    {
        // Extract timestamp
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $timestamp = Carbon::createFromFormat('Y-m-d H:i:s', $matches[1]);
        } else {
            return null;
        }
        
        // Extract IP
        $ip = 'unknown';
        if (preg_match('/"ip":"([^"]+)"/', $line, $matches)) {
            $ip = $matches[1];
        }
        
        // Extract User Agent
        $userAgent = 'unknown';
        if (preg_match('/"user_agent":"([^"]+)"/', $line, $matches)) {
            $userAgent = $matches[1];
        }
        
        return [
            'timestamp' => $timestamp,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'raw_line' => $line
        ];
    }
    
    private function displayStats($stats)
    {
        $this->line('');
        $this->info('=== Bot Protection Statistics ===');
        $this->line("Analysis Period: Last {$stats['analysis_period']} hours");
        $this->line("Cutoff Time: {$stats['cutoff_time']->format('Y-m-d H:i:s')}");
        $this->line('');
        
        $this->info('=== Summary ===');
        $this->line("Total Bot Detections: {$stats['total_bot_detections']}");
        $this->line("Total Honeypot Triggers: {$stats['total_honeypot_triggers']}");
        $this->line("Total Rate Limit Hits: {$stats['total_rate_limit_hits']}");
        $this->line("Unique Blocked IPs: {$stats['unique_blocked_ips']}");
        $this->line('');
        
        if (!empty($stats['top_blocked_ips'])) {
            $this->info('=== Top Blocked IPs ===');
            foreach ($stats['top_blocked_ips'] as $ip => $count) {
                $this->line("{$ip}: {$count} detections");
            }
            $this->line('');
        }
        
        if (!empty($stats['top_user_agents'])) {
            $this->info('=== Top Bot User Agents ===');
            foreach ($stats['top_user_agents'] as $ua => $count) {
                $this->line("{$ua}: {$count} detections");
            }
            $this->line('');
        }
        
        if ($stats['total_bot_detections'] > 0) {
            $this->warn('Recent bot activity detected! Consider reviewing your protection settings.');
        } else {
            $this->info('No bot activity detected in the specified period.');
        }
    }
    
    private function exportStats($stats)
    {
        $filename = storage_path('logs/bot-analysis-' . now()->format('Y-m-d-H-i-s') . '.json');
        
        $exportData = [
            'generated_at' => now()->toISOString(),
            'analysis_period_hours' => $stats['analysis_period'],
            'statistics' => $stats
        ];
        
        if (file_put_contents($filename, json_encode($exportData, JSON_PRETTY_PRINT))) {
            $this->info("Stats exported to: {$filename}");
        } else {
            $this->error("Failed to export stats to file");
        }
    }
}
