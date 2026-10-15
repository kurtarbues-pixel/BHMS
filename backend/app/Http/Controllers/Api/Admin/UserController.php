<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:25'],
            'role' => ['required', Rule::in([Role::ADMIN, Role::LANDLORD, Role::STAFF, Role::TENANT])],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $role = Role::where('name', $data['role'])->firstOrFail();
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role_id' => $role->id,
            'password' => $data['password'],
            'is_active' => true,
        ])->load('role');

        ActivityLogger::log('admin.user.created', $user, ['role' => $role->name]);

        return response()->json(['user' => $user->toAuthArray()], 201);
    }

    public function index(Request $request)
    {
        $users = User::with('role')->latest()->paginate(20);

        return response()->json([
            'data' => $users->map(fn (User $user) => $user->toAuthArray())->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function update(UpdateAdminUserRequest $request, User $user)
    {
        $data = $request->validated();

        if ($user->is(auth()->user()) && (($data['role'] ?? Role::ADMIN) !== Role::ADMIN || ($data['is_active'] ?? true) !== true)) {
            return response()->json(['message' => 'You cannot change your own administrator access.'], 422);
        }

        foreach (['name', 'username', 'email', 'phone', 'password'] as $field) {
            if (array_key_exists($field, $data)) {
                $user->{$field} = $data[$field];
            }
        }

        if (isset($data['role'])) {
            $role = Role::where('name', $data['role'])->firstOrFail();
            $user->role_id = $role->id;
        }

        if (array_key_exists('is_active', $data)) {
            $user->is_active = $data['is_active'];
        }

        $user->save();
        $user->load('role');

        $loggedChanges = $data;
        unset($loggedChanges['password']);
        ActivityLogger::log('admin.user.updated', $user, $loggedChanges);

        return response()->json(['user' => $user->toAuthArray()]);
    }
}
