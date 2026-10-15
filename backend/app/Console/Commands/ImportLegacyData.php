<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ImportLegacyData extends Command
{
    protected $signature = 'legacy:import';

    protected $description = 'Copy legacy PHP users, tenants, rooms, and reservations into Laravel; safe to re-run.';

    private array $counts = ['users' => 0, 'tenants' => 0, 'rooms' => 0, 'leases' => 0];

    public function handle(): int
    {
        try {
            $source = DB::connection('legacy');
            $schema = $source->getSchemaBuilder();
            if (! $schema->hasTable('guests') || ! $schema->hasTable('rooms') || ! $schema->hasTable('reservations')) {
                $this->error('The configured legacy database does not contain the expected guests, rooms, and reservations tables.');

                return self::FAILURE;
            }

            $this->importLegacyUsers($source, $schema);
            $this->importGuests($source);
            $this->importRooms($source);
            $this->importReservations($source);

            $this->info(sprintf(
                'Legacy import complete: %d user accounts, %d tenant records, %d rooms, and %d leases imported or updated.',
                $this->counts['users'], $this->counts['tenants'], $this->counts['rooms'], $this->counts['leases']
            ));
            $this->warn('Imported former guest accounts are inactive until an administrator sets a password and enables them in User accounts.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Legacy import failed: '.$exception->getMessage());
            $this->line('Check LEGACY_DB_* settings and confirm the legacy MySQL account can read its tables.');

            return self::FAILURE;
        }
    }

    private function importLegacyUsers($source, $schema): void
    {
        if (! $schema->hasTable('users')) return;

        foreach ($source->table('users')->orderBy('id')->get() as $legacy) {
            if ($this->mappedId('users', $legacy->id)) {
                $this->counts['users']++;
                continue;
            }
            $username = trim((string) ($legacy->username ?? ''));
            if ($username === '') continue;

            $roleName = ($legacy->role ?? '') === 'admin' ? Role::ADMIN : Role::STAFF;
            $role = Role::firstOrCreate(['name' => $roleName], ['label' => ucfirst($roleName)]);
            $email = Str::slug($username, '.').'@legacy.invalid';
            $user = User::where('username', $username)->first();
            if (! $user && $username === 'admin') $user = User::where('email', 'admin@bhms.test')->first();
            $user ??= new User;
            $user->name = $user->exists ? $user->name : ucfirst($username);
            $user->username = $username;
            $user->email = $user->exists ? $user->email : $email;
            $user->role_id = $role->id;
            $user->is_active = true;
            if (! empty($legacy->password)) $user->password = $legacy->password;
            elseif (! $user->exists) { $user->password = Str::random(48); $user->is_active = false; }
            $user->save();
            $this->remember('users', $legacy->id, 'user', $user->id);
            $this->counts['users']++;
        }
    }

    private function importGuests($source): void
    {
        $tenantRole = Role::firstOrCreate(['name' => Role::TENANT], ['label' => 'Tenant']);

        foreach ($source->table('guests')->orderBy('id')->get() as $guest) {
            if ($targetId = $this->mappedId('guests', $guest->id)) {
                $this->counts['tenants']++;
                continue;
            }

            $email = trim((string) ($guest->email ?? ''));
            $legacyUsername = 'legacy-guest-'.$guest->id;
            $existing = User::with('role')->where('username', $legacyUsername)->first();
            $existing ??= $email !== '' ? User::with('role')->where('email', $email)->first() : null;
            if ($existing && ! $existing->isTenant()) {
                $email = 'legacy-guest-'.$guest->id.'@legacy.invalid';
                $existing = null;
            }

            if ($existing) {
                $this->remember('guests', $guest->id, 'user', $existing->id);
                $this->counts['tenants']++;
                continue;
            }

            $tenant = $existing ?: new User;
            $tenant->name = trim(($guest->first_name ?? '').' '.($guest->last_name ?? '')) ?: 'Legacy tenant '.$guest->id;
            $tenant->username = $legacyUsername;
            $tenant->email = $email !== '' ? $email : 'legacy-guest-'.$guest->id.'@legacy.invalid';
            $tenant->phone = $guest->phone ?? null;
            $tenant->role_id = $tenantRole->id;
            $tenant->password = Str::random(48);
            $tenant->is_active = false;
            $tenant->save();
            $this->remember('guests', $guest->id, 'user', $tenant->id);
            $this->counts['tenants']++;
        }
    }

    private function importRooms($source): void
    {
        $owner = User::whereHas('role', fn ($query) => $query->where('name', Role::STAFF))->first()
            ?: User::whereHas('role', fn ($query) => $query->where('name', Role::LANDLORD))->first()
            ?: User::whereHas('role', fn ($query) => $query->where('name', Role::ADMIN))->first();
        if (! $owner) throw new \RuntimeException('Seed Laravel roles and an admin or landlord account before importing rooms.');

        foreach ($source->table('rooms')->orderBy('id')->get() as $legacy) {
            if ($this->mappedId('rooms', $legacy->id)) {
                $this->counts['rooms']++;
                continue;
            }
            $room = null;
            $room ??= Room::where('landlord_id', $owner->id)->where('room_number', $legacy->room_number)->first();
            $room ??= new Room;
            $room->landlord_id = $owner->id;
            $room->room_number = $legacy->room_number;
            $room->title = 'Room '.$legacy->room_number;
            $room->type = $legacy->type;
            $room->description = 'Imported from the legacy boarding house system.';
            $room->location = 'Boarding house';
            $room->price = $legacy->price ?? 0;
            $room->amenities = [];
            $room->status = ($legacy->status ?? '') === 'maintenance'
                ? 'maintenance'
                : (Lease::where('room_id', $room->id)->where('status', 'active')->exists() ? 'occupied' : 'available');
            $room->save();
            $this->remember('rooms', $legacy->id, 'room', $room->id);
            $this->counts['rooms']++;
        }
    }

    private function importReservations($source): void
    {
        foreach ($source->table('reservations')->orderBy('id')->get() as $reservation) {
            if ($this->mappedId('reservations', $reservation->id)) {
                $this->counts['leases']++;
                continue;
            }
            $tenantId = $this->mappedId('guests', $reservation->guest_id);
            $roomId = $this->mappedId('rooms', $reservation->room_id);
            if (! $tenantId || ! $roomId) {
                $this->warn("Skipped reservation {$reservation->id}: its tenant or room could not be mapped.");
                continue;
            }

            $room = Room::find($roomId);
            $startDate = $reservation->check_in ?: Carbon::parse($reservation->created_at ?? now())->toDateString();
            $endDate = $reservation->check_out ?: null;
            if ($endDate && $endDate < $startDate) $endDate = null;
            $hasNotEnded = ! $endDate || $endDate >= now()->toDateString();
            $activeRoomId = $hasNotEnded && $room->status !== 'maintenance' && ! Lease::where('active_room_id', $room->id)->exists()
                ? $room->id
                : null;
            $status = $activeRoomId ? 'active' : 'ended';

            $lease = Lease::create([
                'room_id' => $room->id,
                'tenant_id' => $tenantId,
                'landlord_id' => $room->landlord_id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'monthly_rent' => $room->price,
                'status' => $status,
                'active_room_id' => $activeRoomId,
            ]);
            if ($activeRoomId) $room->update(['status' => 'occupied']);
            $this->remember('reservations', $reservation->id, 'lease', $lease->id);
            $this->counts['leases']++;
        }
    }

    private function mappedId(string $sourceTable, int|string $sourceId): ?int
    {
        $map = DB::table('legacy_imports')->where('source_table', $sourceTable)->where('source_id', $sourceId)->first();

        return $map ? (int) $map->target_id : null;
    }

    private function remember(string $sourceTable, int|string $sourceId, string $targetType, int $targetId): void
    {
        DB::table('legacy_imports')->updateOrInsert(
            ['source_table' => $sourceTable, 'source_id' => $sourceId],
            ['target_type' => $targetType, 'target_id' => $targetId, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
