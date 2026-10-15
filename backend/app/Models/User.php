<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'role_id',
        'is_active',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'landlord_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'tenant_id');
    }

    public function tenantLeases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_id');
    }

    public function landlordLeases(): HasMany
    {
        return $this->hasMany(Lease::class, 'landlord_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === Role::ADMIN;
    }

    public function isLandlord(): bool
    {
        return in_array($this->role?->name, [Role::LANDLORD, Role::STAFF], true);
    }

    public function isStaff(): bool
    {
        return $this->role?->name === Role::STAFF;
    }

    public function isTenant(): bool
    {
        return $this->role?->name === Role::TENANT;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role?->name, $roles, true);
    }

    public function toAuthArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role?->name,
            'is_active' => $this->is_active,
        ];
    }
}
