<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $adminRole = Role::where('name', Role::ADMIN)->first();
        $landlordRole = Role::where('name', Role::LANDLORD)->first();
        $tenantRole = Role::where('name', Role::TENANT)->first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@bhms.test'],
            [
                'name' => 'System Admin',
                'username' => 'admin',
                'phone' => '09170000001',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );

        $landlord = User::firstOrCreate(
            ['email' => 'landlord@bhms.test'],
            [
                'name' => 'Maria Santos',
                'username' => 'landlord',
                'phone' => '09171234567',
                'password' => Hash::make('password'),
                'role_id' => $landlordRole->id,
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'tenant@bhms.test'],
            [
                'name' => 'Juan Dela Cruz',
                'username' => 'tenant',
                'phone' => '09179876543',
                'password' => Hash::make('password'),
                'role_id' => $tenantRole->id,
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'tenant2@bhms.test'],
            [
                'name' => 'Ana Reyes',
                'username' => 'tenant2',
                'phone' => '09175551234',
                'password' => Hash::make('password'),
                'role_id' => $tenantRole->id,
                'is_active' => true,
            ]
        );

        if (Room::where('landlord_id', $landlord->id)->count() === 0) {
            $rooms = [
                ['room_number' => '101', 'title' => 'Sunny Bedspace near Campus', 'type' => 'Bedspace', 'location' => 'Sampaloc, Manila', 'price' => 3500, 'amenities' => ['WiFi', 'Electric fan', 'Shared bath']],
                ['room_number' => '102', 'title' => 'Single Room with Window', 'type' => 'Single', 'location' => 'Sampaloc, Manila', 'price' => 6500, 'amenities' => ['WiFi', 'Own bath', 'Study desk']],
                ['room_number' => '201', 'title' => 'Shared Twin for Students', 'type' => 'Shared Twin', 'location' => 'Quezon City', 'price' => 4500, 'amenities' => ['WiFi', 'Two beds', 'Kitchen access']],
                ['room_number' => '202', 'title' => 'Studio with Kitchenette', 'type' => 'Studio', 'location' => 'Makati', 'price' => 12000, 'amenities' => ['WiFi', 'Kitchenette', 'Aircon', 'Own bath']],
                ['room_number' => '301', 'title' => 'Family Room Ground Floor', 'type' => 'Family', 'location' => 'Pasig', 'price' => 15000, 'amenities' => ['WiFi', 'Aircon', 'Two beds', 'Own bath']],
            ];

            foreach ($rooms as $room) {
                Room::create([
                    ...$room,
                    'landlord_id' => $landlord->id,
                    'description' => $room['title'].' listed from the boarding house inventory.',
                    'status' => 'available',
                ]);
            }
        }
    }
}
