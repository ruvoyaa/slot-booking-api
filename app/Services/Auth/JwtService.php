<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class JwtService
{
    public function issueToken(User $user): string
    {
        $now = CarbonImmutable::now();

        $payload = [
            'iss' => config('app.url'),
            'sub' => (string) $user->getKey(),
            'iat' => $now->timestamp,
            'exp' => $now->addSeconds($this->ttlSeconds())->timestamp,
        ];

        return $this->encode($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function parseToken(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new JwtException('Malformed token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->decodeJsonPart($encodedHeader);
        $payload = $this->decodeJsonPart($encodedPayload);

        if (Arr::get($header, 'alg') !== 'HS256' || Arr::get($header, 'typ') !== 'JWT') {
            throw new JwtException('Unsupported token header.');
        }

        $expectedSignature = $this->sign($encodedHeader.'.'.$encodedPayload);
        $actualSignature = $this->base64UrlDecode($encodedSignature);

        if (! hash_equals($expectedSignature, $actualSignature)) {
            throw new JwtException('Invalid token signature.');
        }

        $now = CarbonImmutable::now()->timestamp;
        $exp = Arr::get($payload, 'exp');

        if (! is_int($exp) || $exp <= $now) {
            throw new JwtException('Token expired.');
        }

        return $payload;
    }

    public function ttlSeconds(): int
    {
        return (int) config('jwt.ttl_seconds', 3600);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode($this->sign($encodedHeader.'.'.$encodedPayload));

        return implode('.', [$encodedHeader, $encodedPayload, $signature]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonPart(string $value): array
    {
        $decoded = json_decode($this->base64UrlDecode($value), true);

        if (! is_array($decoded)) {
            throw new JwtException('Invalid token payload.');
        }

        return $decoded;
    }

    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->signingKey(), true);
    }

    private function signingKey(): string
    {
        $secret = (string) config('jwt.secret');

        if ($secret === '') {
            throw new JwtException('JWT secret is not configured.');
        }

        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);

            if ($decoded === false) {
                throw new JwtException('JWT secret is not valid base64.');
            }

            return $decoded;
        }

        return $secret;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new JwtException('Invalid base64 token segment.');
        }

        return $decoded;
    }
}
