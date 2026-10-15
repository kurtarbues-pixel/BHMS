<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\InvoiceGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function generate(Request $request, InvoiceGenerator $generator)
    {
        $data = $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
        ]);

        $landlordId = $request->user()->isAdmin() ? null : $request->user()->id;

        return response()->json($generator->generate($data['period'] ?? null, $landlordId));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Invoice::query()->with(['lease.room', 'lease.tenant.role', 'charges', 'payments']);

        if ($user->isTenant()) {
            $query->whereHas('lease', fn ($q) => $q->where('tenant_id', $user->id));
        } elseif ($user->isLandlord()) {
            $query->whereHas('lease', fn ($q) => $q->where('landlord_id', $user->id));
        } elseif (! $user->isAdmin()) {
            abort(403);
        }

        return InvoiceResource::collection($query->latest()->paginate(20));
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $invoice->load(['lease.room', 'lease.tenant.role', 'charges', 'payments']);

        return new InvoiceResource($invoice);
    }

    public function current(Request $request)
    {
        $user = $request->user();
        $period = now()->format('Y-m');

        $query = Invoice::query()
            ->with(['lease.room', 'charges', 'payments'])
            ->where('billing_period', $period);

        if ($user->isTenant()) {
            $query->whereHas('lease', fn ($q) => $q->where('tenant_id', $user->id)->where('status', 'active'));
        } else {
            abort(403);
        }

        $invoice = $query->first();

        return $invoice
            ? new InvoiceResource($invoice)
            : response()->json(['data' => null]);
    }

    public function pay(StorePaymentRequest $request, Invoice $invoice)
    {
        $this->authorize('pay', $invoice);

        if ($invoice->status === Invoice::PAID) {
            throw ValidationException::withMessages([
                'invoice' => 'This invoice is already paid.',
            ]);
        }

        $amount = $request->validated('amount');
        $remaining = (float) $invoice->remainingBalance();
        if ($amount - $remaining > 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'Payment exceeds the remaining balance of '.$remaining.'.',
            ]);
        }

        DB::transaction(function () use ($request, $invoice, $amount) {
            Payment::create([
                'invoice_id' => $invoice->id,
                'recorded_by' => $request->user()->id,
                'amount' => $amount,
                'method' => $request->validated('method') ?? 'cash',
                'notes' => $request->validated('notes'),
                'paid_at' => now(),
            ]);

            $invoice->amount_paid = bcadd((string) $invoice->amount_paid, (string) $amount, 2);
            $invoice->refreshPaymentStatus();
        });

        ActivityLogger::log('payment.recorded', $invoice->fresh(), ['amount' => $amount]);

        return new InvoiceResource($invoice->fresh(['lease.room', 'charges', 'payments']));
    }
}
