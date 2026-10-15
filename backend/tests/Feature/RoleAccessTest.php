<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_cannot_create_a_room(): void
    {
        $tenant = User::factory()->tenant()->create();
        Sanctum::actingAs($tenant);

        $this->postJson('/api/rooms', [
            'room_number' => '101',
            'title' => 'Test Room',
            'location' => 'Manila',
            'price' => 5000,
        ])->assertForbidden();
    }

    public function test_landlord_cannot_update_another_landlords_room(): void
    {
        $owner = User::factory()->landlord()->create();
        $other = User::factory()->landlord()->create();
        $room = Room::factory()->create(['landlord_id' => $owner->id]);

        Sanctum::actingAs($other);
        $this->putJson("/api/rooms/{$room->id}", [
            'title' => 'Hijacked',
            'location' => 'Manila',
            'price' => 1,
            'room_number' => '999',
        ])->assertForbidden();
    }

    public function test_tenant_cannot_list_admin_users(): void
    {
        Sanctum::actingAs(User::factory()->tenant()->create());
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong',
            ]);
        }

        $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ])->assertStatus(429);
    }
}
