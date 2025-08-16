<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AccountingIntegrationService
{
    /**
     * Sync payment data to QuickBooks
     */
    public function syncToQuickBooks(Payment $payment): array
    {
        try {
            // QuickBooks API integration example
            $invoiceData = [
                'TxnDate' => $payment->created_at->toDateString(),
                'CustomerRef' => [
                    'value' => $this->getOrCreateQuickBooksCustomer($payment->customer_email)
                ],
                'Line' => [
                    [
                        'Amount' => $payment->amount,
                        'DetailType' => 'SalesItemLineDetail',
                        'SalesItemLineDetail' => [
                            'ItemRef' => [
                                'value' => '1', // Service item ID in QuickBooks
                                'name' => 'Digital Service'
                            ]
                        ]
                    ]
                ]
            ];

            // Add tax information if available
            if (isset($payment->tax_data['tax_amount']) && $payment->tax_data['tax_amount'] > 0) {
                $invoiceData['TxnTaxDetail'] = [
                    'TotalTax' => $payment->tax_data['tax_amount']
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.quickbooks.access_token'),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post(config('services.quickbooks.base_url') . '/v3/company/' . 
                     config('services.quickbooks.company_id') . '/invoice', $invoiceData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'quickbooks_id' => $response->json()['QueryResponse']['Invoice'][0]['Id'],
                    'sync_date' => now()->toISOString()
                ];
            }

            throw new \Exception('QuickBooks API error: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('QuickBooks sync failed for payment ' . $payment->id . ': ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry_after' => now()->addHours(1)->toISOString()
            ];
        }
    }

    /**
     * Sync payment data to Xero
     */
    public function syncToXero(Payment $payment): array
    {
        try {
            $invoiceData = [
                'Type' => 'ACCREC',
                'Contact' => [
                    'ContactID' => $this->getOrCreateXeroContact($payment->customer_email)
                ],
                'Date' => $payment->created_at->toDateString(),
                'DueDate' => $payment->created_at->toDateString(),
                'LineItems' => [
                    [
                        'Description' => 'Digital Service Payment',
                        'Quantity' => 1,
                        'UnitAmount' => $payment->amount,
                        'AccountCode' => '200' // Revenue account
                    ]
                ],
                'Status' => 'PAID'
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.xero.access_token'),
                'Content-Type' => 'application/json',
                'Xero-tenant-id' => config('services.xero.tenant_id')
            ])->post('https://api.xero.com/api.xro/2.0/Invoices', [
                'Invoices' => [$invoiceData]
            ]);

            if ($response->successful()) {
                $invoice = $response->json()['Invoices'][0];
                return [
                    'success' => true,
                    'xero_id' => $invoice['InvoiceID'],
                    'sync_date' => now()->toISOString()
                ];
            }

            throw new \Exception('Xero API error: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Xero sync failed for payment ' . $payment->id . ': ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retry_after' => now()->addHours(1)->toISOString()
            ];
        }
    }

    /**
     * Generate comprehensive financial report
     */
    public function generateFinancialReport(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // Revenue Analysis
        $totalRevenue = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $subscriptionRevenue = Payment::where('status', 'completed')
            ->whereNotNull('subscription_id')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $oneTimeRevenue = $totalRevenue - $subscriptionRevenue;

        // Tax Analysis
        $taxReport = app(TaxCalculationService::class)->generateTaxReport($startDate, $endDate);

        // Customer Analysis
        $newCustomers = Customer::whereBetween('created_at', [$start, $end])->count();
        $totalCustomers = Customer::where('created_at', '<=', $end)->count();

        // Subscription Metrics
        $newSubscriptions = Subscription::whereBetween('created_at', [$start, $end])->count();
        $activeSubscriptions = Subscription::where('status', 'active')
            ->where('starts_at', '<=', $end)
            ->count();

        $cancelledSubscriptions = Subscription::where('status', 'cancelled')
            ->whereBetween('cancelled_at', [$start, $end])
            ->count();

        // Monthly Recurring Revenue
        $mrr = $this->calculateMRR($end);

        // Churn Analysis
        $churnRate = $activeSubscriptions > 0 ? 
            round(($cancelledSubscriptions / $activeSubscriptions) * 100, 2) : 0;

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $start->diffInDays($end) + 1
            ],
            
            'revenue' => [
                'total_revenue' => round($totalRevenue, 2),
                'subscription_revenue' => round($subscriptionRevenue, 2),
                'one_time_revenue' => round($oneTimeRevenue, 2),
                'subscription_percentage' => $totalRevenue > 0 ? 
                    round(($subscriptionRevenue / $totalRevenue) * 100, 2) : 0,
                'daily_average' => round($totalRevenue / ($start->diffInDays($end) + 1), 2)
            ],

            'tax_summary' => [
                'total_tax_collected' => $taxReport['total_tax_collected'],
                'net_revenue' => $taxReport['total_net_amount'],
                'tax_by_country' => $taxReport['by_country']
            ],

            'customers' => [
                'new_customers' => $newCustomers,
                'total_customers' => $totalCustomers,
                'growth_rate' => $this->calculateCustomerGrowthRate($start, $end)
            ],

            'subscriptions' => [
                'new_subscriptions' => $newSubscriptions,
                'active_subscriptions' => $activeSubscriptions,
                'cancelled_subscriptions' => $cancelledSubscriptions,
                'churn_rate' => $churnRate,
                'mrr' => round($mrr, 2)
            ],

            'key_metrics' => [
                'average_revenue_per_user' => $totalCustomers > 0 ? 
                    round($totalRevenue / $totalCustomers, 2) : 0,
                'customer_acquisition_cost' => $this->estimateCAC($newCustomers),
                'lifetime_value' => $this->calculateAverageLTV(),
                'payment_success_rate' => $this->calculatePaymentSuccessRate($start, $end)
            ],

            'generated_at' => now()->toISOString(),
            'currency' => 'USD'
        ];
    }

    /**
     * Export data for accounting software
     */
    public function exportForAccounting(string $format, string $startDate, string $endDate): array
    {
        $report = $this->generateFinancialReport($startDate, $endDate);
        
        switch (strtolower($format)) {
            case 'quickbooks':
                return $this->formatForQuickBooks($report);
            
            case 'xero':
                return $this->formatForXero($report);
            
            case 'csv':
                return $this->formatAsCSV($report);
            
            case 'json':
            default:
                return $report;
        }
    }

    /**
     * Sync all pending payments to accounting software
     */
    public function syncPendingPayments(string $provider = 'quickbooks'): array
    {
        $pendingPayments = Payment::where('status', 'completed')
            ->whereNull('accounting_sync_data')
            ->limit(50) // Process in batches
            ->get();

        $results = [
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($pendingPayments as $payment) {
            $results['total_processed']++;
            
            try {
                $syncResult = match ($provider) {
                    'quickbooks' => $this->syncToQuickBooks($payment),
                    'xero' => $this->syncToXero($payment),
                    default => ['success' => false, 'error' => 'Unknown provider']
                };

                if ($syncResult['success']) {
                    $payment->update([
                        'accounting_sync_data' => $syncResult
                    ]);
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'payment_id' => $payment->id,
                        'error' => $syncResult['error'] ?? 'Unknown error'
                    ];
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    // Private helper methods

    private function getOrCreateQuickBooksCustomer(string $email): string
    {
        // In production, this would query QuickBooks API to find or create customer
        return 'QB_CUSTOMER_' . md5($email);
    }

    private function getOrCreateXeroContact(string $email): string
    {
        // In production, this would query Xero API to find or create contact
        return 'XERO_CONTACT_' . md5($email);
    }

    private function calculateMRR(Carbon $asOfDate): float
    {
        $monthlySubscriptions = Subscription::where('status', 'active')
            ->whereHas('plan', function ($query) {
                $query->where('billing_interval', 'monthly');
            })
            ->where('starts_at', '<=', $asOfDate)
            ->with('plan')
            ->get();

        $yearlySubscriptions = Subscription::where('status', 'active')
            ->whereHas('plan', function ($query) {
                $query->where('billing_interval', 'yearly');
            })
            ->where('starts_at', '<=', $asOfDate)
            ->with('plan')
            ->get();

        $monthlyMRR = $monthlySubscriptions->sum(function ($subscription) {
            return $subscription->plan_data['price'] ?? 0;
        });

        $yearlyMRR = $yearlySubscriptions->sum(function ($subscription) {
            return ($subscription->plan_data['price'] ?? 0) / 12;
        });

        return $monthlyMRR + $yearlyMRR;
    }

    private function calculateCustomerGrowthRate(Carbon $start, Carbon $end): float
    {
        $customersAtStart = Customer::where('created_at', '<', $start)->count();
        $customersAtEnd = Customer::where('created_at', '<=', $end)->count();

        return $customersAtStart > 0 ? 
            round((($customersAtEnd - $customersAtStart) / $customersAtStart) * 100, 2) : 0;
    }

    private function estimateCAC(int $newCustomers): float
    {
        // Simplified CAC calculation - would integrate with marketing spend APIs
        $estimatedMarketingSpend = 5000;
        return $newCustomers > 0 ? round($estimatedMarketingSpend / $newCustomers, 2) : 0;
    }

    private function calculateAverageLTV(): float
    {
        return round(Customer::avg('lifetime_value') ?? 0, 2);
    }

    private function calculatePaymentSuccessRate(Carbon $start, Carbon $end): float
    {
        $totalAttempts = Payment::whereBetween('created_at', [$start, $end])->count();
        $successfulPayments = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        return $totalAttempts > 0 ? round(($successfulPayments / $totalAttempts) * 100, 2) : 0;
    }

    private function formatForQuickBooks(array $report): array
    {
        return [
            'format' => 'quickbooks',
            'journal_entries' => [
                [
                    'date' => $report['period']['end_date'],
                    'description' => 'Revenue Recognition - ' . $report['period']['start_date'] . ' to ' . $report['period']['end_date'],
                    'lines' => [
                        [
                            'account' => 'Cash',
                            'debit' => $report['revenue']['total_revenue']
                        ],
                        [
                            'account' => 'Revenue',
                            'credit' => $report['tax_summary']['net_revenue']
                        ],
                        [
                            'account' => 'Tax Payable',
                            'credit' => $report['tax_summary']['total_tax_collected']
                        ]
                    ]
                ]
            ]
        ];
    }

    private function formatForXero(array $report): array
    {
        return [
            'format' => 'xero',
            'manual_journals' => [
                [
                    'Date' => $report['period']['end_date'],
                    'Narration' => 'Revenue Recognition - ' . $report['period']['start_date'] . ' to ' . $report['period']['end_date'],
                    'JournalLines' => [
                        [
                            'AccountCode' => '090', // Cash account
                            'Description' => 'Payment Gateway Receipts',
                            'TaxType' => 'NONE',
                            'Debit' => $report['revenue']['total_revenue']
                        ],
                        [
                            'AccountCode' => '200', // Revenue account
                            'Description' => 'Digital Service Revenue',
                            'TaxType' => 'OUTPUT',
                            'Credit' => $report['tax_summary']['net_revenue']
                        ]
                    ]
                ]
            ]
        ];
    }

    private function formatAsCSV(array $report): array
    {
        $csvData = [
            ['Metric', 'Value'],
            ['Total Revenue', $report['revenue']['total_revenue']],
            ['Subscription Revenue', $report['revenue']['subscription_revenue']],
            ['One-time Revenue', $report['revenue']['one_time_revenue']],
            ['Total Tax Collected', $report['tax_summary']['total_tax_collected']],
            ['Net Revenue', $report['tax_summary']['net_revenue']],
            ['New Customers', $report['customers']['new_customers']],
            ['Total Customers', $report['customers']['total_customers']],
            ['New Subscriptions', $report['subscriptions']['new_subscriptions']],
            ['Active Subscriptions', $report['subscriptions']['active_subscriptions']],
            ['MRR', $report['subscriptions']['mrr']],
            ['Churn Rate', $report['subscriptions']['churn_rate'] . '%']
        ];

        return [
            'format' => 'csv',
            'filename' => 'financial_report_' . str_replace('-', '', $report['period']['end_date']) . '.csv',
            'data' => $csvData
        ];
    }
}