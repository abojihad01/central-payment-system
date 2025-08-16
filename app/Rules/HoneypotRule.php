<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Models\BotProtectionSettings;
use App\Models\BotDetection;

class HoneypotRule implements Rule
{
    protected $field;

    public function __construct($field = 'website_url')
    {
        $this->field = $field;
    }

    /**
     * Determine if the validation rule passes.
     */
    public function passes($attribute, $value)
    {
        // Check if honeypot protection is enabled
        if (!BotProtectionSettings::get('honeypot_enabled', true)) {
            return true;
        }

        // Honeypot field should be empty
        if (!empty($value)) {
            $this->logDetection('honeypot', "Honeypot field '{$attribute}' was filled", 90);
            
            Log::warning('Honeypot field filled - bot detected', [
                'field' => $attribute,
                'value' => $value,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);
            
            return false;
        }

        // Check form submission timing
        $formStartTime = request()->input('form_start_time');
        if ($formStartTime) {
            $minTime = BotProtectionSettings::get('min_form_time', 3);
            $timeTaken = time() - $formStartTime;
            
            if ($timeTaken < $minTime) {
                $this->logDetection('timing', "Form submitted too quickly: {$timeTaken}s < {$minTime}s", 70);
                
                Log::warning('Form submitted too quickly - bot detected', [
                    'time_taken' => $timeTaken,
                    'min_time_required' => $minTime,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent()
                ]);
                
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     */
    public function message()
    {
        return 'Invalid form submission detected.';
    }

    /**
     * Log detection to database
     */
    private function logDetection(string $type, string $details, int $riskScore): void
    {
        // Check if logging is enabled
        if (!BotProtectionSettings::get('log_detections', true)) {
            return;
        }

        try {
            BotDetection::logDetection([
                'type' => $type,
                'details' => $details,
                'risk_score' => $riskScore,
                'is_blocked' => true
            ]);
        } catch (\Exception $e) {
            // Fail silently to avoid breaking the request
            Log::error('Failed to log honeypot detection', ['error' => $e->getMessage()]);
        }
    }
}