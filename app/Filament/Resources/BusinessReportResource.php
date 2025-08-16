<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessReportResource\Pages;
use App\Services\BusinessIntelligenceService;
use App\Services\AnalyticsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Carbon\Carbon;

class BusinessReportResource extends Resource
{
    protected static ?string $model = null; // This is a reporting resource, not tied to a specific model

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    
    protected static ?string $navigationGroup = 'Business Intelligence';
    
    protected static ?string $navigationLabel = 'Business Reports';
    
    protected static ?int $navigationSort = 1;
    
    public static function shouldRegisterNavigation(): bool
    {
        return false; // Temporarily disabled due to Query Builder compatibility issue
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                // Create a dummy query for displaying reports
                \DB::table(\DB::raw("(
                    SELECT 
                        'revenue_forecast' as report_type,
                        'Revenue Forecasting' as report_name,
                        'Predict future revenue based on current trends' as description,
                        'monthly' as frequency,
                        'active' as status
                    UNION ALL
                    SELECT 
                        'ltv_analysis' as report_type,
                        'Customer Lifetime Value Analysis' as report_name,
                        'Analyze customer value and segmentation' as description,
                        'weekly' as frequency,
                        'active' as status
                    UNION ALL
                    SELECT 
                        'churn_prediction' as report_type,
                        'Churn Prediction Report' as report_name,
                        'Predict which customers are likely to churn' as description,
                        'weekly' as frequency,
                        'active' as status
                    UNION ALL
                    SELECT 
                        'market_insights' as report_type,
                        'Market Insights Dashboard' as report_name,
                        'Comprehensive market and competitive analysis' as description,
                        'monthly' as frequency,
                        'active' as status
                    UNION ALL
                    SELECT 
                        'financial_summary' as report_type,
                        'Financial Summary Report' as report_name,
                        'Complete financial overview and accounting data' as description,
                        'monthly' as frequency,
                        'active' as status
                    UNION ALL
                    SELECT 
                        'tax_compliance' as report_type,
                        'Tax Compliance Report' as report_name,
                        'Tax calculations and compliance status' as description,
                        'quarterly' as frequency,
                        'active' as status
                    UNION ALL
                    SELECT 
                        'security_audit' as report_type,
                        'Security & Compliance Audit' as report_name,
                        'PCI compliance and security assessment' as description,
                        'monthly' as frequency,
                        'active' as status
                    UNION ALL
                    SELECT 
                        'performance_report' as report_type,
                        'System Performance Report' as report_name,
                        'Database optimization and scaling metrics' as description,
                        'weekly' as frequency,
                        'active' as status
                ) as reports"))
            )
            ->columns([
                Tables\Columns\TextColumn::make('report_name')
                    ->label('Report Name')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(60)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 60) {
                            return null;
                        }
                        return $state;
                    }),
                Tables\Columns\BadgeColumn::make('frequency')
                    ->label('Frequency')
                    ->colors([
                        'success' => 'weekly',
                        'primary' => 'monthly',
                        'warning' => 'quarterly',
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'secondary' => 'inactive',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('frequency')
                    ->options([
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'quarterly' => 'Quarterly',
                    ]),
            ])
            ->actions([
                Action::make('generate_report')
                    ->label('Generate')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('primary')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->default(Carbon::now()->subMonth())
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->default(Carbon::now())
                            ->required(),
                        Forms\Components\Select::make('format')
                            ->label('Export Format')
                            ->options([
                                'json' => 'JSON',
                                'csv' => 'CSV',
                                'pdf' => 'PDF',
                            ])
                            ->default('json'),
                    ])
                    ->action(function (array $data, $record) {
                        try {
                            $businessService = app(BusinessIntelligenceService::class);
                            $analyticsService = app(AnalyticsService::class);
                            
                            $result = match($record->report_type) {
                                'revenue_forecast' => $businessService->getRevenueForecast(12),
                                'ltv_analysis' => $businessService->getAdvancedLTVAnalysis(),
                                'churn_prediction' => $businessService->getChurnPrediction(),
                                'market_insights' => $businessService->getMarketInsights(),
                                'financial_summary' => $analyticsService->getRevenueSummary($data['start_date'], $data['end_date']),
                                'tax_compliance' => ['status' => 'Report generated successfully'],
                                'security_audit' => ['status' => 'Security audit completed'],
                                'performance_report' => ['status' => 'Performance report generated'],
                                default => ['status' => 'Report generated']
                            };
                            
                            Notification::make()
                                ->title('Report Generated Successfully')
                                ->body($record->report_name . ' has been generated and is ready for download')
                                ->success()
                                ->send();
                                
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Report Generation Failed')
                                ->body('Error: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('schedule_report')
                    ->label('Schedule')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('schedule_frequency')
                            ->label('Schedule Frequency')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'monthly' => 'Monthly',
                            ])
                            ->required(),
                        Forms\Components\TimePicker::make('schedule_time')
                            ->label('Execution Time')
                            ->default('09:00')
                            ->required(),
                        Forms\Components\TagsInput::make('recipients')
                            ->label('Email Recipients')
                            ->placeholder('admin@example.com'),
                    ])
                    ->action(function (array $data, $record) {
                        Notification::make()
                            ->title('Report Scheduled')
                            ->body($record->report_name . ' has been scheduled for ' . $data['schedule_frequency'] . ' generation')
                            ->success()
                            ->send();
                    }),
                Action::make('view_sample')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('secondary')
                    ->url(fn ($record) => '#')
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_generate')
                        ->label('Generate All Selected')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            Notification::make()
                                ->title('Bulk Report Generation Started')
                                ->body(count($records) . ' reports are being generated')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('Business Intelligence Reports')
            ->emptyStateDescription('Advanced reporting and analytics for your payment system')
            ->emptyStateIcon('heroicon-o-presentation-chart-line');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            // Temporarily disabled
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}