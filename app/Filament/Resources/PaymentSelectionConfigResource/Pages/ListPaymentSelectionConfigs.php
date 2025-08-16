<?php

namespace App\Filament\Resources\PaymentSelectionConfigResource\Pages;

use App\Filament\Resources\PaymentSelectionConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentSelectionConfigs extends ListRecords
{
    protected static string $resource = PaymentSelectionConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}