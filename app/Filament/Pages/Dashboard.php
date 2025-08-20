<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'لوحة التحكم';
    
    protected static ?string $navigationLabel = 'لوحة التحكم';
    
    public function getHeading(): string
    {
        return 'مرحباً بك في نظام إدارة المدفوعات المركزي';
    }
    
    public function getSubheading(): ?string
    {
        return 'يمكنك من هنا إدارة جميع المدفوعات والاشتراكات والمهام المجدولة';
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\AnalyticsOverview::class,
            \App\Filament\Widgets\RevenueOverviewWidget::class,
            \App\Filament\Widgets\RevenueChartWidget::class,
            \App\Filament\Widgets\TopWebsitesWidget::class,
            \App\Filament\Widgets\TopLinksWidget::class,
        ];
    }
}