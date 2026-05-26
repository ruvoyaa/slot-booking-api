<?php

namespace App\Http\Controllers;

use App\Enums\HoldStatus;
use App\Models\Hold;
use App\Models\User;
use App\Services\Slot\CapacityConflictException;
use App\Services\Slot\HoldStateException;
use App\Services\Slot\NotFoundException;
use App\Services\Slot\SlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HoldController extends Controller
{
    public function __construct(
        private readonly SlotService $slotService,
    ) {
    }

    public function store(Request $request, int $slotId): JsonResponse
    {
        $payload = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || trim($idempotencyKey) === '') {
            return response()->json([
                'message' => 'Idempotency-Key header is required.',
            ], 422);
        }

        try {
            $hold = $this->slotService->createHold(
                $this->user($request),
                $slotId,
                (int) $payload['quantity'],
                $idempotencyKey,
            );
        } catch (NotFoundException $exception) {
            return $this->error($exception->getMessage(), 404);
        } catch (CapacityConflictException $exception) {
            return $this->error($exception->getMessage(), 409);
        } catch (HoldStateException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return response()->json($this->serializeHold($hold), 201);
    }

    public function confirm(Request $request, int $holdId): JsonResponse
    {
        try {
            $hold = $this->slotService->confirmHold($this->user($request), $holdId);
        } catch (NotFoundException $exception) {
            return $this->error($exception->getMessage(), 404);
        } catch (CapacityConflictException $exception) {
            return $this->error($exception->getMessage(), 409);
        } catch (HoldStateException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        if ($hold->status === HoldStatus::Expired) {
            return $this->error('Hold expired.', 409);
        }

        return response()->json($this->serializeHold($hold));
    }

    public function destroy(Request $request, int $holdId): JsonResponse
    {
        try {
            $hold = $this->slotService->cancelHold($this->user($request), $holdId);
        } catch (NotFoundException $exception) {
            return $this->error($exception->getMessage(), 404);
        } catch (CapacityConflictException $exception) {
            return $this->error($exception->getMessage(), 409);
        } catch (HoldStateException $exception) {
            return $this->error($exception->getMessage(), 422);
        }

        return response()->json($this->serializeHold($hold));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHold(Hold $hold): array
    {
        return [
            'id' => $hold->id,
            'slot_id' => $hold->slot_id,
            'user_id' => $hold->user_id,
            'status' => $hold->status->value,
            'quantity' => $hold->quantity,
            'idempotency_key' => $hold->idempotency_key,
            'expires_at' => $hold->expires_at->toIso8601String(),
            'created_at' => $hold->created_at?->toIso8601String(),
            'updated_at' => $hold->updated_at?->toIso8601String(),
        ];
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], $status);
    }
}
