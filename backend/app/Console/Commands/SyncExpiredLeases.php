<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Models\Room;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncExpiredLeases extends Command
{
    protected $signature = 'leases:sync-expired';

    protected $description = 'Automatically end leases that reached their end date and release room availability.';

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();

        $expiredLeases = Lease::query()
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->with('room')
            ->get();

        if ($expiredLeases->isEmpty()) {
            $this->info('No expired leases found.');
            return self::SUCCESS;
        }

        $count = 0;
        $roomsFreed = 0;

        foreach ($expiredLeases as $lease) {
            DB::transaction(function () use ($lease, &$count, &$roomsFreed) {
                $lease->update([
                    'status' => 'ended',
                    'active_room_id' => null,
                ]);
                $count++;

                if ($lease->room) {
                    $hasOtherActive = Lease::where('active_room_id', $lease->room_id)->exists();
                    if (! $hasOtherActive && $lease->room->status === 'occupied') {
                        $lease->room->update(['status' => 'available']);
                        $roomsFreed++;
                    }
                }

                ActivityLogger::log('lease.ended', $lease, ['automated' => true, 'reason' => 'period_expired']);
            });
        }

        $this->info("Successfully synchronized {$count} expired lease(s) and released {$roomsFreed} room(s).");

        return self::SUCCESS;
    }
}

