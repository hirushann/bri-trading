<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $guarded = [];

    public const STATUSES = ['pending' => 'Pending', 'cleared' => 'Cleared', 'bounced' => 'Bounced'];

    public const STATUS_COLORS = ['pending' => 'warning', 'cleared' => 'success', 'bounced' => 'danger'];

    protected $casts = [
        'transaction_date' => 'date',
        'cheque_date' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }

    /**
     * 10% commission for the rep on money the business keeps: a bounced cheque earns nothing, and money owed
     * back to the customer (invoice credit) is taken off the newest payments first.
     * Only commissions nothing has been paid on are changed or removed; paid ones are left as they are.
     */
    public function syncCommission(): void
    {
        $base = $this->status === 'bounced' ? 0 : (float) $this->amount;

        if ($base > 0 && $this->invoice_id && $invoice = Invoice::find($this->invoice_id)) {
            $credit = $invoice->credit();
            foreach ($invoice->payments()->where('status', '!=', 'bounced')->orderByDesc('transaction_date')->orderByDesc('id')->get() as $payment) {
                if ($payment->id === $this->id) {
                    $base = max(0, $base - $credit);
                    break;
                }
                $credit = max(0, $credit - $payment->amount);
            }
        }

        $amount = round($base * 0.10, 2);
        $existing = Commission::where('payment_id', $this->id)->first();

        if (! $existing) {
            if ($this->sales_rep_id && $amount > 0) {
                Commission::create(['user_id' => $this->sales_rep_id, 'payment_id' => $this->id, 'amount' => $amount, 'status' => 'pending']);
            }

            return;
        }

        // Older commissions can be marked paid with paid_amount still 0, so check both.
        if ($existing->status !== 'pending' || $existing->paid_amount > 0) {
            return;
        }

        if ($this->sales_rep_id && $amount > 0) {
            $existing->update(['user_id' => $this->sales_rep_id, 'amount' => $amount]);
        } else {
            $existing->delete();
        }
    }
}
