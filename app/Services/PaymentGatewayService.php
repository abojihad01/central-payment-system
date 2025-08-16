<?php

namespace App\Services;

use App\Models\PaymentGateway;
use App\Models\PaymentAccount;
use App\Models\PaymentSelectionConfig;
use App\Models\PaymentAccountSelection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    public function selectBestGateway(
        string $currency = 'USD',
        ?string $country = null,
        bool $productionOnly = false
    ): ?PaymentGateway {
        $query = PaymentGateway::query()
            ->active()
            ->orderedByPriority()
            ->with(['activeAccounts' => function ($query) use ($productionOnly) {
                if ($productionOnly) {
                    $query->production();
                }
            }]);

        // تصفية حسب العملة (غير حساس للأحرف الكبيرة/الصغيرة)
        if ($currency) {
            $currency = strtoupper($currency); // تحويل إلى أحرف كبيرة
            $query->where(function ($q) use ($currency) {
                $q->whereJsonContains('supported_currencies', $currency)
                  ->orWhereNull('supported_currencies')
                  ->orWhereJsonLength('supported_currencies', 0);
            });
        }

        // تصفية حسب البلد
        if ($country) {
            $query->where(function ($q) use ($country) {
                $q->whereJsonContains('supported_countries', $country)
                  ->orWhereNull('supported_countries')
                  ->orWhereJsonLength('supported_countries', 0);
            });
        }

        $gateways = $query->get();

        // اختيار البوابة التي لديها حسابات نشطة
        foreach ($gateways as $gateway) {
            if ($gateway->activeAccounts->isNotEmpty()) {
                return $gateway;
            }
        }

        return null;
    }
    
    /**
     * اختيار بوابة محددة بالاسم
     */
    public function selectGatewayByName(
        string $gatewayName,
        string $currency = 'USD',
        ?string $country = null,
        bool $productionOnly = false
    ): ?PaymentGateway {
        $query = PaymentGateway::query()
            ->active()
            ->where('name', $gatewayName)
            ->with(['activeAccounts' => function ($query) use ($productionOnly) {
                if ($productionOnly) {
                    $query->production();
                }
            }]);

        // تصفية حسب العملة (غير حساس للأحرف الكبيرة/الصغيرة)
        if ($currency) {
            $currency = strtoupper($currency); // تحويل إلى أحرف كبيرة
            $query->where(function ($q) use ($currency) {
                $q->whereJsonContains('supported_currencies', $currency)
                  ->orWhereNull('supported_currencies')
                  ->orWhereJsonLength('supported_currencies', 0);
            });
        }

        // تصفية حسب البلد
        if ($country) {
            $query->where(function ($q) use ($country) {
                $q->whereJsonContains('supported_countries', $country)
                  ->orWhereNull('supported_countries')
                  ->orWhereJsonLength('supported_countries', 0);
            });
        }

        $gateway = $query->first();

        // التأكد من وجود حسابات نشطة
        if ($gateway && $gateway->activeAccounts->isNotEmpty()) {
            return $gateway;
        }

        return null;
    }
    
    /**
     * الحصول على جميع البوابات المتاحة لعملة وبلد معين
     */
    public function getAvailableGateways(?string $currency = null, ?string $country = null): Collection
    {
        $query = PaymentGateway::query()
            ->active()
            ->orderedByPriority()
            ->with(['activeAccounts']);

        // تصفية حسب العملة (غير حساس للأحرف الكبيرة/الصغيرة)
        if ($currency) {
            $currency = strtoupper($currency); // تحويل إلى أحرف كبيرة
            $query->where(function ($q) use ($currency) {
                $q->whereJsonContains('supported_currencies', $currency)
                  ->orWhereNull('supported_currencies')
                  ->orWhereJsonLength('supported_currencies', 0);
            });
        }

        // تصفية حسب البلد
        if ($country) {
            $query->where(function ($q) use ($country) {
                $q->whereJsonContains('supported_countries', $country)
                  ->orWhereNull('supported_countries')
                  ->orWhereJsonLength('supported_countries', 0);
            });
        }

        return $query->get()->filter(function ($gateway) {
            return $gateway->activeAccounts->isNotEmpty();
        });
    }

    public function selectBestAccount(PaymentGateway $gateway, bool $productionOnly = false, ?int $paymentId = null): ?PaymentAccount
    {
        $startTime = microtime(true);
        
        $query = $gateway->activeAccounts();
        
        if ($productionOnly) {
            $query->production();
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            return null;
        }

        // Get configuration for this gateway
        $config = PaymentSelectionConfig::getConfig($gateway->name);
        
        // Apply selection strategy
        $selectedAccount = $this->applySelectionStrategy($accounts, $config, $gateway->name);
        
        $selectionTime = (microtime(true) - $startTime) * 1000;

        // Log the selection if payment ID is provided
        if ($paymentId && $selectedAccount) {
            $this->logAccountSelection(
                $paymentId,
                $selectedAccount,
                $gateway,
                $accounts,
                $config,
                $selectionTime
            );
        }

        return $selectedAccount;
    }

    private function applySelectionStrategy(Collection $accounts, ?PaymentSelectionConfig $config, string $gatewayName): ?PaymentAccount
    {
        $strategy = $config?->selection_strategy ?? PaymentSelectionConfig::STRATEGY_LEAST_USED;
        
        switch ($strategy) {
            case PaymentSelectionConfig::STRATEGY_LEAST_USED:
                return $this->selectLeastUsed($accounts, $gatewayName);
                
            case PaymentSelectionConfig::STRATEGY_ROUND_ROBIN:
                return $this->selectRoundRobin($accounts, $gatewayName);
                
            case PaymentSelectionConfig::STRATEGY_WEIGHTED:
                return $this->selectWeighted($accounts, $config?->account_weights ?? [], $gatewayName);
                
            case PaymentSelectionConfig::STRATEGY_MANUAL:
                return $this->selectManual($accounts, $config?->account_priorities ?? [], $gatewayName);
                
            case PaymentSelectionConfig::STRATEGY_RANDOM:
                return $this->selectRandom($accounts, $gatewayName);
                
            default:
                return $this->selectLeastUsed($accounts, $gatewayName);
        }
    }

    private function selectLeastUsed(Collection $accounts, string $gatewayName): ?PaymentAccount
    {
        // أولاً: البحث عن حساب لم يُستخدم بعد
        $unusedAccount = $accounts->where('successful_transactions', 0)
                                 ->where('failed_transactions', 0)
                                 ->first();

        if ($unusedAccount) {
            Log::info('Selected unused payment account', [
                'gateway' => $gatewayName,
                'account_id' => $unusedAccount->account_id,
                'strategy' => 'unused_account'
            ]);
            return $unusedAccount;
        }

        // ثانياً: اختيار الحساب بأقل عدد معاملات ناجحة
        $leastUsedAccount = $accounts->sortBy('successful_transactions')->first();

        Log::info('Selected least used payment account', [
            'gateway' => $gatewayName,
            'account_id' => $leastUsedAccount->account_id,
            'successful_transactions' => $leastUsedAccount->successful_transactions,
            'strategy' => 'least_used'
        ]);

        return $leastUsedAccount;
    }

    private function selectRoundRobin(Collection $accounts, string $gatewayName): ?PaymentAccount
    {
        // Simple round-robin based on last_used_at
        $account = $accounts->sortBy('last_used_at')->first();
        
        Log::info('Selected round-robin payment account', [
            'gateway' => $gatewayName,
            'account_id' => $account->account_id,
            'strategy' => 'round_robin'
        ]);
        
        return $account;
    }

    private function selectWeighted(Collection $accounts, array $weights, string $gatewayName): ?PaymentAccount
    {
        if (empty($weights)) {
            return $this->selectLeastUsed($accounts, $gatewayName);
        }
        
        // Weighted random selection
        $totalWeight = 0;
        $weightedAccounts = [];
        
        foreach ($accounts as $account) {
            $weight = $weights[$account->account_id] ?? 1;
            $totalWeight += $weight;
            $weightedAccounts[] = ['account' => $account, 'weight' => $weight];
        }
        
        $random = mt_rand(1, $totalWeight);
        $currentWeight = 0;
        
        foreach ($weightedAccounts as $item) {
            $currentWeight += $item['weight'];
            if ($random <= $currentWeight) {
                Log::info('Selected weighted payment account', [
                    'gateway' => $gatewayName,
                    'account_id' => $item['account']->account_id,
                    'weight' => $item['weight'],
                    'strategy' => 'weighted'
                ]);
                return $item['account'];
            }
        }
        
        return $accounts->first();
    }

    private function selectManual(Collection $accounts, array $priorities, string $gatewayName): ?PaymentAccount
    {
        if (empty($priorities)) {
            return $this->selectLeastUsed($accounts, $gatewayName);
        }
        
        // Sort by manual priority order
        $sortedAccounts = $accounts->sortBy(function ($account) use ($priorities) {
            return $priorities[$account->account_id] ?? 999;
        });
        
        $account = $sortedAccounts->first();
        
        Log::info('Selected manual priority payment account', [
            'gateway' => $gatewayName,
            'account_id' => $account->account_id,
            'priority' => $priorities[$account->account_id] ?? 999,
            'strategy' => 'manual'
        ]);
        
        return $account;
    }

    private function selectRandom(Collection $accounts, string $gatewayName): ?PaymentAccount
    {
        $account = $accounts->random();
        
        Log::info('Selected random payment account', [
            'gateway' => $gatewayName,
            'account_id' => $account->account_id,
            'strategy' => 'random'
        ]);
        
        return $account;
    }

    private function logAccountSelection(
        int $paymentId,
        PaymentAccount $selectedAccount,
        PaymentGateway $gateway,
        Collection $allAccounts,
        ?PaymentSelectionConfig $config,
        float $selectionTimeMs
    ): void {
        try {
            PaymentAccountSelection::create([
                'payment_id' => $paymentId,
                'payment_account_id' => $selectedAccount->id,
                'gateway_name' => $gateway->name,
                'selection_method' => $config?->selection_strategy ?? 'least_used',
                'selection_criteria' => [
                    'total_accounts_available' => $allAccounts->count(),
                    'config_used' => $config?->name ?? 'default',
                    'production_only' => false, // Could be passed as parameter
                ],
                'available_accounts' => $allAccounts->map(fn($acc) => [
                    'account_id' => $acc->account_id,
                    'name' => $acc->name,
                    'successful_transactions' => $acc->successful_transactions,
                    'failed_transactions' => $acc->failed_transactions,
                    'total_amount' => $acc->total_amount,
                    'last_used_at' => $acc->last_used_at,
                ])->toArray(),
                'account_stats' => [
                    'selected_account' => [
                        'successful_transactions' => $selectedAccount->successful_transactions,
                        'failed_transactions' => $selectedAccount->failed_transactions,
                        'total_amount' => $selectedAccount->total_amount,
                        'success_rate' => $this->calculateSuccessRate($selectedAccount),
                    ]
                ],
                'selection_reason' => $this->getSelectionReason($selectedAccount, $allAccounts, $config),
                'selection_priority' => $this->getSelectionPriority($selectedAccount, $allAccounts),
                'was_fallback' => false,
                'selection_time_ms' => $selectionTimeMs,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log account selection', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);
        }
    }

    private function calculateSuccessRate(PaymentAccount $account): float
    {
        $total = $account->successful_transactions + $account->failed_transactions;
        return $total > 0 ? ($account->successful_transactions / $total) * 100 : 100;
    }

    private function getSelectionReason(PaymentAccount $selected, Collection $all, ?PaymentSelectionConfig $config): string
    {
        $strategy = $config?->selection_strategy ?? 'least_used';
        
        switch ($strategy) {
            case 'least_used':
                if ($selected->successful_transactions == 0 && $selected->failed_transactions == 0) {
                    return 'Unused account - never processed transactions';
                }
                return "Least used account - {$selected->successful_transactions} successful transactions";
                
            case 'round_robin':
                return "Round-robin selection - last used: " . ($selected->last_used_at ?? 'never');
                
            case 'weighted':
                $weight = $config->account_weights[$selected->account_id] ?? 1;
                return "Weighted selection - weight: {$weight}";
                
            case 'manual':
                $priority = $config->account_priorities[$selected->account_id] ?? 999;
                return "Manual priority selection - priority: {$priority}";
                
            case 'random':
                return 'Random selection';
                
            default:
                return 'Default selection strategy';
        }
    }

    private function getSelectionPriority(PaymentAccount $selected, Collection $all): int
    {
        return $all->search(function ($account) use ($selected) {
            return $account->id === $selected->id;
        }) + 1;
    }

    public function selectPaymentMethod(
        string $currency = 'USD',
        ?string $country = null,
        bool $productionOnly = false
    ): array {
        $gateway = $this->selectBestGateway($currency, $country, $productionOnly);
        
        if (!$gateway) {
            throw new \Exception('No available payment gateway found for the given criteria');
        }

        $account = $this->selectBestAccount($gateway, $productionOnly);
        
        if (!$account) {
            throw new \Exception("No available payment account found for gateway: {$gateway->name}");
        }

        Log::info('Selected payment method', [
            'gateway' => $gateway->name,
            'account_id' => $account->account_id,
            'currency' => $currency,
            'country' => $country,
            'production_only' => $productionOnly
        ]);

        return [
            'gateway' => $gateway,
            'account' => $account
        ];
    }

    public function retryPayment(
        int $paymentId,
        string $currency = 'USD',
        ?string $country = null,
        bool $productionOnly = false
    ): array {
        // الحصول على البوابات المتاحة
        $availableGateways = $this->getAvailableGateways($currency, $country);
        
        // تصفية للإنتاج إذا لزم الأمر
        if ($productionOnly) {
            $availableGateways = $availableGateways->filter(function ($gateway) {
                return $gateway->activeAccounts->filter(function ($account) {
                    return !$account->is_sandbox;
                })->isNotEmpty();
            });
        }

        if ($availableGateways->isEmpty()) {
            throw new \Exception('No alternative payment gateways available for retry');
        }

        // اختيار بوابة بديلة
        $gateway = $availableGateways->first();
        $account = $this->selectBestAccount($gateway, $productionOnly);

        if (!$account) {
            throw new \Exception("No available account found for retry gateway: {$gateway->name}");
        }

        Log::info('Selected retry payment method', [
            'original_payment_id' => $paymentId,
            'retry_gateway' => $gateway->name,
            'retry_account_id' => $account->account_id
        ]);

        return [
            'gateway' => $gateway,
            'account' => $account
        ];
    }

    public function getGatewayStatistics(): array
    {
        $gateways = PaymentGateway::with(['accounts'])->get();
        
        $stats = [];
        
        foreach ($gateways as $gateway) {
            $stats[] = [
                'name' => $gateway->display_name,
                'code' => $gateway->name,
                'is_active' => $gateway->is_active,
                'accounts_count' => $gateway->accounts->count(),
                'active_accounts_count' => $gateway->activeAccounts->count(),
                'total_transactions' => $gateway->total_transactions,
                'successful_transactions' => $gateway->successful_transactions,
                'failed_transactions' => $gateway->failed_transactions,
                'success_rate' => $gateway->success_rate,
                'total_amount' => $gateway->total_amount,
            ];
        }

        return $stats;
    }

    public function getAccountStatistics(int $gatewayId): array
    {
        $gateway = PaymentGateway::with(['accounts'])->findOrFail($gatewayId);
        
        $stats = [];
        
        foreach ($gateway->accounts as $account) {
            $stats[] = [
                'account_id' => $account->account_id,
                'name' => $account->name,
                'is_active' => $account->is_active,
                'is_sandbox' => $account->is_sandbox,
                'successful_transactions' => $account->successful_transactions,
                'failed_transactions' => $account->failed_transactions,
                'total_transactions' => $account->total_transactions,
                'success_rate' => $account->success_rate,
                'total_amount' => $account->total_amount,
                'last_used_at' => $account->last_used_at,
            ];
        }

        return $stats;
    }

    public function testAccountConnection(PaymentAccount $account): array
    {
        try {
            // TODO: Implement actual connection test based on gateway type
            switch ($account->gateway->name) {
                case 'stripe':
                    return $this->testStripeConnection($account);
                case 'paypal':
                    return $this->testPayPalConnection($account);
                default:
                    return [
                        'success' => false,
                        'message' => 'Connection test not implemented for this gateway'
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function testStripeConnection(PaymentAccount $account): array
    {
        // TODO: Implement Stripe connection test
        return [
            'success' => true,
            'message' => 'Stripe connection test successful (mock)'
        ];
    }

    private function testPayPalConnection(PaymentAccount $account): array
    {
        try {
            $paypalService = new \App\Services\PayPalService($account);
            return $paypalService->testConnection();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'PayPal connection test failed: ' . $e->getMessage()
            ];
        }
    }
}