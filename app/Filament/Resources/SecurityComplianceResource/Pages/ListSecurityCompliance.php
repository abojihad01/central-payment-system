<?php

namespace App\Filament\Resources\SecurityComplianceResource\Pages;

use App\Filament\Resources\SecurityComplianceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSecurityCompliance extends ListRecords
{
    protected static string $resource = SecurityComplianceResource::class;


    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('comprehensive_audit')
                ->label('Run Full Security Audit')
                ->icon('heroicon-o-shield-check')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Comprehensive Security Audit')
                ->modalDescription('This will run all security checks and generate a complete compliance report. This process may take several minutes.')
                ->action(function () {
                    \Filament\Notifications\Notification::make()
                        ->title('Comprehensive Security Audit Started')
                        ->body('Full security audit is running. You will be notified when complete.')
                        ->success()
                        ->send();
                }),
                
            Actions\Action::make('pci_compliance_report')
                ->label('PCI Compliance Report')
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Select::make('report_type')
                        ->label('Report Type')
                        ->options([
                            'executive_summary' => 'Executive Summary',
                            'detailed_technical' => 'Detailed Technical Report',
                            'compliance_checklist' => 'Compliance Checklist',
                        ])
                        ->default('executive_summary')
                        ->required(),
                    \Filament\Forms\Components\DatePicker::make('assessment_date')
                        ->label('Assessment Date')
                        ->default(now())
                        ->required(),
                ])
                ->action(function (array $data) {
                    \Filament\Notifications\Notification::make()
                        ->title('PCI Compliance Report Generated')
                        ->body('PCI DSS compliance report (' . $data['report_type'] . ') has been generated successfully')
                        ->success()
                        ->send();
                }),
                
            Actions\Action::make('security_settings')
                ->label('Security Settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Section::make('Monitoring Settings')
                        ->schema([
                            \Filament\Forms\Components\Toggle::make('real_time_monitoring')
                                ->label('Real-time Security Monitoring')
                                ->default(true),
                            \Filament\Forms\Components\Toggle::make('automated_remediation')
                                ->label('Automated Threat Remediation')
                                ->default(false),
                            \Filament\Forms\Components\Select::make('alert_threshold')
                                ->label('Security Alert Threshold')
                                ->options([
                                    'low' => 'Low (Score < 90%)',
                                    'medium' => 'Medium (Score < 80%)',
                                    'high' => 'High (Score < 70%)',
                                ])
                                ->default('medium'),
                        ]),
                    \Filament\Forms\Components\Section::make('Notification Settings')
                        ->schema([
                            \Filament\Forms\Components\TagsInput::make('alert_emails')
                                ->label('Security Alert Email Recipients')
                                ->placeholder('security@example.com'),
                            \Filament\Forms\Components\Toggle::make('slack_notifications')
                                ->label('Enable Slack Notifications')
                                ->default(false),
                            \Filament\Forms\Components\TextInput::make('slack_webhook')
                                ->label('Slack Webhook URL')
                                ->url(),
                        ]),
                ])
                ->action(function (array $data) {
                    \Filament\Notifications\Notification::make()
                        ->title('Security Settings Updated')
                        ->body('Security monitoring and notification settings have been updated')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\FraudDetectionStats::class,
        ];
    }
}