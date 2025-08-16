<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentGatewayResource\Pages;
use App\Filament\Resources\PaymentGatewayResource\RelationManagers;
use App\Models\PaymentGateway;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentGatewayResource extends Resource
{
    protected static ?string $model = PaymentGateway::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationGroup = 'إدارة الدفع';
    
    protected static ?string $navigationLabel = 'بوابات الدفع';
    
    protected static ?string $modelLabel = 'بوابة دفع';
    
    protected static ?string $pluralModelLabel = 'بوابات الدفع';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('المعلومات الأساسية')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->label('اسم البوابة (بالإنجليزية)')
                            ->helperText('مثل: stripe, paypal')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('display_name')
                            ->required()
                            ->label('الاسم المعروض')
                            ->helperText('مثل: Stripe, PayPal')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('الوصف')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('logo_url')
                            ->label('رابط الشعار')
                            ->url()
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('الإعدادات')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('مفعّلة')
                            ->default(true)
                            ->required(),
                        Forms\Components\TextInput::make('priority')
                            ->label('الأولوية')
                            ->helperText('رقم أعلى = أولوية أكبر')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Forms\Components\Section::make('العملات والبلدان المدعومة')
                    ->schema([
                        Forms\Components\TagsInput::make('supported_currencies')
                            ->label('العملات المدعومة')
                            ->helperText('اتركه فارغ لدعم جميع العملات')
                            ->placeholder('USD, EUR, SAR'),
                        Forms\Components\TagsInput::make('supported_countries')
                            ->label('البلدان المدعومة')
                            ->helperText('اتركه فارغ لدعم جميع البلدان')
                            ->placeholder('US, SA, AE'),
                    ])->columns(2),

                Forms\Components\Section::make('الإعدادات المتقدمة')
                    ->schema([
                        Forms\Components\KeyValue::make('configuration')
                            ->label('إعدادات إضافية')
                            ->keyLabel('المفتاح')
                            ->valueLabel('القيمة')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_url')
                    ->label('الشعار')
                    ->circular()
                    ->defaultImageUrl('/images/default-gateway.png'),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('اسم البوابة')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم التقني')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('الحالة')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('priority')
                    ->label('الأولوية')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('accounts_count')
                    ->label('عدد الحسابات')
                    ->counts('accounts')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('successful_transactions')
                    ->label('المعاملات الناجحة')
                    ->numeric()
                    ->sortable()
                    ->color('success'),
                Tables\Columns\TextColumn::make('success_rate')
                    ->label('معدل النجاح')
                    ->formatStateUsing(fn (string $state): string => number_format($state, 1) . '%')
                    ->color(fn (string $state): string => match (true) {
                        $state >= 95 => 'success',
                        $state >= 80 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('مفعّلة')
                    ->falseLabel('معطّلة'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_status')
                    ->label('تغيير الحالة')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (PaymentGateway $record) {
                        $record->update(['is_active' => !$record->is_active]);
                    })
                    ->color('warning'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\AccountsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentGateways::route('/'),
            'create' => Pages\CreatePaymentGateway::route('/create'),
            'edit' => Pages\EditPaymentGateway::route('/{record}/edit'),
        ];
    }
}