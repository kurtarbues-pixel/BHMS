<?php

namespace Database\Factories;

use App\Models\Inquiry;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inquiry>
 */
class InquiryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'tenant_id' => User::factory()->tenant(),
            'message' => 'I am interested in renting this room starting next month.',
            'status' => Inquiry::PENDING,
        ];
    }
}
