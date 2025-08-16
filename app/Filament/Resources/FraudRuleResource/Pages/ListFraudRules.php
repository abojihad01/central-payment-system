<?php

namespace App\Filament\Resources\FraudRuleResource\Pages;

use App\Filament\Resources\FraudRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFraudRules extends ListRecords
{
    protected static string $resource = FraudRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
