<?php

namespace App\Filament\Resources\FraudRuleResource\Pages;

use App\Filament\Resources\FraudRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFraudRule extends EditRecord
{
    protected static string $resource = FraudRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
