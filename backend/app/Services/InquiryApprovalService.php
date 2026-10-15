<?php

namespace App\Services;

use App\Mail\InquiryApprovedMail;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\Notification;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InquiryApprovalService
{
    public function approve(Inquiry $inquiry): Lease
    {
        $lease = DB::transaction(function () use ($inquiry) {
            $inquiry = Inquiry::whereKey($inquiry->id)->lockForUpdate()->firstOrFail();

            if (! $inquiry->canTransitionTo(Inquiry::CONVERTED) && ! $inquiry->canTransitionTo(Inquiry::APPROVED)) {
                throw ValidationException::withMessages([
                    'status' => 'This inquiry cannot be approved from its current state.',
                ]);
            }

            $room = Room::whereKey($inquiry->room_id)->lockForUpdate()->firstOrFail();

            $taken = Lease::where('active_room_id', $room->id)->lockForUpdate()->exists();
            if ($taken || $room->status === 'occupied') {
                throw new ConflictHttpException('This room was just taken.');
            }

            $lease = Lease::create([
                'room_id' => $room->id,
                'tenant_id' => $inquiry->tenant_id,
                'landlord_id' => $room->landlord_id,
                'inquiry_id' => $inquiry->id,
                'start_date' => now()->toDateString(),
                'monthly_rent' => $room->price,
                'status' => 'active',
                'active_room_id' => $room->id,
            ]);

            $room->update(['status' => 'occupied']);

            $inquiry->update(['status' => Inquiry::CONVERTED]);

            Inquiry::where('room_id', $room->id)
                ->where('id', '!=', $inquiry->id)
                ->where('status', Inquiry::PENDING)
                ->update(['status' => Inquiry::REJECTED]);

            return $lease->fresh(['room', 'tenant', 'landlord']);
        });

        $inquiry = $inquiry->fresh(['tenant', 'room']);

        Mail::to($inquiry->tenant->email)->send(new InquiryApprovedMail($inquiry, $lease));

        Notification::create([
            'user_id' => $inquiry->tenant_id,
            'title' => 'Inquiry approved',
            'body' => 'Your inquiry for '.$inquiry->room->title.' was approved and a lease was created.',
        ]);

        ActivityLogger::log('inquiry.approved', $inquiry, ['lease_id' => $lease->id]);

        return $lease;
    }

    public function reject(Inquiry $inquiry): Inquiry
    {
        if (! $inquiry->canTransitionTo(Inquiry::REJECTED)) {
            throw ValidationException::withMessages([
                'status' => 'This inquiry cannot be rejected from its current state.',
            ]);
        }

        $inquiry->update(['status' => Inquiry::REJECTED]);

        ActivityLogger::log('inquiry.rejected', $inquiry);

        return $inquiry->fresh();
    }
}
