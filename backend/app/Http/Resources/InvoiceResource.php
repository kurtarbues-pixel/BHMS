<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'billing_period' => $this->billing_period,
            'base_rent' => $this->base_rent,
            'additional_charges_total' => $this->additional_charges_total,
            'total_amount_due' => $this->total_amount_due,
            'amount_paid' => $this->amount_paid,
            'balance' => $this->remainingBalance(),
            'status' => ucfirst($this->status),
            'due_date' => $this->due_date?->toDateString(),
            'lease' => $this->whenLoaded('lease', fn () => new LeaseResource($this->lease)),
            'charges' => $this->whenLoaded('charges', fn () => $this->charges->map(fn ($charge) => [
                'id' => $charge->id,
                'category' => $charge->category,
                'description' => $charge->description,
                'amount' => $charge->amount,
                'status' => ucfirst($charge->status),
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'notes' => $payment->notes,
                'paid_at' => $payment->paid_at,
            ])),
        ];
    }
}
