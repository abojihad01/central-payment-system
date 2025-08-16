<?php

namespace App\Filament\Resources\CustomerEventResource\Pages;

use App\Filament\Resources\CustomerEventResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerEvent extends CreateRecord
{
    protected static string $resource = CustomerEventResource::class;
}
