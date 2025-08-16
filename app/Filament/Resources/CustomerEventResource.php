<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerEventResource\Pages;
use App\Filament\Resources\CustomerEventResource\RelationManagers;
use App\Models\CustomerEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerEventResource extends Resource
{
    protected static ?string $model = CustomerEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    
    protected static ?string $navigationGroup = 'Customer Management';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'id')
                    ->required(),
                Forms\Components\TextInput::make('event_type')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('metadata'),
                Forms\Components\TextInput::make('source')
                    ->maxLength(255),
                Forms\Components\TextInput::make('ip_address')
                    ->maxLength(255),
                Forms\Components\TextInput::make('user_agent')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.customer_id')
                    ->label('Customer ID')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Customer Email')
                    ->searchable()
                    ->copyable(),
                
                Tables\Columns\BadgeColumn::make('event_type')
                    ->label('Event Type')
                    ->searchable()
                    ->colors([
                        'success' => ['customer_created', 'payment_successful', 'subscription_created'],
                        'danger' => ['payment_failed', 'customer_blocked', 'subscription_cancelled'],
                        'warning' => ['login_attempt', 'password_reset'],
                        'primary' => ['profile_updated', 'subscription_upgraded'],
                    ]),
                
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->limit(50),
                
                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->options([
                        'customer_created' => 'Customer Created',
                        'payment_successful' => 'Payment Successful',
                        'payment_failed' => 'Payment Failed', 
                        'subscription_created' => 'Subscription Created',
                        'subscription_cancelled' => 'Subscription Cancelled',
                        'customer_blocked' => 'Customer Blocked',
                        'profile_updated' => 'Profile Updated',
                    ]),
                
                Tables\Filters\SelectFilter::make('source')
                    ->options([
                        'web' => 'Web',
                        'api' => 'API',
                        'admin' => 'Admin',
                        'system' => 'System',
                    ]),
                
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListCustomerEvents::route('/'),
            'create' => Pages\CreateCustomerEvent::route('/create'),
            'edit' => Pages\EditCustomerEvent::route('/{record}/edit'),
        ];
    }
}
