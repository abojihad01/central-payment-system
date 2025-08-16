<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TaxCalculationService
{
    private array $taxRates;

    public function __construct()
    {
        // Default tax rates by country - in production, integrate with Avalara/TaxJar
        $this->taxRates = [
            'US' => ['rate' => 8.25, 'name' => 'Sales Tax'],
            'CA' => ['rate' => 13.00, 'name' => 'HST'],
            'GB' => ['rate' => 20.00, 'name' => 'VAT'],
            'DE' => ['rate' => 19.00, 'name' => 'VAT'],
            'FR' => ['rate' => 20.00, 'name' => 'VAT'],
            'ES' => ['rate' => 21.00, 'name' => 'IVA'],
            'IT' => ['rate' => 22.00, 'name' => 'IVA'],
            'AU' => ['rate' => 10.00, 'name' => 'GST'],
            'SE' => ['rate' => 25.00, 'name' => 'VAT'],
            'NO' => ['rate' => 25.00, 'name' => 'VAT'],
        ];
    }

    /**
     * Calculate tax for a payment amount
     */
    public function calculateTax(float $amount, string $countryCode, ?string $businessType = null): array
    {
        $cacheKey = "tax_calc_{$countryCode}_{$amount}_{$businessType}";
        
        return Cache::remember($cacheKey, 3600, function () use ($amount, $countryCode, $businessType) {
            // Get tax rate for country
            $taxInfo = $this->getTaxRate($countryCode, $businessType);
            
            if (!$taxInfo) {
                return [
                    'subtotal' => $amount,
                    'tax_amount' => 0,
                    'tax_rate' => 0,
                    'tax_name' => 'No Tax',
                    'total' => $amount,
                    'tax_exempt' => true
                ];
            }

            $taxAmount = $amount * ($taxInfo['rate'] / 100);
            
            return [
                'subtotal' => round($amount, 2),
                'tax_amount' => round($taxAmount, 2),
                'tax_rate' => $taxInfo['rate'],
                'tax_name' => $taxInfo['name'],
                'total' => round($amount + $taxAmount, 2),
                'tax_exempt' => false,
                'country_code' => $countryCode
            ];
        });
    }

    /**
     * Validate tax exemption
     */
    public function validateTaxExemption(string $exemptionId, string $countryCode): array
    {
        // In production, integrate with tax authority APIs
        $exemptionPatterns = [
            'US' => '/^[0-9]{2}-[0-9]{7}$/', // Example US tax exemption format
            'GB' => '/^GB[0-9]{9}$/',        // UK VAT number format
            'DE' => '/^DE[0-9]{9}$/',        // German VAT number format
        ];

        $isValid = false;
        if (isset($exemptionPatterns[$countryCode])) {
            $isValid = preg_match($exemptionPatterns[$countryCode], $exemptionId);
        }

        return [
            'exemption_id' => $exemptionId,
            'country_code' => $countryCode,
            'is_valid' => (bool) $isValid,
            'validated_at' => now()->toISOString()
        ];
    }

    /**
     * Generate tax report for accounting
     */
    public function generateTaxReport(string $startDate, string $endDate, ?string $countryCode = null): array
    {
        $query = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($countryCode) {
            $query->whereJsonContains('tax_data->country_code', $countryCode);
        }

        $payments = $query->get();
        $report = [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'total_transactions' => $payments->count(),
            'total_gross_amount' => 0,
            'total_tax_collected' => 0,
            'total_net_amount' => 0,
            'by_country' => [],
            'by_tax_rate' => [],
            'transactions' => []
        ];

        foreach ($payments as $payment) {
            $taxData = $payment->tax_data ?? [];
            $country = $taxData['country_code'] ?? 'UNKNOWN';
            $taxAmount = $taxData['tax_amount'] ?? 0;
            $taxRate = $taxData['tax_rate'] ?? 0;

            // Aggregate totals
            $report['total_gross_amount'] += $payment->amount;
            $report['total_tax_collected'] += $taxAmount;
            $report['total_net_amount'] += ($payment->amount - $taxAmount);

            // By country
            if (!isset($report['by_country'][$country])) {
                $report['by_country'][$country] = [
                    'transactions' => 0,
                    'gross_amount' => 0,
                    'tax_collected' => 0
                ];
            }
            $report['by_country'][$country]['transactions']++;
            $report['by_country'][$country]['gross_amount'] += $payment->amount;
            $report['by_country'][$country]['tax_collected'] += $taxAmount;

            // By tax rate
            $rateKey = (string) $taxRate;
            if (!isset($report['by_tax_rate'][$rateKey])) {
                $report['by_tax_rate'][$rateKey] = [
                    'transactions' => 0,
                    'gross_amount' => 0,
                    'tax_collected' => 0
                ];
            }
            $report['by_tax_rate'][$rateKey]['transactions']++;
            $report['by_tax_rate'][$rateKey]['gross_amount'] += $payment->amount;
            $report['by_tax_rate'][$rateKey]['tax_collected'] += $taxAmount;

            // Transaction details
            $report['transactions'][] = [
                'payment_id' => $payment->id,
                'date' => $payment->created_at->toDateString(),
                'amount' => $payment->amount,
                'tax_amount' => $taxAmount,
                'tax_rate' => $taxRate,
                'country' => $country,
                'customer_email' => $payment->customer_email
            ];
        }

        // Round final totals
        $report['total_gross_amount'] = round($report['total_gross_amount'], 2);
        $report['total_tax_collected'] = round($report['total_tax_collected'], 2);
        $report['total_net_amount'] = round($report['total_net_amount'], 2);

        return $report;
    }

    /**
     * Integration with Avalara AvaTax (example)
     */
    public function calculateTaxWithAvalara(array $transaction): array
    {
        // This would integrate with Avalara's API in production
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.avalara.api_key'),
                'Content-Type' => 'application/json'
            ])->post('https://sandbox-rest.avatax.com/api/v2/transactions/create', [
                'companyCode' => config('services.avalara.company_code'),
                'type' => 'SalesInvoice',
                'customerCode' => $transaction['customer_code'],
                'date' => now()->toDateString(),
                'lines' => [
                    [
                        'number' => '1',
                        'amount' => $transaction['amount'],
                        'description' => $transaction['description'] ?? 'Payment'
                    ]
                ],
                'addresses' => [
                    'shipTo' => [
                        'line1' => $transaction['address']['line1'] ?? '',
                        'city' => $transaction['address']['city'] ?? '',
                        'region' => $transaction['address']['region'] ?? '',
                        'country' => $transaction['address']['country'] ?? '',
                        'postalCode' => $transaction['address']['postal_code'] ?? ''
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'subtotal' => $data['totalAmount'] - $data['totalTax'],
                    'tax_amount' => $data['totalTax'],
                    'total' => $data['totalAmount'],
                    'tax_details' => $data['lines'][0]['details'] ?? []
                ];
            }
        } catch (\Exception $e) {
            Log::error('Avalara tax calculation failed: ' . $e->getMessage());
        }

        // Fallback to internal calculation
        return $this->calculateTax(
            $transaction['amount'], 
            $transaction['address']['country'] ?? 'US'
        );
    }

    private function getTaxRate(string $countryCode, ?string $businessType = null): ?array
    {
        // Business exemptions
        if ($businessType === 'b2b' && in_array($countryCode, ['US', 'CA'])) {
            return null; // B2B often exempt from sales tax
        }

        return $this->taxRates[$countryCode] ?? null;
    }
}