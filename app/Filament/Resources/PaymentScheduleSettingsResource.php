<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentScheduleSettingsResource\Pages;
use App\Models\PaymentScheduleSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentScheduleSettingsResource extends Resource
{
    protected static ?string $model = PaymentScheduleSettings::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Payment Schedule Settings';
    protected static ?string $label = 'Payment Schedule Setting';
    protected static ?string $pluralLabel = 'Payment Schedule Settings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Setting Name')
                    ->required()
                    ->unique(ignoreRecord: true),
                    
                Forms\Components\Select::make('schedule_type')
                    ->label('Schedule Type')
                    ->options([
                        'verification' => 'Payment Verification',
                        'processing' => 'Payment Processing',
                        'retry' => 'Failed Payment Retry',
                        'cleanup' => 'Old Payment Cleanup',
                    ])
                    ->required(),
                    
                Forms\Components\TextInput::make('interval_minutes')
                    ->label('Interval (Minutes)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->helperText('How often this task should run (in minutes)'),
                    
                Forms\Components\TextInput::make('min_age_minutes')
                    ->label('Minimum Age (Minutes)')
                    ->numeric()
                    ->default(5)
                    ->helperText('Minimum age of payments to process'),
                    
                Forms\Components\TextInput::make('max_age_minutes')
                    ->label('Maximum Age (Minutes)')
                    ->numeric()
                    ->default(1440)
                    ->helperText('Maximum age of payments to process (24 hours = 1440 minutes)'),
                    
                Forms\Components\TextInput::make('batch_limit')
                    ->label('Batch Limit')
                    ->numeric()
                    ->default(50)
                    ->minValue(1)
                    ->maxValue(1000)
                    ->helperText('Maximum number of payments to process in one batch'),
                    
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Enable/disable this schedule'),
                    
                Forms\Components\Textarea::make('command')
                    ->label('Artisan Command')
                    ->required()
                    ->helperText('The artisan command to execute (e.g., payments:verify-pending)')
                    ->columnSpanFull(),
                    
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->helperText('Description of what this schedule does')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\BadgeColumn::make('schedule_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'verification',
                        'success' => 'processing',
                        'warning' => 'retry',
                        'danger' => 'cleanup',
                    ]),
                    
                Tables\Columns\TextColumn::make('interval_minutes')
                    ->label('Interval')
                    ->suffix(' min')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('min_age_minutes')
                    ->label('Min Age')
                    ->suffix(' min')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('max_age_minutes')
                    ->label('Max Age')
                    ->suffix(' min')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('batch_limit')
                    ->label('Batch Size')
                    ->sortable(),
                    
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
                    
                Tables\Columns\TextColumn::make('command')
                    ->label('Command')
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        return $column->getState();
                    }),
                    
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('schedule_type')
                    ->options([
                        'verification' => 'Payment Verification',
                        'processing' => 'Payment Processing',
                        'retry' => 'Failed Payment Retry',
                        'cleanup' => 'Old Payment Cleanup',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('test_run')
                    ->label('Test Run')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->action(function (PaymentScheduleSettings $record) {
                        $command = str_replace([
                            '{min_age}',
                            '{max_age}',
                            '{limit}'
                        ], [
                            $record->min_age_minutes,
                            $record->max_age_minutes,
                            min($record->batch_limit, 5) // Limit test runs to 5 items
                        ], $record->command);
                        
                        $fullCommand = "cd /home/payments/central-payment-system && php artisan $command";
                        $output = shell_exec("$fullCommand 2>&1");
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Test run completed')
                            ->body("Command: php artisan $command")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Test Schedule')
                    ->modalDescription('This will run the command with a limited batch size for testing.')
                    ->modalSubmitActionLabel('Run Test'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('toggle_active')
                        ->label('Toggle Active')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => !$record->is_active]);
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentScheduleSettings::route('/'),
            'create' => Pages\CreatePaymentScheduleSettings::route('/create'),
            'edit' => Pages\EditPaymentScheduleSettings::route('/{record}/edit'),
        ];
    }
}