<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            Actions\Action::make('create_and_print')
                ->label('Create & Print')
                ->action(function () {
                    $this->create();
                    $order = $this->record;
                    if ($order && $order->invoice) {
                         return redirect()->route('invoices.print', $order->invoice);
                    }
                }),
            $this->getCancelFormAction(),
        ];
    }
}
