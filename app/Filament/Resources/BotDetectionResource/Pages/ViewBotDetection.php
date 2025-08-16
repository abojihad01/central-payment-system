<?php

namespace App\Filament\Resources\BotDetectionResource\Pages;

use App\Filament\Resources\BotDetectionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewBotDetection extends ViewRecord
{
    protected static string $resource = BotDetectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No actions needed for view page
        ];
    }
}