<?php

namespace App\Filament\Resources\BotDetectionResource\Pages;

use App\Filament\Resources\BotDetectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBotDetection extends EditRecord
{
    protected static string $resource = BotDetectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
