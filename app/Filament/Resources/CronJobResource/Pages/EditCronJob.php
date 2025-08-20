<?php

namespace App\Filament\Resources\CronJobResource\Pages;

use App\Filament\Resources\CronJobResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCronJob extends EditRecord
{
    protected static string $resource = CronJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Recalculate next run time after saving changes
        if ($this->record->isDirty(['cron_expression', 'is_active'])) {
            $this->record->calculateNextRun();
        }
    }
}