<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\PendingCharge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceGenerator
{
    public function generate(?string $period = null, ?int $landlordId = null): array
    {
        $period = $period ?: now()->format('Y-m');
        $periodStart = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $dueDate = $periodStart->copy()->addMonth()->addDays(4)->toDateString();

        $created = 0;
        $updated = 0;

        $leases = Lease::query()
            ->where('status', 'active')
            ->when($landlordId, fn ($query) => $query->where('landlord_id', $landlordId))
            ->whereDate('start_date', '<=', $periodEnd)
            ->where(function ($q) use ($periodStart) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $periodStart);
            })
            ->get();

        foreach ($leases as $lease) {
            DB::transaction(function () use ($lease, $period, $dueDate, &$created, &$updated) {
                $locked = Lease::whereKey($lease->id)->lockForUpdate()->first();

                $invoice = Invoice::where('lease_id', $locked->id)
                    ->where('billing_period', $period)
                    ->lockForUpdate()
                    ->first();

                $pending = PendingCharge::where('lease_id', $locked->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->get();

                $pendingTotal = (string) $pending->sum(fn ($c) => (float) $c->amount);

                if ($invoice && $invoice->status === Invoice::PAID) {
                    return;
                }

                if (! $invoice) {
                    $total = bcadd((string) $locked->monthly_rent, $pendingTotal, 2);
                    $invoice = Invoice::create([
                        'lease_id' => $locked->id,
                        'billing_period' => $period,
                        'base_rent' => $locked->monthly_rent,
                        'additional_charges_total' => $pendingTotal,
                        'total_amount_due' => $total,
                        'amount_paid' => 0,
                        'status' => Invoice::UNPAID,
                        'due_date' => $dueDate,
                    ]);
                    $created++;
                } else {
                    $additional = bcadd((string) $invoice->additional_charges_total, $pendingTotal, 2);
                    $invoice->update([
                        'additional_charges_total' => $additional,
                        'total_amount_due' => bcadd((string) $invoice->base_rent, $additional, 2),
                    ]);
                    $updated++;
                }

                if ($pending->isNotEmpty()) {
                    PendingCharge::whereIn('id', $pending->pluck('id'))
                        ->update([
                            'status' => 'billed',
                            'invoice_id' => $invoice->id,
                        ]);
                }
            });
        }

        return [
            'period' => $period,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    public function markOverdue(): int
    {
        return Invoice::query()
            ->whereIn('status', [Invoice::UNPAID])
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => Invoice::OVERDUE]);
    }
}
