<?php

namespace App\Services\Slot;

use App\Enums\HoldStatus;
use App\Models\Hold;
use App\Models\Slot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class SlotService
{
    private const AVAILABILITY_CACHE_KEY = 'slots.availability.v1';
    private const AVAILABILITY_LOCK_KEY = 'slots.availability.lock';

    /**
     * @return array<int, array{slot_id:int, capacity:int, remaining:int}>
     */
    public function getAvailability(): array
    {
        $store = $this->cacheStore();

        if ($store !== 'redis') {
            return Cache::store($store)->remember(
                self::AVAILABILITY_CACHE_KEY,
                $this->availabilityTtlSeconds(),
                fn (): array => $this->freshAvailability(),
            );
        }

        try {
            return Cache::store($store)
                ->lock(self::AVAILABILITY_LOCK_KEY, $this->availabilityLockSeconds())
                ->block($this->availabilityWaitSeconds(), function (): array {
                    return Cache::store($store)->remember(
                        self::AVAILABILITY_CACHE_KEY,
                        $this->availabilityTtlSeconds(),
                        fn (): array => $this->freshAvailability(),
                    );
                });
        } catch (LockTimeoutException) {
            return $this->freshAvailability();
        }
    }

    public function createHold(User $user, int $slotId, int $quantity, string $idempotencyKey): Hold
    {
        if ($quantity <= 0) {
            throw new HoldStateException('Quantity must be greater than zero.');
        }

        try {
            $hold = DB::transaction(function () use ($user, $slotId, $quantity, $idempotencyKey): Hold {
                $existingHold = Hold::query()
                    ->where('user_id', $user->getKey())
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingHold) {
                    return $existingHold;
                }

                /** @var Slot|null $slot */
                $slot = Slot::query()->lockForUpdate()->find($slotId);

                if (! $slot) {
                    throw new NotFoundException('Slot not found.');
                }

                if ($slot->remaining() < $quantity) {
                    throw new CapacityConflictException('Slot capacity is exhausted.');
                }

                $slot->increment('held_count', $quantity);

                return Hold::query()->create([
                    'slot_id' => $slot->getKey(),
                    'user_id' => $user->getKey(),
                    'status' => HoldStatus::Held,
                    'quantity' => $quantity,
                    'idempotency_key' => $idempotencyKey,
                    'expires_at' => CarbonImmutable::now()->addSeconds($this->holdTtlSeconds()),
                ]);
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyViolation($exception)) {
                throw $exception;
            }

            $hold = Hold::query()
                ->where('user_id', $user->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $hold) {
                throw $exception;
            }
        }

        $this->invalidateAvailabilityCache();

        return $hold->fresh(['slot', 'user']) ?? $hold;
    }

    public function confirmHold(User $user, int $holdId): Hold
    {
        DB::beginTransaction();

        try {
            $hold = $this->lockUserHold($user, $holdId);
            $slot = $this->lockSlot($hold->slot_id);

            if ($hold->status === HoldStatus::Expired) {
                DB::commit();

                return $hold->fresh(['slot', 'user']) ?? $hold;
            }

            if ($hold->status === HoldStatus::Confirmed) {
                DB::commit();

                return $hold->fresh(['slot', 'user']) ?? $hold;
            }

            if ($hold->status !== HoldStatus::Held) {
                throw new HoldStateException('Only held reservations can be confirmed.');
            }

            if ($hold->isExpired()) {
                $slot->decrement('held_count', $hold->quantity);
                $hold->forceFill([
                    'status' => HoldStatus::Expired,
                ])->save();

                DB::commit();
                $this->invalidateAvailabilityCache();

                return $hold->fresh(['slot', 'user']) ?? $hold;
            }

            $slot->decrement('held_count', $hold->quantity);
            $slot->increment('confirmed_count', $hold->quantity);
            $hold->forceFill([
                'status' => HoldStatus::Confirmed,
            ])->save();

            DB::commit();
            $this->invalidateAvailabilityCache();

            return $hold->fresh(['slot', 'user']) ?? $hold;
        } catch (Throwable $throwable) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $throwable;
        }
    }

    public function cancelHold(User $user, int $holdId): Hold
    {
        $hold = DB::transaction(function () use ($user, $holdId): Hold {
            $hold = $this->lockUserHold($user, $holdId);

            if ($hold->status === HoldStatus::Cancelled) {
                return $hold;
            }

            if ($hold->status === HoldStatus::Confirmed) {
                throw new HoldStateException('Confirmed holds cannot be cancelled in the base version.');
            }

            if ($hold->status === HoldStatus::Expired) {
                throw new HoldStateException('Expired holds cannot be cancelled.');
            }

            if ($hold->status !== HoldStatus::Held) {
                throw new HoldStateException('Only held reservations can be cancelled.');
            }

            $slot = $this->lockSlot($hold->slot_id);
            $slot->decrement('held_count', $hold->quantity);

            $hold->forceFill([
                'status' => HoldStatus::Cancelled,
            ])->save();

            return $hold->fresh(['slot', 'user']) ?? $hold;
        });

        $this->invalidateAvailabilityCache();

        return $hold;
    }

    private function invalidateAvailabilityCache(): void
    {
        Cache::store($this->cacheStore())->forget(self::AVAILABILITY_CACHE_KEY);
    }

    /**
     * @return array<int, array{slot_id:int, capacity:int, remaining:int}>
     */
    private function freshAvailability(): array
    {
        return Slot::query()
            ->orderBy('start_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Slot $slot): array => [
                'slot_id' => $slot->id,
                'capacity' => $slot->capacity,
                'remaining' => $slot->remaining(),
            ])
            ->all();
    }

    private function lockUserHold(User $user, int $holdId): Hold
    {
        /** @var Hold|null $hold */
        $hold = Hold::query()
            ->whereKey($holdId)
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();

        if (! $hold) {
            throw new NotFoundException('Hold not found.');
        }

        return $hold;
    }

    private function lockSlot(int $slotId): Slot
    {
        /** @var Slot|null $slot */
        $slot = Slot::query()->lockForUpdate()->find($slotId);

        if (! $slot) {
            throw new NotFoundException('Slot not found.');
        }

        return $slot;
    }

    private function cacheStore(): string
    {
        $configuredStore = (string) config('cache.default', 'database');

        if ($configuredStore === 'redis' && ! extension_loaded('redis')) {
            return 'database';
        }

        return $configuredStore;
    }

    private function availabilityTtlSeconds(): int
    {
        return (int) config('booking.availability_cache_ttl_seconds', 10);
    }

    private function availabilityLockSeconds(): int
    {
        return (int) config('booking.availability_lock_seconds', 5);
    }

    private function availabilityWaitSeconds(): int
    {
        return (int) config('booking.availability_wait_seconds', 3);
    }

    private function holdTtlSeconds(): int
    {
        return (int) config('booking.hold_ttl_seconds', 300);
    }

    private function isDuplicateKeyViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;

        return $sqlState === '23000' || $sqlState === '23505' || $driverCode === 1062 || $driverCode === 19;
    }
}
