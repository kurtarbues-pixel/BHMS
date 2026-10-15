<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        $lease = $invoice->lease;

        return $user->isAdmin()
            || $lease->tenant_id === $user->id
            || $lease->landlord_id === $user->id;
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || $invoice->lease->landlord_id === $user->id;
    }
}
