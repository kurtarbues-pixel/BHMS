<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function landlord(Request $request)
    {
        $user = $request->user();
        $period = now()->format('Y-m');
        $isAdmin = $user->isAdmin();

        $rooms = Room::query()->when(! $isAdmin, fn ($query) => $query->where('landlord_id', $user->id));
        $leases = Lease::query()->when(! $isAdmin, fn ($query) => $query->where('landlord_id', $user->id));
        $inquiries = Inquiry::query()->where('status', Inquiry::PENDING)
            ->when(! $isAdmin, fn ($query) => $query->whereHas('room', fn ($roomsQuery) => $roomsQuery->where('landlord_id', $user->id)));
        $invoices = Invoice::query()->where('billing_period', $period)
            ->when(! $isAdmin, fn ($query) => $query->whereHas('lease', fn ($leasesQuery) => $leasesQuery->where('landlord_id', $user->id)));

        $totalRooms = (clone $rooms)->count();
        $occupied = (clone $rooms)->where('status', 'occupied')->count();
        $available = (clone $rooms)->where('status', 'available')->count();
        $occupancy = $totalRooms > 0 ? round(($occupied / $totalRooms) * 100) : 0;
        $pendingInquiries = $inquiries->count();
        $monthlyRevenue = (clone $invoices)->whereIn('status', [Invoice::PAID, Invoice::PARTIAL])->sum('amount_paid');
        $tenantCount = User::query()->whereHas('role', fn ($query) => $query->where('name', Role::TENANT))
            ->when(! $isAdmin, fn ($query) => $query->whereHas('tenantLeases', fn ($leaseQuery) => $leaseQuery->where('landlord_id', $user->id)))
            ->count();

        return response()->json([
            'occupancy' => [
                'percent' => $occupancy,
                'occupied' => $occupied,
                'total' => $totalRooms,
            ],
            'available_rooms' => $available,
            'tenant_count' => $tenantCount,
            'pending_inquiries' => $pendingInquiries,
            'monthly_revenue' => number_format((float) $monthlyRevenue, 2, '.', ''),
            'active_leases' => $leases->where('status', 'active')->count(),
        ]);
    }
}
