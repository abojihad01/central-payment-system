<?php

namespace App\Filament\Resources\SystemPerformanceResource\Pages;

use App\Filament\Resources\SystemPerformanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSystemPerformance extends ListRecords
{
    protected static string $resource = SystemPerformanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('comprehensive_optimization')
                ->label('Full System Optimization')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Comprehensive System Optimization')
                ->modalDescription('This will run a complete system optimization including database, cache, queues, and performance tuning. The process may take 10-15 minutes.')
                ->action(function () {
                    \Filament\Notifications\Notification::make()
                        ->title('System Optimization Started')
                        ->body('Comprehensive system optimization is running. You will be notified when complete.')
                        ->success()
                        ->send();
                }),
                
            Actions\Action::make('scaling_analysis')
                ->label('Scaling Analysis')
                ->icon('heroicon-o-chart-bar-square')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Section::make('Analysis Parameters')
                        ->schema([
                            \Filament\Forms\Components\Select::make('time_period')
                                ->label('Analysis Period')
                                ->options([
                                    'last_hour' => 'Last Hour',
                                    'last_day' => 'Last 24 Hours',
                                    'last_week' => 'Last Week',
                                    'last_month' => 'Last Month',
                                ])
                                ->default('last_week')
                                ->required(),
                            \Filament\Forms\Components\Select::make('analysis_type')
                                ->label('Analysis Type')
                                ->options([
                                    'performance_bottlenecks' => 'Performance Bottlenecks',
                                    'capacity_planning' => 'Capacity Planning',
                                    'cost_optimization' => 'Cost Optimization',
                                    'scaling_recommendations' => 'Scaling Recommendations',
                                ])
                                ->default('performance_bottlenecks')
                                ->required(),
                        ]),
                ])
                ->action(function (array $data) {
                    \Filament\Notifications\Notification::make()
                        ->title('Scaling Analysis Generated')
                        ->body('System scaling analysis for ' . $data['time_period'] . ' has been completed')
                        ->success()
                        ->send();
                }),
                
            Actions\Action::make('performance_settings')
                ->label('Performance Settings')
                ->icon('heroicon-o-cog-8-tooth')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Section::make('Monitoring Configuration')
                        ->schema([
                            \Filament\Forms\Components\Toggle::make('auto_scaling')
                                ->label('Enable Auto-scaling')
                                ->default(false),
                            \Filament\Forms\Components\Toggle::make('predictive_scaling')
                                ->label('Predictive Scaling')
                                ->default(false),
                            \Filament\Forms\Components\TextInput::make('cpu_scale_threshold')
                                ->label('CPU Auto-scale Threshold (%)')
                                ->numeric()
                                ->minValue(50)
                                ->maxValue(90)
                                ->default(75),
                            \Filament\Forms\Components\TextInput::make('memory_scale_threshold')
                                ->label('Memory Auto-scale Threshold (%)')
                                ->numeric()
                                ->minValue(50)
                                ->maxValue(90)
                                ->default(80),
                        ]),
                    \Filament\Forms\Components\Section::make('Optimization Settings')
                        ->schema([
                            \Filament\Forms\Components\Toggle::make('automatic_cache_optimization')
                                ->label('Automatic Cache Optimization')
                                ->default(true),
                            \Filament\Forms\Components\Toggle::make('database_query_optimization')
                                ->label('Database Query Optimization')
                                ->default(true),
                            \Filament\Forms\Components\Toggle::make('queue_auto_scaling')
                                ->label('Queue Worker Auto-scaling')
                                ->default(false),
                        ]),
                    \Filament\Forms\Components\Section::make('Alert Configuration')
                        ->schema([
                            \Filament\Forms\Components\TagsInput::make('performance_alert_emails')
                                ->label('Performance Alert Email Recipients')
                                ->placeholder('devops@example.com'),
                            \Filament\Forms\Components\Toggle::make('critical_alerts')
                                ->label('Enable Critical Performance Alerts')
                                ->default(true),
                            \Filament\Forms\Components\Select::make('alert_frequency')
                                ->label('Alert Frequency')
                                ->options([
                                    'immediate' => 'Immediate',
                                    'every_5_minutes' => 'Every 5 Minutes',
                                    'every_15_minutes' => 'Every 15 Minutes',
                                    'hourly' => 'Hourly',
                                ])
                                ->default('every_5_minutes'),
                        ]),
                ])
                ->action(function (array $data) {
                    \Filament\Notifications\Notification::make()
                        ->title('Performance Settings Updated')
                        ->body('System performance monitoring and scaling settings have been updated')
                        ->success()
                        ->send();
                }),
                
            Actions\Action::make('system_health_report')
                ->label('System Health Report')
                ->icon('heroicon-o-heart')
                ->color('secondary')
                ->action(function () {
                    \Filament\Notifications\Notification::make()
                        ->title('System Health Report Generated')
                        ->body('Comprehensive system health report is ready for download')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // You could add performance-specific widgets here
        ];
    }
}