<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Services\RecaptchaService;
use App\Models\BotProtectionSettings;

class RecaptchaConfigWidget extends Widget
{
    protected static string $view = 'filament.widgets.recaptcha-config';

    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        $recaptchaService = app(RecaptchaService::class);
        $config = $recaptchaService->getConfig();
        
        return [
            'config' => $config,
            'siteKey' => config('services.recaptcha.site_key'),
            'secretKey' => config('services.recaptcha.secret_key'),
            'isConfigured' => $recaptchaService->isConfigured(),
            'settings' => [
                'enabled' => BotProtectionSettings::get('recaptcha_enabled', false),
                'threshold' => BotProtectionSettings::get('recaptcha_threshold', 0.5),
                'version' => BotProtectionSettings::get('recaptcha_version', 'v3'),
                'actions' => BotProtectionSettings::get('recaptcha_actions', [])
            ]
        ];
    }

    public function testRecaptcha()
    {
        $recaptchaService = app(RecaptchaService::class);
        
        if (!$recaptchaService->isConfigured()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'reCAPTCHA is not configured. Please add your site and secret keys.'
            ]);
            return;
        }
        
        // Test with a dummy token (will fail but shows connection works)
        $result = $recaptchaService->verify('test-token', 'test');
        
        if (isset($result['error_codes'])) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'reCAPTCHA API connection successful (test token rejected as expected)'
            ]);
        } else {
            $this->dispatch('notify', [
                'type' => 'error', 
                'message' => 'reCAPTCHA API connection failed'
            ]);
        }
    }

    public function updateThreshold($threshold)
    {
        $threshold = (float) $threshold;
        
        if ($threshold < 0 || $threshold > 1) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Threshold must be between 0.0 and 1.0'
            ]);
            return;
        }
        
        BotProtectionSettings::set('recaptcha_threshold', $threshold, 'string');
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "reCAPTCHA threshold updated to {$threshold}"
        ]);
    }
}