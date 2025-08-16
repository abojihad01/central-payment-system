<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FraudRuleResource\Pages;
use App\Filament\Resources\FraudRuleResource\RelationManagers;
use App\Models\FraudRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FraudRuleResource extends Resource
{
    protected static ?string $model = FraudRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    
    protected static ?string $navigationGroup = 'Security & Fraud';
    
    protected static ?string $recordTitleAttribute = 'name';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Rule Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Rule Name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->required()
                            ->rows(3),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->required()
                            ->default(true),
                        Forms\Components\TextInput::make('priority')
                            ->label('Priority (1-100)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(50)
                            ->helperText('Higher numbers = higher priority'),
                    ])->columns(2),

                Forms\Components\Section::make('Rule Configuration')
                    ->schema([
                        Forms\Components\Textarea::make('conditions')
                            ->label('Rule Conditions (JSON)')
                            ->required()
                            ->rows(6)
                            ->helperText('JSON format: {"field": "value", "operator": "equals", "threshold": 100}'),
                        Forms\Components\Select::make('action')
                            ->label('Action to Take')
                            ->required()
                            ->options([
                                'allow' => 'Allow Transaction',
                                'flag' => 'Flag for Review',
                                'block' => 'Block Transaction',
                                'review' => 'Manual Review Required',
                                'monitor' => 'Monitor Closely',
                            ])
                            ->default('flag'),
                        Forms\Components\TextInput::make('risk_score_impact')
                            ->label('Risk Score Impact')
                            ->required()
                            ->numeric()
                            ->minValue(-50)
                            ->maxValue(100)
                            ->default(10)
                            ->helperText('Points to add/subtract from risk score (-50 to +100)'),
                    ])->columns(1),

                Forms\Components\Section::make('Performance Statistics')
                    ->schema([
                        Forms\Components\TextInput::make('times_triggered')
                            ->label('Times Triggered')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->helperText('Total number of times this rule has been triggered'),
                        Forms\Components\TextInput::make('false_positives')
                            ->label('False Positives')
                            ->numeric()
                            ->default(0)
                            ->helperText('Number of false positive detections'),
                        Forms\Components\TextInput::make('accuracy_rate')
                            ->label('Accuracy Rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->default(95.00)
                            ->suffix('%')
                            ->helperText('Percentage accuracy of this rule'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Rule Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Priority')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 80 => 'danger',
                        $state >= 60 => 'warning',
                        $state >= 40 => 'primary',
                        default => 'secondary',
                    }),
                Tables\Columns\BadgeColumn::make('action')
                    ->label('Action')
                    ->colors([
                        'success' => 'allow',
                        'warning' => ['flag', 'monitor'],
                        'danger' => 'block',
                        'primary' => 'review',
                    ]),
                Tables\Columns\TextColumn::make('risk_score_impact')
                    ->label('Risk Impact')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : ($state < 0 ? 'success' : 'secondary'))
                    ->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '') . $state),
                Tables\Columns\TextColumn::make('times_triggered')
                    ->label('Triggered')
                    ->numeric()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Total'),
                    ]),
                Tables\Columns\TextColumn::make('false_positives')
                    ->label('False +')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state > 10 ? 'danger' : ($state > 5 ? 'warning' : 'success')),
                Tables\Columns\TextColumn::make('accuracy_rate')
                    ->label('Accuracy')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->suffix('%')
                    ->color(fn ($state) => $state >= 95 ? 'success' : ($state >= 90 ? 'warning' : 'danger')),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
                SelectFilter::make('action')
                    ->label('Action Type')
                    ->options([
                        'allow' => 'Allow',
                        'flag' => 'Flag',
                        'block' => 'Block',
                        'review' => 'Review',
                        'monitor' => 'Monitor',
                    ]),
                Filter::make('priority_range')
                    ->form([
                        Forms\Components\Select::make('priority_level')
                            ->label('Priority Level')
                            ->options([
                                'critical' => 'Critical (80-100)',
                                'high' => 'High (60-79)',
                                'medium' => 'Medium (40-59)',
                                'low' => 'Low (1-39)',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['priority_level'],
                            fn (Builder $query, $level): Builder => match($level) {
                                'critical' => $query->where('priority', '>=', 80),
                                'high' => $query->whereBetween('priority', [60, 79]),
                                'medium' => $query->whereBetween('priority', [40, 59]),
                                'low' => $query->where('priority', '<', 40),
                                default => $query,
                            }
                        );
                    }),
                Filter::make('performance')
                    ->form([
                        Forms\Components\Select::make('performance_level')
                            ->label('Performance Level')
                            ->options([
                                'excellent' => 'Excellent (≥95% accuracy)',
                                'good' => 'Good (90-94% accuracy)',
                                'poor' => 'Poor (<90% accuracy)',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['performance_level'],
                            fn (Builder $query, $level): Builder => match($level) {
                                'excellent' => $query->where('accuracy_rate', '>=', 95),
                                'good' => $query->whereBetween('accuracy_rate', [90, 94.99]),
                                'poor' => $query->where('accuracy_rate', '<', 90),
                                default => $query,
                            }
                        );
                    }),
            ])
            ->actions([
                Action::make('toggle_status')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn ($record) => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['is_active' => !$record->is_active]);
                        
                        Notification::make()
                            ->title('Rule ' . ($record->is_active ? 'activated' : 'deactivated') . ' successfully')
                            ->success()
                            ->send();
                    }),
                Action::make('test_rule')
                    ->label('Test Rule')
                    ->icon('heroicon-o-beaker')
                    ->color('primary')
                    ->form([
                        Forms\Components\Textarea::make('test_data')
                            ->label('Test Data (JSON)')
                            ->required()
                            ->rows(6)
                            ->placeholder('{"customer_email": "test@example.com", "amount": 1000, "country": "US"}'),
                    ])
                    ->action(function ($record, array $data) {
                        // Here you would implement rule testing logic
                        Notification::make()
                            ->title('Rule test completed')
                            ->body('Test results would be shown here in production')
                            ->info()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['is_active' => true]);
                            
                            Notification::make()
                                ->title('Rules activated successfully')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('bulk_deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['is_active' => false]);
                            
                            Notification::make()
                                ->title('Rules deactivated successfully')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFraudRules::route('/'),
            'create' => Pages\CreateFraudRule::route('/create'),
            'edit' => Pages\EditFraudRule::route('/{record}/edit'),
        ];
    }
}
