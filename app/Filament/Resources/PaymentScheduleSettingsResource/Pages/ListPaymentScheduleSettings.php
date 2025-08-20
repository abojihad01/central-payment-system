<?php

namespace App\Filament\Resources\PaymentScheduleSettingsResource\Pages;

use App\Filament\Resources\PaymentScheduleSettingsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentScheduleSettings extends ListRecords
{
    protected static string $resource = PaymentScheduleSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}