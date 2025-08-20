<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Resources\PaymentResource\RelationManagers;
use App\Jobs\ProcessPendingPayment;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('generated_link_id')
                    ->relationship('generatedLink', 'id')
                    ->required(),
                Forms\Components\TextInput::make('payment_gateway')
                    ->required(),
                Forms\Components\TextInput::make('gateway_payment_id')
                    ->required(),
                Forms\Components\TextInput::make('gateway_session_id'),
                Forms\Components\TextInput::make('amount')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('currency')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('customer_email')
                    ->email()
                    ->required(),
                Forms\Components\TextInput::make('customer_phone')
                    ->tel(),
                Forms\Components\Textarea::make('gateway_response')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('paid_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('generatedLink.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_gateway')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gateway_payment_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gateway_session_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
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
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('payment_gateway')
                    ->options([
                        'stripe' => 'Stripe',
                        'paypal' => 'PayPal',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('process_payment')
                    ->label('معالجة الدفعة')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (Payment $record) => $record->status === 'pending')
                    ->action(function (Payment $record) {
                        // Dispatch job to process payment
                        ProcessPendingPayment::dispatch($record);
                        
                        Notification::make()
                            ->title('تم إرسال الدفعة للمعالجة')
                            ->body("Payment ID: {$record->id} تم إضافتها لقائمة المعالجة")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('معالجة الدفعة المعلقة')
                    ->modalDescription('هل تريد معالجة هذه الدفعة الآن؟')
                    ->modalSubmitActionLabel('نعم، معالجة الآن'),
                    
                Tables\Actions\Action::make('verify_payment')
                    ->label('التحقق من حالة الدفعة')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->visible(fn (Payment $record) => $record->status === 'pending' && !empty($record->gateway_session_id))
                    ->action(function (Payment $record) {
                        try {
                            // Get payment account and Stripe client
                            $paymentAccount = $record->paymentAccount;
                            if (!$paymentAccount || !isset($paymentAccount->credentials['secret_key'])) {
                                throw new \Exception('Stripe credentials not found');
                            }
                            
                            $stripe = new \Stripe\StripeClient($paymentAccount->credentials['secret_key']);
                            
                            if (str_starts_with($record->gateway_session_id, 'cs_')) {
                                $session = $stripe->checkout->sessions->retrieve($record->gateway_session_id);
                                
                                Notification::make()
                                    ->title('حالة الدفعة في Stripe')
                                    ->body("Session Status: {$session->status}, Payment Status: {$session->payment_status}")
                                    ->info()
                                    ->send();
                            } elseif (str_starts_with($record->gateway_payment_id, 'pi_')) {
                                $intent = $stripe->paymentIntents->retrieve($record->gateway_payment_id);
                                
                                Notification::make()
                                    ->title('حالة الدفعة في Stripe')
                                    ->body("Payment Intent Status: {$intent->status}")
                                    ->info()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('خطأ في التحقق')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('process_pending')
                        ->label('معالجة الدفعات المحددة')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $processedCount = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'pending') {
                                    ProcessPendingPayment::dispatch($record);
                                    $processedCount++;
                                }
                            }
                            
                            Notification::make()
                                ->title('تم إرسال الدفعات للمعالجة')
                                ->body("تم إضافة {$processedCount} دفعة لقائمة المعالجة")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('معالجة الدفعات المعلقة')
                        ->modalDescription('هل تريد معالجة جميع الدفعات المحددة؟')
                        ->modalSubmitActionLabel('نعم، معالجة الآن'),
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
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
