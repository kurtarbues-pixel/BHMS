<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $staffMayManage = $user?->isStaff() && in_array('landlord', $roles, true);

        if (! $user || (! $user->hasRole(...$roles) && ! $staffMayManage)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is deactivated.'], 403);
        }

        return $next($request);
    }
}
