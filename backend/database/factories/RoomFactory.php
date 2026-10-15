<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'landlord_id' => User::factory()->landlord(),
            'room_number' => (string) fake()->unique()->numberBetween(100, 999),
            'title' => fake()->words(3, true).' Room',
            'type' => fake()->randomElement(['Bedspace', 'Single', 'Shared Twin', 'Studio', 'Family']),
            'description' => fake()->sentence(),
            'location' => fake()->randomElement(['Sampaloc, Manila', 'Quezon City', 'Makati', 'Pasig']),
            'price' => fake()->randomElement([3500, 4500, 6500, 8000, 12000]),
            'amenities' => ['WiFi', 'Own bath'],
            'status' => 'available',
        ];
    }
}
