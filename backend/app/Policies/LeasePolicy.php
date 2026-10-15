<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;

class LeasePolicy
{
    public function view(User $user, Lease $lease): bool
    {
        return $user->isAdmin()
            || $lease->tenant_id === $user->id
            || $lease->landlord_id === $user->id;
    }

    public function update(User $user, Lease $lease): bool
    {
        return $user->isAdmin() || $lease->landlord_id === $user->id;
    }

    public function end(User $user, Lease $lease): bool
    {
        return $this->update($user, $lease);
    }

    public function charge(User $user, Lease $lease): bool
    {
        return $this->update($user, $lease);
    }
}
