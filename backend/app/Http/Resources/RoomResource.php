<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_number' => $this->room_number,
            'title' => $this->title,
            'type' => $this->type,
            'description' => $this->description,
            'location' => $this->location,
            'price' => $this->price,
            'amenities' => $this->amenities ?? [],
            'status' => $this->status,
            'landlord' => $this->whenLoaded('landlord', fn () => [
                'id' => $this->landlord->id,
                'name' => $this->landlord->name,
                'email' => $request->user() ? $this->landlord->email : null,
                'phone' => $request->user() ? $this->landlord->phone : null,
            ]),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->url,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
