<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    public const UNPAID = 'unpaid';
    public const PARTIAL = 'partial';
    public const PAID = 'paid';
    public const OVERDUE = 'overdue';

    protected $fillable = [
        'lease_id',
        'billing_period',
        'base_rent',
        'additional_charges_total',
        'total_amount_due',
        'amount_paid',
        'status',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'base_rent' => 'decimal:2',
            'additional_charges_total' => 'decimal:2',
            'total_amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(PendingCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function remainingBalance(): string
    {
        return bcsub((string) $this->total_amount_due, (string) $this->amount_paid, 2);
    }

    public function refreshPaymentStatus(): void
    {
        $paid = (float) $this->amount_paid;
        $due = (float) $this->total_amount_due;

        if ($paid <= 0) {
            $this->status = $this->due_date->isPast() ? self::OVERDUE : self::UNPAID;
        } elseif ($paid + 0.001 >= $due) {
            $this->status = self::PAID;
        } else {
            $this->status = self::PARTIAL;
        }

        $this->save();
    }
}
