<?php

namespace App\Filament\Resources\PaymentScheduleSettingsResource\Pages;

use App\Filament\Resources\PaymentScheduleSettingsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaymentScheduleSettings extends EditRecord
{
    protected static string $resource = PaymentScheduleSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}