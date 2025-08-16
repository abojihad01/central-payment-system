<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BotProtectionSettings;

class ToggleBotProtectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:toggle {--status : Show current status only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Toggle bot protection on/off or show current status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentStatus = BotProtectionSettings::get('protection_enabled', true);
        
        if ($this->option('status')) {
            $this->displayStatus($currentStatus);
            return 0;
        }

        $this->displayStatus($currentStatus);
        
        if ($this->confirm('Do you want to ' . ($currentStatus ? 'disable' : 'enable') . ' bot protection?')) {
            BotProtectionSettings::set('protection_enabled', !$currentStatus, 'boolean');
            
            $newStatus = !$currentStatus;
            $this->newLine();
            
            if ($newStatus) {
                $this->info('✅ Bot protection ENABLED');
                $this->line('Your site is now protected against automated attacks.');
            } else {
                $this->error('⚠️  Bot protection DISABLED');
                $this->warn('Your site is now vulnerable to automated attacks!');
            }
            
            $this->newLine();
            $this->displayFeatureStatus();
        } else {
            $this->info('No changes made.');
        }
        
        return 0;
    }
    
    private function displayStatus($enabled)
    {
        $this->line('');
        $this->line('🛡️  Bot Protection Status: ' . ($enabled ? '<fg=green>ENABLED</>' : '<fg=red>DISABLED</>'));
        $this->line('');
    }
    
    private function displayFeatureStatus()
    {
        $features = [
            'rate_limit_enabled' => 'Rate Limiting',
            'bot_detection_enabled' => 'Bot Detection', 
            'honeypot_enabled' => 'Honeypot Protection',
            'recaptcha_enabled' => 'reCAPTCHA'
        ];
        
        $this->line('Individual Feature Status:');
        foreach ($features as $key => $name) {
            $status = BotProtectionSettings::get($key, true);
            $icon = $status ? '✅' : '❌';
            $color = $status ? 'green' : 'red';
            $this->line("  {$icon} {$name}: <fg={$color}>" . ($status ? 'ENABLED' : 'DISABLED') . '</>');
        }
        
        $this->line('');
        $this->line('Use <fg=yellow>php artisan bot:monitor</> to view recent activity.');
        $this->line('Configure settings at: <fg=blue>/admin/bot-protection-settings</>');
    }
}
