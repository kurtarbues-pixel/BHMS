<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $role = Role::where('name', $request->validated('role'))->firstOrFail();

        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => $request->validated('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $user->load('role');
        $token = $user->createToken('api')->plainTextToken;

        ActivityLogger::log('auth.register', $user, [], $user);

        return response()->json([
            'user' => $user->toAuthArray(),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $identifier = $request->validated('identifier') ?: $request->validated('email');
        $user = User::with('role')->where(function ($query) use ($identifier) {
            $query->where('email', $identifier)->orWhere('username', $identifier);
        })->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('api')->plainTextToken;

        ActivityLogger::log('auth.login', $user, [], $user);

        return response()->json([
            'user' => $user->toAuthArray(),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        ActivityLogger::log('auth.logout');

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        $request->user()->load('role');

        return response()->json(['user' => $request->user()->toAuthArray()]);
    }
}
