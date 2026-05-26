<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $capacity
 * @property int $held_count
 * @property int $confirmed_count
 * @property \Illuminate\Support\Carbon $start_at
 * @property \Illuminate\Support\Carbon $end_at
 * @property-read Collection<int, Hold> $holds
 */
class Slot extends Model
{
    protected $fillable = [
        'capacity',
        'held_count',
        'confirmed_count',
        'start_at',
        'end_at',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'held_count' => 'integer',
            'confirmed_count' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }

    public function remaining(): int
    {
        return $this->capacity - $this->held_count - $this->confirmed_count;
    }
}
