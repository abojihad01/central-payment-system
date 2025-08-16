<?php

namespace App\Filament\Resources\BotProtectionSettingsResource\Pages;

use App\Filament\Resources\BotProtectionSettingsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBotProtectionSettings extends ListRecords
{
    protected static string $resource = BotProtectionSettingsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
