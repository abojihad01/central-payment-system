<?php

namespace App\Filament\Resources\CronJobResource\Pages;

use App\Filament\Resources\CronJobResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCronJob extends CreateRecord
{
    protected static string $resource = CronJobResource::class;

    protected function afterCreate(): void
    {
        // Calculate next run time after creating
        $this->record->calculateNextRun();
    }
}