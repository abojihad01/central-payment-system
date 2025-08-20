<?php

namespace App\Filament\Widgets;

use App\Models\Website;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TopWebsitesWidget extends BaseWidget
{
    protected static ?string $heading = 'أفضل 5 مواقع أداءً (هذا الشهر)';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $thisMonth = Carbon::now()->startOfMonth();
        
        return Website::query()
            ->leftJoin('generated_links', 'websites.id', '=', 'generated_links.website_id')
            ->leftJoin('payments', 'generated_links.id', '=', 'payments.generated_link_id')
            ->select([
                'websites.id',
                'websites.name',
                'websites.domain',
                'websites.is_active',
                DB::raw('COALESCE(SUM(CASE WHEN payments.status = "completed" AND payments.created_at >= "' . $thisMonth->format('Y-m-d H:i:s') . '" THEN payments.amount ELSE 0 END), 0) as monthly_revenue'),
                DB::raw('COUNT(CASE WHEN payments.status = "completed" AND payments.created_at >= "' . $thisMonth->format('Y-m-d H:i:s') . '" THEN payments.id END) as monthly_payments'),
                DB::raw('COUNT(DISTINCT CASE WHEN generated_links.created_at >= "' . $thisMonth->format('Y-m-d H:i:s') . '" THEN generated_links.id END) as monthly_links'),
                DB::raw('COALESCE(AVG(CASE WHEN payments.status = "completed" AND payments.created_at >= "' . $thisMonth->format('Y-m-d H:i:s') . '" THEN payments.amount END), 0) as avg_payment'),
            ])
            ->groupBy(['websites.id', 'websites.name', 'websites.domain', 'websites.is_active'])
            ->orderBy('monthly_revenue', 'desc')
            ->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')
                ->label('اسم الموقع')
                ->searchable()
                ->weight('bold'),

            Tables\Columns\TextColumn::make('domain')
                ->label('النطاق')
                ->url(fn ($record) => 'https://' . $record->domain, true)
                ->color('primary'),

            Tables\Columns\IconColumn::make('is_active')
                ->label('الحالة')
                ->boolean()
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger'),

            Tables\Columns\TextColumn::make('monthly_revenue')
                ->label('إيرادات الشهر')
                ->money('USD')
                ->sortable()
                ->color('success')
                ->weight('bold'),

            Tables\Columns\TextColumn::make('monthly_payments')
                ->label('المدفوعات')
                ->numeric()
                ->sortable()
                ->badge()
                ->color('info'),

            Tables\Columns\TextColumn::make('monthly_links')
                ->label('الروابط المُولدة')
                ->numeric()
                ->sortable()
                ->badge()
                ->color('warning'),

            Tables\Columns\TextColumn::make('avg_payment')
                ->label('متوسط قيمة الدفعة')
                ->money('USD')
                ->sortable(),

            Tables\Columns\TextColumn::make('conversion_rate')
                ->label('معدل التحويل')
                ->getStateUsing(function ($record) {
                    if ($record->monthly_links > 0) {
                        return round(($record->monthly_payments / $record->monthly_links) * 100, 1) . '%';
                    }
                    return '0%';
                })
                ->badge()
                ->color(function ($record) {
                    $rate = $record->monthly_links > 0 ? ($record->monthly_payments / $record->monthly_links) * 100 : 0;
                    if ($rate > 80) return 'success';
                    if ($rate > 60) return 'warning';
                    return 'danger';
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('view_details')
                ->label('عرض التفاصيل')
                ->icon('heroicon-o-eye')
                ->url(fn ($record) => route('filament.admin.pages.reports-and-analytics') . '?website_id=' . $record->id)
                ->openUrlInNewTab(),
        ];
    }
}