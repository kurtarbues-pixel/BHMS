<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'monthly_rent' => $this->monthly_rent,
            'status' => $this->status,
            'room' => $this->whenLoaded('room', fn () => new RoomResource($this->room)),
            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant->toAuthArray()),
            'landlord' => $this->whenLoaded('landlord', fn () => $this->landlord->toAuthArray()),
            'charges' => $this->whenLoaded('charges', fn () => $this->charges->map(fn ($charge) => [
                'id' => $charge->id,
                'category' => $charge->category,
                'description' => $charge->description,
                'amount' => $charge->amount,
                'status' => $charge->status,
                'invoice_id' => $charge->invoice_id,
                'created_at' => $charge->created_at,
            ])),
        ];
    }
}
