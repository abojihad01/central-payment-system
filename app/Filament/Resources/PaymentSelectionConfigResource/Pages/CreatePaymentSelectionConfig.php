<?php

namespace App\Filament\Resources\PaymentSelectionConfigResource\Pages;

use App\Filament\Resources\PaymentSelectionConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentSelectionConfig extends CreateRecord
{
    protected static string $resource = PaymentSelectionConfigResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Handle account weights from repeater
        if (isset($data['account_weights_repeater'])) {
            $weights = [];
            foreach ($data['account_weights_repeater'] as $item) {
                if (!empty($item['account_id']) && !empty($item['weight'])) {
                    $weights[$item['account_id']] = (int) $item['weight'];
                }
            }
            $data['account_weights'] = $weights;
            unset($data['account_weights_repeater']);
        }

        // Handle account priorities from repeater
        if (isset($data['account_priorities_repeater'])) {
            $priorities = [];
            foreach ($data['account_priorities_repeater'] as $item) {
                if (!empty($item['account_id']) && !empty($item['priority'])) {
                    $priorities[$item['account_id']] = (int) $item['priority'];
                }
            }
            $data['account_priorities'] = $priorities;
            unset($data['account_priorities_repeater']);
        }

        // Parse strategy_config JSON
        if (!empty($data['strategy_config']) && is_string($data['strategy_config'])) {
            $config = json_decode($data['strategy_config'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['strategy_config'] = $config;
            } else {
                $data['strategy_config'] = null;
            }
        }

        return $data;
    }
}