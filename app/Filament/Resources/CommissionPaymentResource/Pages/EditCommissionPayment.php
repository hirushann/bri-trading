<?php

namespace App\Filament\Resources\CommissionPaymentResource\Pages;

use App\Filament\Resources\CommissionPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommissionPayment extends EditRecord
{
    protected static string $resource = CommissionPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
