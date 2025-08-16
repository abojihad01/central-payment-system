<?php

namespace App\Filament\Resources\CustomerEventResource\Pages;

use App\Filament\Resources\CustomerEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerEvents extends ListRecords
{
    protected static string $resource = CustomerEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
