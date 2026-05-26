<?php

namespace Tests\Feature;

use App\Enums\HoldStatus;
use App\Models\Hold;
use App\Models\Slot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotBookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_jwt_token(): void
    {
        $user = User::factory()->create([
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'token_type',
                'access_token',
                'expires_in',
                'user' => ['id', 'name', 'email'],
            ])
            ->assertJson([
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_availability_returns_slots(): void
    {
        $this->createSlot(capacity: 5);
        $this->createSlot(capacity: 10, startOffsetHours: 3, endOffsetHours: 4);

        $this->getJson('/api/slots/availability')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment([
                'capacity' => 5,
                'remaining' => 5,
            ])
            ->assertJsonFragment([
                'capacity' => 10,
                'remaining' => 10,
            ]);
    }

    public function test_hold_creation_succeeds_and_reduces_remaining(): void
    {
        $user = User::factory()->create();
        $slot = $this->createSlot(capacity: 5);

        $response = $this->actingAsJwt($user)->postJson(
            "/api/slots/{$slot->id}/hold",
            ['quantity' => 2],
            ['Idempotency-Key' => 'hold-success-1'],
        );

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'slot_id' => $slot->id,
                'user_id' => $user->id,
                'status' => 'held',
                'quantity' => 2,
            ]);

        $slot->refresh();

        $this->assertSame(2, $slot->held_count);
        $this->assertSame(0, $slot->confirmed_count);
        $this->assertSame(3, $slot->remaining());
    }

    public function test_hold_is_idempotent_for_same_user_and_key(): void
    {
        $user = User::factory()->create();
        $slot = $this->createSlot(capacity: 5);

        $first = $this->actingAsJwt($user)->postJson(
            "/api/slots/{$slot->id}/hold",
            ['quantity' => 2],
            ['Idempotency-Key' => 'same-key-1'],
        );

        $second = $this->actingAsJwt($user)->postJson(
            "/api/slots/{$slot->id}/hold",
            ['quantity' => 2],
            ['Idempotency-Key' => 'same-key-1'],
        );

        $first->assertCreated();
        $second->assertCreated();
        $second->assertJson([
            'id' => $first->json('id'),
            'slot_id' => $slot->id,
            'status' => 'held',
            'quantity' => 2,
        ]);

        $this->assertDatabaseCount('holds', 1);

        $slot->refresh();
        $this->assertSame(2, $slot->held_count);
        $this->assertSame(3, $slot->remaining());
    }

    public function test_hold_returns_conflict_when_capacity_is_exhausted(): void
    {
        $user = User::factory()->create();
        $slot = $this->createSlot(capacity: 2);

        $response = $this->actingAsJwt($user)->postJson(
            "/api/slots/{$slot->id}/hold",
            ['quantity' => 3],
            ['Idempotency-Key' => 'oversell-1'],
        );

        $response
            ->assertStatus(409)
            ->assertJsonFragment([
                'message' => 'Slot capacity is exhausted.',
            ]);

        $this->assertDatabaseCount('holds', 0);
        $slot->refresh();
        $this->assertSame(0, $slot->held_count);
        $this->assertSame(2, $slot->remaining());
    }

    public function test_same_user_can_create_multiple_holds_for_same_slot_with_different_keys(): void
    {
        $user = User::factory()->create();
        $slot = $this->createSlot(capacity: 5);

        $first = $this->actingAsJwt($user)->postJson(
            "/api/slots/{$slot->id}/hold",
            ['quantity' => 1],
            ['Idempotency-Key' => 'multi-hold-1'],
        );

        $second = $this->actingAsJwt($user)->postJson(
            "/api/slots/{$slot->id}/hold",
            ['quantity' => 1],
            ['Idempotency-Key' => 'multi-hold-2'],
        );

        $first->assertCreated();
        $second->assertCreated();
        $this->assertNotSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('holds', 2);

        $slot->refresh();
        $this->assertSame(2, $slot->held_count);
        $this->assertSame(3, $slot->remaining());
    }

    public function test_confirm_transfers_held_count_to_confirmed_count(): void
    {
        $user = User::factory()->create();
        $slot = $this->createSlot(capacity: 5, heldCount: 2);
        $hold = Hold::query()->create([
            'slot_id' => $slot->id,
            'user_id' => $user->id,
            'status' => HoldStatus::Held,
            'quantity' => 2,
            'idempotency_key' => 'confirm-1',
            'expires_at' => CarbonImmutable::now()->addMinutes(5),
        ]);

        $this->actingAsJwt($user)
            ->postJson("/api/holds/{$hold->id}/confirm")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $hold->id,
                'status' => 'confirmed',
            ]);

        $slot->refresh();
        $hold->refresh();

        $this->assertSame(HoldStatus::Confirmed, $hold->status);
        $this->assertSame(0, $slot->held_count);
        $this->assertSame(2, $slot->confirmed_count);
        $this->assertSame(3, $slot->remaining());
    }

    public function test_confirm_expired_hold_returns_conflict_and_releases_capacity(): void
    {
        $user = User::factory()->create();
        $slot = $this->createSlot(capacity: 5, heldCount: 2);
        $hold = Hold::query()->create([
            'slot_id' => $slot->id,
            'user_id' => $user->id,
            'status' => HoldStatus::Held,
            'quantity' => 2,
            'idempotency_key' => 'expired-1',
            'expires_at' => CarbonImmutable::now()->subMinute(),
        ]);

        $this->actingAsJwt($user)
            ->postJson("/api/holds/{$hold->id}/confirm")
            ->assertStatus(409)
            ->assertJsonFragment([
                'message' => 'Hold expired.',
            ]);

        $this->assertDatabaseHas('holds', [
            'id' => $hold->id,
            'status' => HoldStatus::Expired->value,
        ]);

        $this->assertDatabaseHas('slots', [
            'id' => $slot->id,
            'held_count' => 0,
            'confirmed_count' => 0,
        ]);

        $this->getJson('/api/slots/availability')
            ->assertOk()
            ->assertJsonFragment([
                'slot_id' => $slot->id,
                'remaining' => 5,
            ]);
    }

    public function test_cancel_held_hold_returns_capacity(): void
    {
        $user = User::factory()->create();
        $slot = $this->createSlot(capacity: 5, heldCount: 1);
        $hold = Hold::query()->create([
            'slot_id' => $slot->id,
            'user_id' => $user->id,
            'status' => HoldStatus::Held,
            'quantity' => 1,
            'idempotency_key' => 'cancel-1',
            'expires_at' => CarbonImmutable::now()->addMinutes(5),
        ]);

        $this->actingAsJwt($user)
            ->deleteJson("/api/holds/{$hold->id}")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $hold->id,
                'status' => 'cancelled',
            ]);

        $slot->refresh();
        $hold->refresh();

        $this->assertSame(HoldStatus::Cancelled, $hold->status);
        $this->assertSame(0, $slot->held_count);
        $this->assertSame(5, $slot->remaining());
    }

    public function test_protected_hold_routes_require_bearer_token(): void
    {
        $slot = $this->createSlot(capacity: 5);

        $this->postJson("/api/slots/{$slot->id}/hold", [
            'quantity' => 1,
        ], [
            'Idempotency-Key' => 'unauthorized-1',
        ])
            ->assertStatus(401)
            ->assertJsonFragment([
                'message' => 'Missing bearer token.',
            ]);
    }

    private function actingAsJwt(User $user): self
    {
        $token = app(\App\Services\Auth\JwtService::class)->issueToken($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function createSlot(
        int $capacity,
        int $heldCount = 0,
        int $confirmedCount = 0,
        int $startOffsetHours = 1,
        int $endOffsetHours = 2,
    ): Slot {
        return Slot::query()->create([
            'capacity' => $capacity,
            'held_count' => $heldCount,
            'confirmed_count' => $confirmedCount,
            'start_at' => CarbonImmutable::now()->addHours($startOffsetHours),
            'end_at' => CarbonImmutable::now()->addHours($endOffsetHours),
        ]);
    }
}
