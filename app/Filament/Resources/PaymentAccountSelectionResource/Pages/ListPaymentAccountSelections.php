<?php

namespace App\Filament\Resources\PaymentAccountSelectionResource\Pages;

use App\Filament\Resources\PaymentAccountSelectionResource;
use App\Models\PaymentAccountSelection;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;

class ListPaymentAccountSelections extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PaymentAccountSelectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - this is analytics only
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PaymentAccountSelectionResource\Widgets\AccountSelectionStatsWidget::class,
        ];
    }

    public function getTitle(): string
    {
        return 'Account Selection Analytics';
    }

    public function getSubheading(): ?string
    {
        return 'Monitor how payment accounts are being selected for transactions';
    }
}