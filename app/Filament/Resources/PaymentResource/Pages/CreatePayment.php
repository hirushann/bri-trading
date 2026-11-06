<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    /**
     * Split one payment across the selected invoices, oldest first.
     * Each invoice gets amount (then discount) up to its balance; any overpayment lands on the last invoice.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $invoices = Invoice::whereIn('id', $data['invoice_ids'])->orderBy('id')->get();
        unset($data['invoice_ids']);

        $amountLeft = (float) $data['amount'];
        $discountLeft = (float) ($data['discount'] ?? 0);

        return DB::transaction(function () use ($invoices, $data, &$amountLeft, &$discountLeft) {
            $first = null;

            foreach ($invoices as $i => $invoice) {
                $isLast = $i === $invoices->count() - 1;
                $balance = (float) $invoice->balance_due;

                $pay = $isLast ? $amountLeft : min($amountLeft, $balance);
                $discount = $isLast ? $discountLeft : min($discountLeft, max($balance - $pay, 0));
                $amountLeft -= $pay;
                $discountLeft -= $discount;

                // Skip invoices the money didn't reach, but always record at least one payment.
                if ($pay <= 0 && $discount <= 0 && $first) {
                    continue;
                }

                $payment = Payment::create([...$data, 'invoice_id' => $invoice->id, 'amount' => $pay, 'discount' => $discount]);
                $first ??= $payment;
            }

            return $first;
        });
    }
}
