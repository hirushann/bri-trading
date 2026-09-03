<?php

namespace App\Filament\Resources\CommissionPaymentResource\Pages;

use App\Filament\Resources\CommissionPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommissionPayments extends ListRecords
{
    protected static string $resource = CommissionPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
