<?php

namespace App\Filament\Resources\CustomerEventResource\Pages;

use App\Filament\Resources\CustomerEventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerEvent extends EditRecord
{
    protected static string $resource = CustomerEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
