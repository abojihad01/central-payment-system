<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentAccountResource\Pages;
use App\Filament\Resources\PaymentAccountResource\RelationManagers;
use App\Models\PaymentAccount;
use App\Models\PaymentGateway;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentAccountResource extends Resource
{
    protected static ?string $model = PaymentAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    
    protected static ?string $navigationGroup = 'إدارة الدفع';
    
    protected static ?string $navigationLabel = 'حسابات الدفع';
    
    protected static ?string $modelLabel = 'حساب دفع';
    
    protected static ?string $pluralModelLabel = 'حسابات الدفع';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('المعلومات الأساسية')
                    ->schema([
                        Forms\Components\Select::make('payment_gateway_id')
                            ->relationship('gateway', 'display_name')
                            ->required()
                            ->label('بوابة الدفع')
                            ->preload()
                            ->searchable(),
                        Forms\Components\TextInput::make('account_id')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->label('معرف الحساب')
                            ->helperText('معرف فريد للحساب')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('اسم الحساب')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('الوصف')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('بيانات المصادقة')
                    ->description('بيانات الاتصال مع بوابة الدفع')
                    ->schema([
                        Forms\Components\KeyValue::make('credentials')
                            ->label('بيانات المصادقة')
                            ->keyLabel('المفتاح')
                            ->valueLabel('القيمة')
                            ->helperText('مثل: API Key, Secret Key, etc.')
                            ->columnSpanFull()
                            ->required(),
                    ]),

                Forms\Components\Section::make('الإعدادات')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('مفعّل')
                            ->default(true)
                            ->required(),
                        Forms\Components\Toggle::make('is_sandbox')
                            ->label('بيئة اختبار')
                            ->helperText('فعّل للاختبار، أطفئ للإنتاج')
                            ->default(true)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('الإحصائيات')
                    ->description('هذه الحقول تُحدث تلقائياً')
                    ->schema([
                        Forms\Components\TextInput::make('successful_transactions')
                            ->label('المعاملات الناجحة')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('failed_transactions')
                            ->label('المعاملات الفاشلة')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('total_amount')
                            ->label('إجمالي المبلغ')
                            ->numeric()
                            ->prefix('$')
                            ->default(0.00)
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\DateTimePicker::make('last_used_at')
                            ->label('آخر استخدام')
                            ->disabled()
                            ->dehydrated(false),
                    ])->columns(2),

                Forms\Components\Section::make('إعدادات إضافية')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->label('إعدادات الحساب')
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
                Tables\Columns\TextColumn::make('gateway.display_name')
                    ->label('بوابة الدفع')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم الحساب')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('account_id')
                    ->label('معرف الحساب')
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
                Tables\Columns\IconColumn::make('is_sandbox')
                    ->label('بيئة الاختبار')
                    ->boolean()
                    ->trueIcon('heroicon-o-beaker')
                    ->falseIcon('heroicon-o-globe-alt')
                    ->trueColor('warning')
                    ->falseColor('success'),
                Tables\Columns\TextColumn::make('successful_transactions')
                    ->label('ناجحة')
                    ->numeric()
                    ->sortable()
                    ->color('success')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('failed_transactions')
                    ->label('فاشلة')
                    ->numeric()
                    ->sortable()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('success_rate')
                    ->label('معدل النجاح')
                    ->formatStateUsing(fn (?PaymentAccount $record): string => 
                        $record ? number_format($record->success_rate, 1) . '%' : '0%'
                    )
                    ->color(fn (?PaymentAccount $record): string => 
                        !$record ? 'gray' : match (true) {
                            $record->success_rate >= 95 => 'success',
                            $record->success_rate >= 80 => 'warning',
                            default => 'danger',
                        }
                    ),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('إجمالي المبلغ')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('آخر استخدام')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('لم يُستخدم'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('payment_gateway_id')
                    ->label('بوابة الدفع')
                    ->relationship('gateway', 'display_name')
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('مفعّل')
                    ->falseLabel('معطّل'),
                Tables\Filters\TernaryFilter::make('is_sandbox')
                    ->label('البيئة')
                    ->placeholder('الكل')
                    ->trueLabel('اختبار')
                    ->falseLabel('إنتاج'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_status')
                    ->label('تغيير الحالة')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (PaymentAccount $record) {
                        $record->update(['is_active' => !$record->is_active]);
                    })
                    ->color('warning'),
                Tables\Actions\Action::make('test_connection')
                    ->label('اختبار الاتصال')
                    ->icon('heroicon-o-signal')
                    ->action(function (PaymentAccount $record) {
                        // TODO: Implement connection test
                    })
                    ->color('info'),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('حذف حساب الدفع')
                    ->modalDescription('هل أنت متأكد من حذف هذا الحساب؟ هذا الإجراء لا يمكن التراجع عنه.')
                    ->modalSubmitActionLabel('حذف')
                    ->modalCancelActionLabel('إلغاء'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('حذف حسابات الدفع المحددة')
                        ->modalDescription('هل أنت متأكد من حذف الحسابات المحددة؟ هذا الإجراء لا يمكن التراجع عنه.')
                        ->modalSubmitActionLabel('حذف الكل')
                        ->modalCancelActionLabel('إلغاء'),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('تفعيل المحدد')
                        ->icon('heroicon-o-check-circle')
                        ->action(function ($records) {
                            $records->each->update(['is_active' => true]);
                        })
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('تفعيل الحسابات المحددة')
                        ->modalDescription('سيتم تفعيل جميع الحسابات المحددة.')
                        ->modalSubmitActionLabel('تفعيل')
                        ->modalCancelActionLabel('إلغاء'),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('تعطيل المحدد')
                        ->icon('heroicon-o-x-circle')
                        ->action(function ($records) {
                            $records->each->update(['is_active' => false]);
                        })
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('تعطيل الحسابات المحددة')
                        ->modalDescription('سيتم تعطيل جميع الحسابات المحددة.')
                        ->modalSubmitActionLabel('تعطيل')
                        ->modalCancelActionLabel('إلغاء'),
                    Tables\Actions\BulkAction::make('toggle_sandbox')
                        ->label('تغيير وضع الاختبار')
                        ->icon('heroicon-o-beaker')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update(['is_sandbox' => !$record->is_sandbox]);
                            });
                        })
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('تغيير وضع الاختبار')
                        ->modalDescription('سيتم تغيير وضع الاختبار/الإنتاج لجميع الحسابات المحددة.')
                        ->modalSubmitActionLabel('تغيير')
                        ->modalCancelActionLabel('إلغاء'),
                ]),
            ]);
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
            'index' => Pages\ListPaymentAccounts::route('/'),
            'create' => Pages\CreatePaymentAccount::route('/create'),
            'edit' => Pages\EditPaymentAccount::route('/{record}/edit'),
        ];
    }
}