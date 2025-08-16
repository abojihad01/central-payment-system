<?php

namespace App\Filament\Pages;

use App\Models\Website;
use App\Models\Plan;
use App\Services\PaymentLinkService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class GeneratePaymentLink extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    
    protected static ?string $navigationGroup = 'إدارة الدفع';
    
    protected static ?string $navigationLabel = 'إنشاء رابط دفع';
    
    protected static ?string $title = 'إنشاء رابط دفع جديد';

    protected static string $view = 'filament.pages.generate-payment-link';

    public ?array $data = [];
    
    public ?string $generatedLink = null;
    public ?array $linkData = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات الرابط')
                    ->schema([
                        Forms\Components\Select::make('website_id')
                            ->label('اختر الموقع')
                            ->options(Website::where('is_active', true)->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('plan_id', null)),
                            
                        Forms\Components\Select::make('plan_id')
                            ->label('اختر الباقة')
                            ->options(function (Forms\Get $get) {
                                $websiteId = $get('website_id');
                                if (!$websiteId) {
                                    return [];
                                }
                                return Plan::where('website_id', $websiteId)
                                    ->where('is_active', true)
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->live(),
                    ])->columns(2),

                Forms\Components\Section::make('روابط التحويل')
                    ->schema([
                        Forms\Components\TextInput::make('success_url')
                            ->label('رابط النجاح')
                            ->url()
                            ->required()
                            ->helperText('الرابط الذي سيتم التحويل إليه عند نجاح الدفع'),
                            
                        Forms\Components\TextInput::make('failure_url')
                            ->label('رابط الفشل')
                            ->url()
                            ->required()
                            ->helperText('الرابط الذي سيتم التحويل إليه عند فشل الدفع'),
                    ])->columns(2),

                Forms\Components\Section::make('الإعدادات الإضافية')
                    ->schema([
                        Forms\Components\TextInput::make('expiry_minutes')
                            ->label('مدة انتهاء الصلاحية (بالدقائق)')
                            ->numeric()
                            ->helperText('اتركه فارغاً للحصول على رابط دائم'),
                            
                        Forms\Components\Toggle::make('single_use')
                            ->label('للاستخدام مرة واحدة')
                            ->default(false)
                            ->helperText('إذا كان مفعّل، سيصبح الرابط غير صالح بعد أول استخدام'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function generateLink(): void
    {
        $data = $this->form->getState();
        
        try {
            $service = new PaymentLinkService();
            
            $this->linkData = $service->generatePaymentLink(
                websiteId: $data['website_id'],
                planId: $data['plan_id'],
                successUrl: $data['success_url'],
                failureUrl: $data['failure_url'],
                expiryMinutes: $data['expiry_minutes'] ?: null,
                singleUse: $data['single_use'] ?? false
            );
            
            $this->generatedLink = $this->linkData['payment_link'];
            
            Notification::make()
                ->title('تم إنشاء الرابط بنجاح!')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('خطأ في إنشاء الرابط')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function copyToClipboard(): void
    {
        Notification::make()
            ->title('تم نسخ الرابط!')
            ->body($this->generatedLink)
            ->info()
            ->send();
    }

    public function resetForm(): void
    {
        $this->generatedLink = null;
        $this->linkData = null;
        $this->form->fill();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('generate')
                ->label('إنشاء رابط الدفع')
                ->icon('heroicon-o-plus')
                ->action('generateLink')
                ->color('primary')
                ->size('lg'),
                
            Action::make('reset')
                ->label('إعادة تعيين')
                ->icon('heroicon-o-arrow-path')
                ->action('resetForm')
                ->color('gray')
                ->outlined(),
        ];
    }
}