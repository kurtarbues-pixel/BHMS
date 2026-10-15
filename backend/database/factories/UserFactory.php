<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '09'.fake()->numerify('#########'),
            'role_id' => Role::where('name', Role::TENANT)->value('id'),
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function landlord(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::where('name', Role::LANDLORD)->value('id'),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::where('name', Role::ADMIN)->value('id'),
        ]);
    }

    public function tenant(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::where('name', Role::TENANT)->value('id'),
        ]);
    }
}
