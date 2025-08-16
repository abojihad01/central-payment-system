<?php

namespace App\Filament\Resources\PaymentAccountSelectionResource\Pages;

use App\Filament\Resources\PaymentAccountSelectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentAccountSelection extends ViewRecord
{
    protected static string $resource = PaymentAccountSelectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No edit/delete actions - this is analytics only
        ];
    }

    public function getTitle(): string
    {
        return 'Account Selection Details';
    }
}