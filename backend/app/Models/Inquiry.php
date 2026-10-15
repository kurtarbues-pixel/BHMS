<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inquiry extends Model
{
    use HasFactory;

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const CONVERTED = 'converted';

    protected $fillable = [
        'room_id',
        'tenant_id',
        'message',
        'status',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function lease(): HasOne
    {
        return $this->hasOne(Lease::class);
    }

    public function canTransitionTo(string $target): bool
    {
        $allowed = [
            self::PENDING => [self::APPROVED, self::REJECTED, self::CONVERTED],
            self::APPROVED => [self::CONVERTED, self::REJECTED],
            self::REJECTED => [],
            self::CONVERTED => [],
        ];

        return in_array($target, $allowed[$this->status] ?? [], true);
    }
}
