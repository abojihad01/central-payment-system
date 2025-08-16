<?php

namespace App\Filament\Resources\PaymentSelectionConfigResource\Pages;

use App\Filament\Resources\PaymentSelectionConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentSelectionConfig extends ViewRecord
{
    protected static string $resource = PaymentSelectionConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}