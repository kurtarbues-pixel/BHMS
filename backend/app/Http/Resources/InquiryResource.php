<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'status' => ucfirst($this->status),
            'room' => $this->whenLoaded('room', fn () => new RoomResource($this->room)),
            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant->toAuthArray()),
            'created_at' => $this->created_at,
        ];
    }
}
