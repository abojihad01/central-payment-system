<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
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
        return [
            'token' => 'required|string',
            'payment_method' => 'required|in:stripe,paypal',
            'email' => [
                'required',
                'email:rfc,dns',
                'max:254',
                'regex:/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/'
            ],
            'phone' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{6,14}$/',
                'min:8',
                'max:16'
            ]
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Payment token is required.',
            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in' => 'Invalid payment method selected.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Email address must not exceed 254 characters.',
            'email.regex' => 'Please enter a valid email address format.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Phone number must be in international format (e.g., +1234567890).',
            'phone.min' => 'Phone number must be at least 8 characters.',
            'phone.max' => 'Phone number must not exceed 16 characters.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => trim(strtolower($this->email ?? '')),
            'phone' => $this->sanitizePhone($this->phone ?? '')
        ]);
    }

    /**
     * Sanitize phone number by removing all non-digit characters except +
     */
    private function sanitizePhone(string $phone): string
    {
        // Remove all characters except digits and + at the beginning
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        
        // Ensure only one + at the beginning
        if (strpos($cleaned, '+') !== false) {
            $cleaned = '+' . str_replace('+', '', $cleaned);
        }
        
        return $cleaned;
    }

    /**
     * Get validated email address
     */
    public function getValidatedEmail(): string
    {
        return $this->validated()['email'];
    }

    /**
     * Get validated phone number
     */
    public function getValidatedPhone(): string
    {
        return $this->validated()['phone'];
    }
}