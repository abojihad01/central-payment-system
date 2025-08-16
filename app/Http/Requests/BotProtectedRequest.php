<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\HoneypotRule;
use App\Models\BotProtectionSettings;

class BotProtectedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [];
        
        // Add honeypot validation if enabled
        if (BotProtectionSettings::get('honeypot_enabled', true)) {
            $rules['website_url'] = [new HoneypotRule('website_url')];
            $rules['email_confirmation'] = [new HoneypotRule('email_confirmation')];
        }
        
        return $rules;
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'website_url.*' => 'Invalid form submission detected.',
            'email_confirmation.*' => 'Invalid form submission detected.',
        ];
    }
    
    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Log successful form submission if needed
        logger('Form submission passed bot protection validation', [
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'url' => $this->fullUrl()
        ]);
    }
}