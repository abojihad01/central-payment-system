<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Plan;
use App\Models\PaymentAccount;

class StripeSyncStatusWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    
    protected function getStats(): array
    {
        $totalPlans = Plan::count();
        $stripeAccounts = PaymentAccount::whereHas('gateway', function($q) {
            $q->where('name', 'stripe');
        })->where('is_active', true)->count();
        
        $fullySynced = 0;
        $partiallySynced = 0;
        $notSynced = 0;
        
        $plans = Plan::all();
        
        foreach ($plans as $plan) {
            $metadata = $plan->metadata ?? [];
            $stripeProducts = $metadata['stripe_products'] ?? [];
            
            $syncedAccounts = count($stripeProducts);
            
            if ($syncedAccounts === $stripeAccounts && $syncedAccounts > 0) {
                $fullySynced++;
            } elseif ($syncedAccounts > 0) {
                $partiallySynced++;
            } else {
                $notSynced++;
            }
        }
        
        $syncPercentage = $totalPlans > 0 ? round(($fullySynced / $totalPlans) * 100, 1) : 0;
        
        return [
            Stat::make('Total Plans', $totalPlans)
                ->description('Active subscription plans')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),
                
            Stat::make('Fully Synced', $fullySynced)
                ->description("{$syncPercentage}% sync rate")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
                
            Stat::make('Stripe Accounts', $stripeAccounts)
                ->description('Active Stripe integrations')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('info'),
                
            Stat::make('Needs Sync', $partiallySynced + $notSynced)
                ->description('Plans requiring attention')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($partiallySynced + $notSynced > 0 ? 'warning' : 'success'),
        ];
    }
}