<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GeneratedLinkResource\Pages;
use App\Filament\Resources\GeneratedLinkResource\RelationManagers;
use App\Models\GeneratedLink;
use App\Services\PaymentLinkService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;

class GeneratedLinkResource extends Resource
{
    protected static ?string $model = GeneratedLink::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';
    
    protected static ?string $navigationGroup = 'إدارة الدفع';
    
    protected static ?string $navigationLabel = 'الروابط المولدة';
    
    protected static ?string $modelLabel = 'رابط دفع';
    
    protected static ?string $pluralModelLabel = 'روابط الدفع';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('website_id')
                    ->relationship('website', 'name')
                    ->required()
                    ->label('الموقع'),
                Forms\Components\Select::make('plan_id')
                    ->relationship('plan', 'name')
                    ->required()
                    ->label('الباقة'),
                Forms\Components\TextInput::make('token')
                    ->required()
                    ->label('الرمز المميز')
                    ->default(fn () => \Illuminate\Support\Str::random(64)),
                Forms\Components\TextInput::make('success_url')
                    ->required()
                    ->label('رابط النجاح')
                    ->url(),
                Forms\Components\TextInput::make('failure_url')
                    ->required()
                    ->label('رابط الفشل')
                    ->url(),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->label('السعر'),
                Forms\Components\TextInput::make('currency')
                    ->required()
                    ->default('USD')
                    ->label('العملة'),
                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('ينتهي في'),
                Forms\Components\Toggle::make('single_use')
                    ->label('للاستخدام مرة واحدة')
                    ->default(false),
                Forms\Components\Toggle::make('is_used')
                    ->label('تم استخدامه')
                    ->default(false),
                Forms\Components\Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('website.name')
                    ->label('الموقع')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('الباقة')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('payment_link')
                    ->label('رابط الدفع')
                    ->limit(60)
                    ->copyable()
                    ->copyMessage('تم نسخ الرابط!')
                    ->tooltip('اضغط لنسخ الرابط'),
                Tables\Columns\TextColumn::make('price')
                    ->label('السعر')
                    ->money('USD')
                    ->sortable()
                    ->color('success'),
                Tables\Columns\TextColumn::make('currency')
                    ->label('العملة')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\IconColumn::make('single_use')
                    ->label('استخدام واحد')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('warning')
                    ->falseColor('info'),
                Tables\Columns\IconColumn::make('is_used')
                    ->label('مُستخدم')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('ينتهي في')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('لا ينتهي')
                    ->color(fn ($record) => $record?->isExpired() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('website_id')
                    ->label('الموقع')
                    ->relationship('website', 'name')
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->placeholder('الكل')
                    ->trueLabel('نشط')
                    ->falseLabel('غير نشط'),
                Tables\Filters\TernaryFilter::make('is_used')
                    ->label('الاستخدام')
                    ->placeholder('الكل')
                    ->trueLabel('مُستخدم')
                    ->falseLabel('غير مُستخدم'),
            ])
            ->actions([
                Tables\Actions\Action::make('copy_link')
                    ->label('نسخ الرابط')
                    ->icon('heroicon-o-clipboard')
                    ->color('info')
                    ->action(function (GeneratedLink $record) {
                        $link = $record->payment_link;
                        // هذا سيعمل في المتصفح الحديث
                        Notification::make()
                            ->title('تم نسخ الرابط!')
                            ->body($link)
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('view_link')
                    ->label('عرض الرابط')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading('رابط الدفع')
                    ->modalContent(function (GeneratedLink $record) {
                        return view('filament.modals.payment-link', [
                            'link' => $record->payment_link,
                            'record' => $record
                        ]);
                    }),
                Tables\Actions\Action::make('generate_new')
                    ->label('توليد جديد')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (GeneratedLink $record) {
                        $service = new PaymentLinkService();
                        $linkData = $service->generatePaymentLink(
                            websiteId: $record->website_id,
                            planId: $record->plan_id,
                            successUrl: $record->success_url,
                            failureUrl: $record->failure_url,
                            expiryMinutes: $record->expires_at ? 
                                now()->diffInMinutes($record->expires_at) : null,
                            singleUse: $record->single_use
                        );
                        
                        Notification::make()
                            ->title('تم توليد رابط جديد!')
                            ->body($linkData['payment_link'])
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeneratedLinks::route('/'),
            'create' => Pages\CreateGeneratedLink::route('/create'),
            'edit' => Pages\EditGeneratedLink::route('/{record}/edit'),
        ];
    }
}
