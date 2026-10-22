<?php

declare(strict_types=1);

namespace App\Company\Domain\Service;

final readonly class RefreshTokenClaims
{
    public function __construct(
        public string $userId,
        public string $jti,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
