<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomSearchRequest;
use App\Http\Requests\StoreRoomImagesRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\ToggleAvailabilityRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Models\RoomImage;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    public function index(RoomSearchRequest $request)
    {
        $user = $request->user();
        $query = Room::query()->with(['images', 'landlord.role'])->filter($request->validated());

        if ($user?->isLandlord()) {
            $query->where('landlord_id', $user->id);
        } elseif (! $user?->isAdmin()) {
            $query->where('status', 'available');
        }

        $rooms = $query->latest()->paginate($request->integer('per_page', 9));

        return RoomResource::collection($rooms);
    }

    public function show(Room $room)
    {
        $room->load(['images', 'landlord.role']);

        return new RoomResource($room);
    }

    public function store(StoreRoomRequest $request)
    {
        $data = $request->validated();
        $data['landlord_id'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'available';
        $data['amenities'] = $data['amenities'] ?? [];

        $exists = Room::where('landlord_id', $request->user()->id)
            ->where('room_number', $data['room_number'])
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'room_number' => 'You already have a room with this number.',
            ]);
        }

        $room = Room::create($data);
        ActivityLogger::log('room.created', $room);

        return (new RoomResource($room->load(['images', 'landlord.role'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateRoomRequest $request, Room $room)
    {
        $this->authorize('update', $room);
        $room->update($request->validated());
        ActivityLogger::log('room.updated', $room);

        return new RoomResource($room->fresh(['images', 'landlord.role']));
    }

    public function destroy(Request $request, Room $room)
    {
        $this->authorize('delete', $room);

        if ($room->leases()->exists()) {
            throw ValidationException::withMessages([
                'room' => 'Cannot delete a room with lease history. Keep the room record for billing and audit history.',
            ]);
        }

        $room->delete();
        ActivityLogger::log('room.deleted', $room);

        return response()->json(['message' => 'Room deleted.']);
    }

    public function toggleAvailability(ToggleAvailabilityRequest $request, Room $room)
    {
        $this->authorize('update', $room);
        if ($room->activeLease()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'End the active lease before changing room availability.',
            ]);
        }
        $room->update(['status' => $request->validated('status')]);
        ActivityLogger::log('room.availability', $room, ['status' => $room->status]);

        return new RoomResource($room->fresh(['images', 'landlord.role']));
    }

    public function uploadImages(StoreRoomImagesRequest $request, Room $room)
    {
        $this->authorize('update', $room);

        $sort = (int) $room->images()->max('sort_order');
        foreach ($request->file('images') as $file) {
            $path = $file->store('rooms/'.$room->id, 'public');
            $sort++;
            RoomImage::create([
                'room_id' => $room->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'sort_order' => $sort,
            ]);
        }

        ActivityLogger::log('room.images', $room);

        return new RoomResource($room->fresh(['images', 'landlord.role']));
    }

    public function destroyImage(Request $request, Room $room, RoomImage $image)
    {
        $this->authorize('update', $room);

        if ($image->room_id !== $room->id) {
            abort(404);
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Image removed.']);
    }

    public function checkAvailability(Request $request, Room $room)
    {
        $hasActive = \App\Models\Lease::where('active_room_id', $room->id)->exists();
        $isAvailable = ($room->status === 'available') && ! $hasActive;

        return response()->json([
            'room_id' => $room->id,
            'room_number' => $room->room_number,
            'status' => $room->status,
            'is_available' => $isAvailable,
        ]);
    }
}

