<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChargeRequest;
use App\Http\Requests\StoreLeaseRequest;
use App\Http\Resources\LeaseResource;
use App\Models\Lease;
use App\Models\PendingCharge;
use App\Models\Room;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaseController extends Controller
{
    public function store(StoreLeaseRequest $request)
    {
        $data = $request->validated();
        $lease = DB::transaction(function () use ($request, $data) {
            $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);
            $tenant = User::with('role')->findOrFail($data['tenant_id']);

            if (! $tenant->isTenant() || ! $tenant->is_active) {
                throw ValidationException::withMessages(['tenant_id' => 'Choose an active tenant account.']);
            }

            if (! $request->user()->isAdmin() && $room->landlord_id !== $request->user()->id) {
                abort(403);
            }

            if ($room->status !== 'available' || Lease::where('active_room_id', $room->id)->exists()) {
                throw ValidationException::withMessages(['room_id' => 'This room is not currently available.']);
            }

            $lease = Lease::create([
                'room_id' => $room->id,
                'tenant_id' => $tenant->id,
                'landlord_id' => $room->landlord_id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'monthly_rent' => $data['monthly_rent'] ?? $room->price,
                'status' => 'active',
                'active_room_id' => $room->id,
            ]);
            $room->update(['status' => 'occupied']);

            return $lease->load(['room.images', 'tenant.role', 'landlord.role', 'charges']);
        });

        ActivityLogger::log('lease.created', $lease);

        return (new LeaseResource($lease))->response()->setStatusCode(201);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Lease::query()->with(['room.images', 'tenant.role', 'landlord.role', 'charges']);

        if ($user->isTenant()) {
            $query->where('tenant_id', $user->id);
        } elseif ($user->isLandlord()) {
            $query->where('landlord_id', $user->id);
        } elseif (! $user->isAdmin()) {
            abort(403);
        }

        return LeaseResource::collection($query->latest()->paginate(20));
    }

    public function end(Request $request, Lease $lease)
    {
        $this->authorize('end', $lease);

        if (! $lease->isActive()) {
            throw ValidationException::withMessages([
                'lease' => 'This lease is already ended.',
            ]);
        }

        $lease->update([
            'status' => 'ended',
            'end_date' => now()->toDateString(),
            'active_room_id' => null,
        ]);
        $lease->room->update(['status' => 'available']);

        ActivityLogger::log('lease.ended', $lease);

        return new LeaseResource($lease->fresh(['room.images', 'tenant.role', 'landlord.role', 'charges']));
    }

    public function storeCharge(StoreChargeRequest $request, Lease $lease)
    {
        $this->authorize('charge', $lease);

        if (! $lease->isActive()) {
            throw ValidationException::withMessages([
                'lease' => 'Charges can only be added to an active lease.',
            ]);
        }

        $uuid = $request->validated('client_uuid');
        if ($uuid) {
            $existing = PendingCharge::where('client_uuid', $uuid)->first();
            if ($existing) {
                return response()->json([
                    'charge' => $existing,
                    'idempotent' => true,
                ]);
            }
        }

        $charge = PendingCharge::create([
            'lease_id' => $lease->id,
            'category' => $request->validated('category'),
            'description' => $request->validated('description'),
            'amount' => $request->validated('amount'),
            'status' => 'pending',
            'client_uuid' => $uuid,
        ]);

        ActivityLogger::log('charge.created', $charge, ['lease_id' => $lease->id]);

        return response()->json(['charge' => $charge], 201);
    }

    public function voidCharge(Request $request, PendingCharge $charge)
    {
        $this->authorize('charge', $charge->lease);

        if ($charge->status !== 'pending') {
            throw ValidationException::withMessages([
                'charge' => 'Only pending charges can be voided.',
            ]);
        }

        $charge->update(['status' => 'voided']);
        ActivityLogger::log('charge.voided', $charge);

        return response()->json(['charge' => $charge->fresh()]);
    }
}
