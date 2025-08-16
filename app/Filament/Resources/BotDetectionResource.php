<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BotDetectionResource\Pages;
use App\Models\BotDetection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action;
use App\Models\BotProtectionSettings;
use Filament\Notifications\Notification;

class BotDetectionResource extends Resource
{
    protected static ?string $model = BotDetection::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Bot Detections';

    protected static ?string $modelLabel = 'Bot Detection';

    protected static ?string $pluralModelLabel = 'Bot Detections';

    protected static ?string $navigationGroup = 'Security';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ip_address')
                    ->label('IP Address')
                    ->required()
                    ->disabled(),
                Forms\Components\TextInput::make('user_agent')
                    ->label('User Agent')
                    ->columnSpanFull()
                    ->disabled(),
                Forms\Components\Select::make('detection_type')
                    ->label('Detection Type')
                    ->options([
                        'bot_user_agent' => 'Bot User Agent',
                        'honeypot' => 'Honeypot Trigger',
                        'rate_limit' => 'Rate Limit Exceeded',
                        'timing' => 'Fast Submission',
                        'suspicious_pattern' => 'Suspicious Pattern'
                    ])
                    ->required()
                    ->disabled(),
                Forms\Components\TextInput::make('url_requested')
                    ->label('URL Requested')
                    ->columnSpanFull()
                    ->disabled(),
                Forms\Components\TextInput::make('method')
                    ->label('HTTP Method')
                    ->disabled(),
                Forms\Components\Toggle::make('is_blocked')
                    ->label('Is Blocked')
                    ->disabled(),
                Forms\Components\TextInput::make('risk_score')
                    ->label('Risk Score')
                    ->numeric()
                    ->disabled(),
                Forms\Components\DateTimePicker::make('detected_at')
                    ->label('Detected At')
                    ->disabled(),
                Forms\Components\Textarea::make('detection_details')
                    ->label('Detection Details')
                    ->columnSpanFull()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('detected_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('detected_at')
                    ->label('Detected')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('detection_type_name')
                    ->label('Type')
                    ->colors([
                        'danger' => 'Bot User Agent',
                        'warning' => 'Rate Limit Exceeded',
                        'info' => 'Honeypot Trigger',
                        'success' => 'Fast Submission'
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('url_requested')
                    ->label('URL')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('method')
                    ->label('Method')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('risk_level')
                    ->label('Risk')
                    ->colors([
                        'success' => 'Very Low',
                        'info' => 'Low',
                        'warning' => 'Medium',
                        'danger' => 'High'
                    ]),
                Tables\Columns\IconColumn::make('is_blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('detection_type')
                    ->label('Detection Type')
                    ->options([
                        'bot_user_agent' => 'Bot User Agent',
                        'honeypot' => 'Honeypot Trigger',
                        'rate_limit' => 'Rate Limit Exceeded',
                        'timing' => 'Fast Submission',
                        'suspicious_pattern' => 'Suspicious Pattern'
                    ]),
                SelectFilter::make('is_blocked')
                    ->label('Status')
                    ->options([
                        1 => 'Blocked',
                        0 => 'Allowed'
                    ]),
                Filter::make('detected_at')
                    ->form([
                        DateTimePicker::make('detected_from')
                            ->label('Detected From'),
                        DateTimePicker::make('detected_until')
                            ->label('Detected Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['detected_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('detected_at', '>=', $date),
                            )
                            ->when(
                                $data['detected_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('detected_at', '<=', $date),
                            );
                    }),
                SelectFilter::make('risk_level')
                    ->label('Risk Level')
                    ->options([
                        'Very Low' => 'Very Low',
                        'Low' => 'Low',
                        'Medium' => 'Medium',
                        'High' => 'High'
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['value']) return $query;
                        
                        return match($data['value']) {
                            'Very Low' => $query->where('risk_score', '<', 20),
                            'Low' => $query->whereBetween('risk_score', [20, 49]),
                            'Medium' => $query->whereBetween('risk_score', [50, 79]),
                            'High' => $query->where('risk_score', '>=', 80),
                            default => $query
                        };
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('whitelist_ip')
                    ->label('Whitelist IP')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (BotDetection $record) {
                        $whitelist = \App\Models\BotProtectionSettings::get('whitelist_ips', []);
                        if (!in_array($record->ip_address, $whitelist)) {
                            $whitelist[] = $record->ip_address;
                            \App\Models\BotProtectionSettings::set('whitelist_ips', $whitelist, 'json');
                        }
                    })
                    ->requiresConfirmation(),
                Action::make('blacklist_ip')
                    ->label('Blacklist IP')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->action(function (BotDetection $record) {
                        $blacklist = \App\Models\BotProtectionSettings::get('blacklist_ips', []);
                        if (!in_array($record->ip_address, $blacklist)) {
                            $blacklist[] = $record->ip_address;
                            \App\Models\BotProtectionSettings::set('blacklist_ips', $blacklist, 'json');
                        }
                    })
                    ->requiresConfirmation(),
            ])
            ->headerActions([
                Action::make('toggle_protection')
                    ->label(function () {
                        $enabled = BotProtectionSettings::get('protection_enabled', true);
                        return $enabled ? 'Disable Protection' : 'Enable Protection';
                    })
                    ->icon(function () {
                        $enabled = BotProtectionSettings::get('protection_enabled', true);
                        return $enabled ? 'heroicon-o-shield-exclamation' : 'heroicon-o-shield-check';
                    })
                    ->color(function () {
                        $enabled = BotProtectionSettings::get('protection_enabled', true);
                        return $enabled ? 'danger' : 'success';
                    })
                    ->action(function () {
                        $enabled = BotProtectionSettings::get('protection_enabled', true);
                        BotProtectionSettings::set('protection_enabled', !$enabled, 'boolean');
                        
                        Notification::make()
                            ->title('Bot protection ' . (!$enabled ? 'enabled' : 'disabled'))
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No Bot Detections')
            ->emptyStateDescription('When bot activity is detected, it will appear here.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Detection Information')
                    ->schema([
                        TextEntry::make('ip_address')
                            ->label('IP Address')
                            ->copyable(),
                        TextEntry::make('detection_type_name')
                            ->label('Detection Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Bot User Agent' => 'danger',
                                'Rate Limit Exceeded' => 'warning',
                                'Honeypot Trigger' => 'info',
                                'Fast Submission' => 'success',
                                default => 'gray'
                            }),
                        TextEntry::make('risk_level')
                            ->label('Risk Level')
                            ->badge()
                            ->color(fn (BotDetection $record): string => $record->risk_color),
                        TextEntry::make('risk_score')
                            ->label('Risk Score')
                            ->suffix('/100'),
                        TextEntry::make('is_blocked')
                            ->label('Status')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Blocked' : 'Allowed')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'danger' : 'success'),
                        TextEntry::make('detected_at')
                            ->label('Detected At')
                            ->dateTime(),
                    ])->columns(2),
                
                Section::make('Request Information')
                    ->schema([
                        TextEntry::make('url_requested')
                            ->label('Requested URL')
                            ->copyable(),
                        TextEntry::make('method')
                            ->label('HTTP Method')
                            ->badge(),
                        TextEntry::make('user_agent')
                            ->label('User Agent')
                            ->copyable()
                            ->columnSpanFull(),
                    ])->columns(2),
                
                Section::make('Additional Details')
                    ->schema([
                        TextEntry::make('detection_details')
                            ->label('Detection Details')
                            ->columnSpanFull(),
                        TextEntry::make('request_data')
                            ->label('Request Data')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) return 'No data';
                                if (!is_array($state)) return 'Invalid data format';
                                
                                try {
                                    $items = [];
                                    foreach ($state as $key => $value) {
                                        if (is_array($value)) {
                                            $value = json_encode($value);
                                        }
                                        $items[] = "<strong>{$key}:</strong> " . htmlspecialchars((string)$value);
                                    }
                                    return implode('<br>', $items);
                                } catch (\Exception $e) {
                                    return 'Error displaying data: ' . $e->getMessage();
                                }
                            })
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('headers')
                            ->label('Request Headers')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) return 'No headers';
                                if (!is_array($state)) return 'Invalid header format';
                                
                                try {
                                    $items = [];
                                    foreach ($state as $key => $value) {
                                        if (is_array($value)) {
                                            $value = implode(', ', $value);
                                        }
                                        $items[] = "<strong>{$key}:</strong> " . htmlspecialchars((string)$value);
                                    }
                                    return implode('<br>', $items);
                                } catch (\Exception $e) {
                                    return 'Error displaying headers: ' . $e->getMessage();
                                }
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBotDetections::route('/'),
            'view' => Pages\ViewBotDetection::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
