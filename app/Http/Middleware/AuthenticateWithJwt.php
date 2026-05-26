<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\JwtException;
use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithJwt
{
    public function __construct(
        private readonly JwtService $jwtService,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return $this->unauthorized('Missing bearer token.');
        }

        try {
            $payload = $this->jwtService->parseToken($token);
        } catch (JwtException $exception) {
            return $this->unauthorized($exception->getMessage());
        }

        $userId = $payload['sub'] ?? null;

        if (! is_numeric($userId)) {
            return $this->unauthorized('Invalid token subject.');
        }

        $user = User::query()->find($userId);

        if (! $user) {
            return $this->unauthorized('User not found.');
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn (): User => $user);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }
}
