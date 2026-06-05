<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use stdClass;
use UnexpectedValueException;

final class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds,
        private readonly string $algorithm,
    ) {
    }

    /**
     * @return array{token: string, expires_in: int}
     */
    public function generateToken(int $userId, string $email): array
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + $this->ttlSeconds;

        $payload = [
            'sub' => $userId,
            'email' => $email,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
        ];

        $token = JWT::encode($payload, $this->secret, $this->algorithm);

        return [
            'token' => $token,
            'expires_in' => $this->ttlSeconds,
        ];
    }

    public function decode(string $token): stdClass
    {
        return JWT::decode($token, new Key($this->secret, $this->algorithm));
    }

    public function isValid(string $token): bool
    {
        try {
            $this->decode($token);

            return true;
        } catch (ExpiredException | SignatureInvalidException | UnexpectedValueException) {
            return false;
        }
    }
}
