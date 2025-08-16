<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\FraudRule;
use App\Models\Blacklist;

return new class extends Migration
{
    public function up(): void
    {
        // High Amount Rule
        FraudRule::create([
            'name' => 'High Amount Transaction',
            'description' => 'Flag transactions over $1000 for review',
            'is_active' => true,
            'priority' => 90,
            'conditions' => [
                [
                    'field' => 'amount',
                    'operator' => '>',
                    'value' => 1000
                ]
            ],
            'action' => 'review',
            'risk_score_impact' => 20
        ]);

        // Multiple Failed Payments Rule
        FraudRule::create([
            'name' => 'Multiple Failed Payments',
            'description' => 'Block customers with high failure rate',
            'is_active' => true,
            'priority' => 95,
            'conditions' => [
                [
                    'field' => 'risk_profile.failed_payments',
                    'operator' => '>',
                    'value' => 5
                ],
                [
                    'field' => 'risk_profile.successful_payments',
                    'operator' => '<',
                    'value' => 2
                ]
            ],
            'action' => 'block',
            'risk_score_impact' => 40
        ]);

        // Suspicious Country Rule
        FraudRule::create([
            'name' => 'High Risk Countries',
            'description' => 'Review payments from high-risk countries',
            'is_active' => true,
            'priority' => 70,
            'conditions' => [
                [
                    'field' => 'country_code',
                    'operator' => 'in',
                    'value' => ['NG', 'ID', 'PK', 'BD', 'EG'] // High fraud countries
                ]
            ],
            'action' => 'review',
            'risk_score_impact' => 15
        ]);

        // Very High Amount Rule
        FraudRule::create([
            'name' => 'Very High Amount Transaction',
            'description' => 'Block transactions over $5000',
            'is_active' => true,
            'priority' => 100,
            'conditions' => [
                [
                    'field' => 'amount',
                    'operator' => '>',
                    'value' => 5000
                ]
            ],
            'action' => 'block',
            'risk_score_impact' => 50
        ]);

        // Disposable Email Rule
        FraudRule::create([
            'name' => 'Disposable Email Domains',
            'description' => 'Flag disposable email addresses',
            'is_active' => true,
            'priority' => 60,
            'conditions' => [
                [
                    'field' => 'email',
                    'operator' => 'regex',
                    'value' => '/@(10minutemail|tempmail|guerrillamail|mailinator|yopmail)\./'
                ]
            ],
            'action' => 'review',
            'risk_score_impact' => 25
        ]);

        // New Customer High Amount Rule
        FraudRule::create([
            'name' => 'New Customer Large Purchase',
            'description' => 'Review large purchases from new customers',
            'is_active' => true,
            'priority' => 80,
            'conditions' => [
                [
                    'field' => 'risk_profile.successful_payments',
                    'operator' => '=',
                    'value' => 0
                ],
                [
                    'field' => 'amount',
                    'operator' => '>',
                    'value' => 500
                ]
            ],
            'action' => 'review',
            'risk_score_impact' => 30
        ]);

        // Velocity Rule - Too Many Attempts
        FraudRule::create([
            'name' => 'High Velocity Transactions',
            'description' => 'Block customers making too many payment attempts',
            'is_active' => true,
            'priority' => 85,
            'conditions' => [
                [
                    'field' => 'risk_profile.failed_payments',
                    'operator' => '>',
                    'value' => 3
                ]
            ],
            'action' => 'review',
            'risk_score_impact' => 35
        ]);

        // Common fraud blacklist entries
        $commonFraudEmails = [
            'test@example.com',
            'fraud@test.com',
            'scammer@gmail.com'
        ];

        foreach ($commonFraudEmails as $email) {
            Blacklist::create([
                'type' => 'email',
                'value' => $email,
                'reason' => 'Known fraud email address',
                'added_by' => 'system'
            ]);
        }

        // Block known fraud IP ranges (examples)
        $fraudIPs = [
            '192.168.1.100', // Example fraud IP
            '10.0.0.50'      // Another example
        ];

        foreach ($fraudIPs as $ip) {
            Blacklist::create([
                'type' => 'ip',
                'value' => $ip,
                'reason' => 'Known fraud IP address',
                'added_by' => 'system'
            ]);
        }

        // Block high-risk countries (optional - can be controversial)
        $highRiskCountries = ['XX']; // Placeholder - don't block real countries without good reason

        foreach ($highRiskCountries as $country) {
            Blacklist::create([
                'type' => 'country',
                'value' => $country,
                'reason' => 'High fraud rate country',
                'added_by' => 'system'
            ]);
        }
    }

    public function down(): void
    {
        FraudRule::truncate();
        Blacklist::where('added_by', 'system')->delete();
    }
};