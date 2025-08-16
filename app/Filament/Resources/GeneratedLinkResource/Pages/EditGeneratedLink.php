<?php

namespace App\Filament\Resources\GeneratedLinkResource\Pages;

use App\Filament\Resources\GeneratedLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGeneratedLink extends EditRecord
{
    protected static string $resource = GeneratedLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
