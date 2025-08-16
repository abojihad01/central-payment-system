<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BotProtectionSettingsResource\Pages;
use App\Models\BotProtectionSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TagsInput;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class BotProtectionSettingsResource extends Resource
{
    protected static ?string $model = BotProtectionSettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Bot Protection Settings';

    protected static ?string $modelLabel = 'Bot Protection Setting';

    protected static ?string $pluralModelLabel = 'Bot Protection Settings';

    protected static ?string $navigationGroup = 'Security';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->label('Setting Key')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->disabled(fn ($record) => $record && $record->exists),
                Forms\Components\Select::make('type')
                    ->label('Data Type')
                    ->options([
                        'string' => 'String',
                        'boolean' => 'Boolean',
                        'integer' => 'Integer',
                        'json' => 'JSON Array'
                    ])
                    ->required()
                    ->reactive()
                    ->disabled(fn ($record) => $record && $record->exists),
                Forms\Components\Select::make('category')
                    ->label('Category')
                    ->options([
                        'general' => 'General',
                        'rate_limiting' => 'Rate Limiting',
                        'bot_detection' => 'Bot Detection',
                        'honeypot' => 'Honeypot Protection',
                        'recaptcha' => 'reCAPTCHA'
                    ])
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Is Active')
                    ->default(true),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->columnSpanFull(),
                
                // Dynamic value field based on type
                Forms\Components\TextInput::make('value')
                    ->label('Value')
                    ->required()
                    ->visible(fn (Forms\Get $get) => in_array($get('type'), ['string', 'integer']))
                    ->numeric(fn (Forms\Get $get) => $get('type') === 'integer')
                    ->columnSpanFull(),
                
                Forms\Components\Toggle::make('boolean_value')
                    ->label('Boolean Value')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'boolean')
                    ->reactive()
                    ->afterStateUpdated(fn (Forms\Set $set, $state) => $set('value', $state ? '1' : '0')),
                
                Forms\Components\TagsInput::make('json_value')
                    ->label('JSON Array Values')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'json')
                    ->reactive()
                    ->afterStateUpdated(fn (Forms\Set $set, $state) => $set('value', json_encode($state ?: []))),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('category')
            ->groups([
                Tables\Grouping\Group::make('category')
                    ->label('Category')
                    ->collapsible(),
            ])
            ->heading(function () {
                $enabled = BotProtectionSettings::get('protection_enabled', true);
                $status = $enabled ? 'ENABLED' : 'DISABLED';
                return "Bot Protection Settings ({$status})";
            })
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Setting Key')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\BadgeColumn::make('category')
                    ->label('Category')
                    ->colors([
                        'primary' => 'general',
                        'warning' => 'rate_limiting',
                        'danger' => 'bot_detection',
                        'info' => 'honeypot',
                        'success' => 'recaptcha'
                    ])
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'gray' => 'string',
                        'success' => 'boolean',
                        'info' => 'integer',
                        'warning' => 'json'
                    ]),
                Tables\Columns\TextColumn::make('formatted_value')
                    ->label('Value')
                    ->getStateUsing(function (BotProtectionSettings $record) {
                        return match($record->type) {
                            'boolean' => $record->value ? 'Enabled' : 'Disabled',
                            'json' => is_array($record->value) ? implode(', ', $record->value) : 'Invalid JSON',
                            default => (string) $record->value
                        };
                    })
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $record = $column->getRecord();
                        if ($record->type === 'json' && is_array($record->value)) {
                            return json_encode($record->value, JSON_PRETTY_PRINT);
                        }
                        return null;
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    })
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Category')
                    ->options([
                        'general' => 'General',
                        'rate_limiting' => 'Rate Limiting',
                        'bot_detection' => 'Bot Detection',
                        'honeypot' => 'Honeypot Protection',
                        'recaptcha' => 'reCAPTCHA'
                    ]),
                SelectFilter::make('type')
                    ->label('Data Type')
                    ->options([
                        'string' => 'String',
                        'boolean' => 'Boolean',
                        'integer' => 'Integer',
                        'json' => 'JSON Array'
                    ]),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive'
                    ])
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('toggle_active')
                    ->label(fn (BotProtectionSettings $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (BotProtectionSettings $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (BotProtectionSettings $record) => $record->is_active ? 'warning' : 'success')
                    ->action(function (BotProtectionSettings $record) {
                        $record->update(['is_active' => !$record->is_active]);
                        BotProtectionSettings::clearCache();
                        
                        Notification::make()
                            ->title('Setting ' . ($record->is_active ? 'activated' : 'deactivated'))
                            ->success()
                            ->send();
                    }),
            ])
            ->headerActions([
                Action::make('toggle_protection')
                    ->label(function () {
                        $enabled = BotProtectionSettings::get('protection_enabled', true);
                        return $enabled ? 'Disable Bot Protection' : 'Enable Bot Protection';
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
                    ->requiresConfirmation()
                    ->modalDescription(function () {
                        $enabled = BotProtectionSettings::get('protection_enabled', true);
                        return $enabled 
                            ? 'This will completely disable bot protection. Your site will be vulnerable to automated attacks.'
                            : 'This will enable bot protection using your current settings.';
                    }),
                Action::make('seed_defaults')
                    ->label('Seed Default Settings')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function () {
                        BotProtectionSettings::seedDefaults();
                        
                        Notification::make()
                            ->title('Default settings seeded successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalDescription('This will create default bot protection settings. Existing settings will not be overwritten.'),
                Action::make('clear_cache')
                    ->label('Clear Settings Cache')
                    ->icon('heroicon-o-trash')
                    ->color('warning')
                    ->action(function () {
                        BotProtectionSettings::clearCache();
                        
                        Notification::make()
                            ->title('Settings cache cleared')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => true]);
                            }
                            BotProtectionSettings::clearCache();
                        }),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_active' => false]);
                            }
                            BotProtectionSettings::clearCache();
                        }),
                ]),
            ])
            ->emptyStateHeading('No Bot Protection Settings')
            ->emptyStateDescription('Configure bot protection settings to control security behavior.')
            ->emptyStateIcon('heroicon-o-cog-6-tooth');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBotProtectionSettings::route('/'),
            'create' => Pages\CreateBotProtectionSettings::route('/create'),
            'edit' => Pages\EditBotProtectionSettings::route('/{record}/edit'),
        ];
    }

    protected static function afterCreate($record, array $data): void
    {
        // Handle dynamic value setting
        if ($data['type'] === 'boolean' && isset($data['boolean_value'])) {
            $record->update(['value' => $data['boolean_value'] ? '1' : '0']);
        } elseif ($data['type'] === 'json' && isset($data['json_value'])) {
            $record->update(['value' => json_encode($data['json_value'] ?: [])]);
        }
        
        BotProtectionSettings::clearCache();
    }

    protected static function afterSave($record, array $data): void
    {
        BotProtectionSettings::clearCache();
    }
}
