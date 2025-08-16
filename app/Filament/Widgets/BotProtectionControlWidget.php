<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\BotProtectionSettings;
use App\Models\BotDetection;

class BotProtectionControlWidget extends Widget
{
    protected static string $view = 'filament.widgets.bot-protection-control';

    protected static ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        $enabled = BotProtectionSettings::get('protection_enabled', true);
        $stats = BotDetection::getStats(24);
        
        return [
            'enabled' => $enabled,
            'stats' => $stats,
            'settings' => [
                'rate_limit_enabled' => BotProtectionSettings::get('rate_limit_enabled', true),
                'bot_detection_enabled' => BotProtectionSettings::get('bot_detection_enabled', true),
                'honeypot_enabled' => BotProtectionSettings::get('honeypot_enabled', true),
                'recaptcha_enabled' => BotProtectionSettings::get('recaptcha_enabled', false),
                'rate_limit_requests' => BotProtectionSettings::get('rate_limit_requests', 10),
                'min_form_time' => BotProtectionSettings::get('min_form_time', 3),
            ]
        ];
    }

    public function toggleProtection()
    {
        $enabled = BotProtectionSettings::get('protection_enabled', true);
        BotProtectionSettings::set('protection_enabled', !$enabled, 'boolean');
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Bot protection ' . (!$enabled ? 'enabled' : 'disabled')
        ]);
    }

    public function toggleFeature(string $feature)
    {
        $current = BotProtectionSettings::get($feature, true);
        BotProtectionSettings::set($feature, !$current, 'boolean');
        
        $featureName = str_replace('_', ' ', $feature);
        $this->dispatch('notify', [
            'type' => 'success', 
            'message' => ucfirst($featureName) . ' ' . (!$current ? 'enabled' : 'disabled')
        ]);
    }
}