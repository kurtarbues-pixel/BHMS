<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isLandlord(), 403);

        $tenants = User::with('role')
            ->whereHas('role', fn ($query) => $query->where('name', Role::TENANT))
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (User $tenant) => $tenant->toAuthArray());

        return response()->json(['data' => $tenants]);
    }
}
