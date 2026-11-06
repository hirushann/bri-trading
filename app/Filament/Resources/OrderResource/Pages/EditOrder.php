<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Reload after a return so the status field and returns tab are not stale.
            OrderResource::returnAction(Actions\Action::class)
                ->after(fn () => $this->redirect(OrderResource::getUrl('edit', ['record' => $this->record]))),
            Actions\DeleteAction::make(),
            Actions\Action::make('print_invoice')
                ->label('Print Invoice')
                ->icon('heroicon-o-printer')
                ->action(function (Order $record) {
                    if (! $record->invoice) {
                        // Generate invoice if missing
                        \App\Models\Invoice::create([
                            'order_id' => $record->id,
                            'invoice_number' => 'BRI-INV-' . str_pad($record->id, 5, '0', STR_PAD_LEFT),
                            'total_amount' => $record->total_amount,
                            'balance_due' => $record->total_amount,
                            'status' => 'unpaid',
                            'issued_date' => now(),
                        ]);
                        $record->refresh();
                    }
                    // Redirect to print
                    return redirect()->route('invoices.print', $record->invoice);
                }),
        ];
    }
}
