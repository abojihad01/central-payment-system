<?php

namespace App\Filament\Widgets;

use App\Models\GeneratedLink;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TopLinksWidget extends BaseWidget
{
    protected static ?string $heading = 'أفضل 5 روابط مُولدة أداءً (هذا الشهر)';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $thisMonth = Carbon::now()->startOfMonth();
        
        return GeneratedLink::query()
            ->leftJoin('payments', 'generated_links.id', '=', 'payments.generated_link_id')
            ->leftJoin('websites', 'generated_links.website_id', '=', 'websites.id')
            ->select([
                'generated_links.id',
                'generated_links.token',
                'generated_links.price',
                'generated_links.currency',
                'generated_links.created_at',
                'websites.name as website_name',
                DB::raw('COALESCE(SUM(CASE WHEN payments.status = "completed" AND payments.created_at >= "' . $thisMonth->format('Y-m-d H:i:s') . '" THEN payments.amount ELSE 0 END), 0) as monthly_revenue'),
                DB::raw('COUNT(CASE WHEN payments.status = "completed" AND payments.created_at >= "' . $thisMonth->format('Y-m-d H:i:s') . '" THEN payments.id END) as successful_payments'),
                DB::raw('COUNT(CASE WHEN payments.created_at >= "' . $thisMonth->format('Y-m-d H:i:s') . '" THEN payments.id END) as total_payments'),
            ])
            ->groupBy([
                'generated_links.id', 
                'generated_links.token', 
                'generated_links.price', 
                'generated_links.currency',
                'generated_links.created_at',
                'websites.name'
            ])
            ->orderBy('monthly_revenue', 'desc')
            ->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('token')
                ->label('رمز الرابط')
                ->limit(10)
                ->copyable()
                ->copyableState(fn ($record) => $record->token)
                ->badge()
                ->color('primary'),

            Tables\Columns\TextColumn::make('website_name')
                ->label('الموقع')
                ->weight('bold'),

            Tables\Columns\TextColumn::make('price')
                ->label('السعر المحدد')
                ->money('USD')
                ->weight('bold'),

            Tables\Columns\TextColumn::make('monthly_revenue')
                ->label('إيرادات الشهر')
                ->money('USD')
                ->sortable()
                ->color('success')
                ->weight('bold'),

            Tables\Columns\TextColumn::make('successful_payments')
                ->label('دفعات ناجحة')
                ->numeric()
                ->sortable()
                ->badge()
                ->color('success'),

            Tables\Columns\TextColumn::make('total_payments')
                ->label('إجمالي المحاولات')
                ->numeric()
                ->sortable()
                ->badge()
                ->color('info'),

            Tables\Columns\TextColumn::make('success_rate')
                ->label('معدل النجاح')
                ->getStateUsing(function ($record) {
                    if ($record->total_payments > 0) {
                        return round(($record->successful_payments / $record->total_payments) * 100, 1) . '%';
                    }
                    return '0%';
                })
                ->badge()
                ->color(function ($record) {
                    $rate = $record->total_payments > 0 ? ($record->successful_payments / $record->total_payments) * 100 : 0;
                    if ($rate > 80) return 'success';
                    if ($rate > 60) return 'warning';
                    return 'danger';
                }),

            Tables\Columns\TextColumn::make('created_at')
                ->label('تاريخ الإنشاء')
                ->date()
                ->sortable(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('copy_link')
                ->label('نسخ الرابط')
                ->icon('heroicon-o-clipboard')
                ->action(function ($record) {
                    // This would typically copy to clipboard via JS
                    return redirect()->back()->with('success', 'تم نسخ الرابط: ' . $record->token);
                }),

            Tables\Actions\Action::make('view_payments')
                ->label('عرض المدفوعات')
                ->icon('heroicon-o-banknotes')
                ->url(fn ($record) => route('filament.admin.resources.payments.index') . '?tableFilters[generated_link_id][value]=' . $record->id)
                ->openUrlInNewTab(),
        ];
    }
}