<?php

namespace App\Policies;

use App\Models\Inquiry;
use App\Models\User;

class InquiryPolicy
{
    public function view(User $user, Inquiry $inquiry): bool
    {
        return $user->isAdmin()
            || $inquiry->tenant_id === $user->id
            || $inquiry->room->landlord_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isTenant();
    }

    public function approve(User $user, Inquiry $inquiry): bool
    {
        return $user->isAdmin() || ($user->isLandlord() && $inquiry->room->landlord_id === $user->id);
    }

    public function reject(User $user, Inquiry $inquiry): bool
    {
        return $this->approve($user, $inquiry);
    }
}
