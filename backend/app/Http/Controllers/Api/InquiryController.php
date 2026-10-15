<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInquiryRequest;
use App\Http\Resources\InquiryResource;
use App\Http\Resources\LeaseResource;
use App\Models\Inquiry;
use App\Models\Room;
use App\Services\ActivityLogger;
use App\Services\InquiryApprovalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InquiryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Inquiry::query()->with(['room.images', 'room.landlord.role', 'tenant.role']);

        if ($user->isTenant()) {
            $query->where('tenant_id', $user->id);
        } elseif ($user->isLandlord()) {
            $query->whereHas('room', fn ($q) => $q->where('landlord_id', $user->id));
        } elseif (! $user->isAdmin()) {
            abort(403);
        }

        return InquiryResource::collection($query->latest()->paginate(20));
    }

    public function store(StoreInquiryRequest $request, Room $room)
    {
        if ($room->status !== 'available') {
            throw ValidationException::withMessages([
                'room' => 'This room is not available.',
            ]);
        }

        $existing = Inquiry::where('room_id', $room->id)
            ->where('tenant_id', $request->user()->id)
            ->where('status', Inquiry::PENDING)
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'room' => 'You already have a pending inquiry for this room.',
            ]);
        }

        $inquiry = Inquiry::create([
            'room_id' => $room->id,
            'tenant_id' => $request->user()->id,
            'message' => $request->validated('message'),
            'status' => Inquiry::PENDING,
        ]);

        ActivityLogger::log('inquiry.created', $inquiry);

        return (new InquiryResource($inquiry->load(['room.images', 'tenant.role'])))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(Request $request, Inquiry $inquiry, InquiryApprovalService $service)
    {
        $this->authorize('approve', $inquiry);

        try {
            $lease = $service->approve($inquiry);
        } catch (ConflictHttpException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'room_taken'], 409);
        }

        return response()->json([
            'message' => 'Inquiry approved and lease created.',
            'lease' => new LeaseResource($lease),
            'inquiry' => new InquiryResource($inquiry->fresh(['room.images', 'tenant.role'])),
        ]);
    }

    public function reject(Request $request, Inquiry $inquiry, InquiryApprovalService $service)
    {
        $this->authorize('reject', $inquiry);
        $inquiry = $service->reject($inquiry);

        return new InquiryResource($inquiry->load(['room.images', 'tenant.role']));
    }
}
