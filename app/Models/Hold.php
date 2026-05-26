<?php

namespace App\Models;

use App\Enums\HoldStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $slot_id
 * @property int $user_id
 * @property string $status
 * @property int $quantity
 * @property string $idempotency_key
 * @property \Illuminate\Support\Carbon $expires_at
 * @property-read Slot $slot
 * @property-read User $user
 */
#[Fillable([
    'slot_id',
    'user_id',
    'status',
    'quantity',
    'idempotency_key',
    'expires_at',
])]
class Hold extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'datetime',
            'status' => HoldStatus::class,
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
