<?php

declare(strict_types=1);

namespace App\Company\Domain\Model;

final class RevokedToken
{
    public function __construct(
        private readonly string $jti,
        private readonly \DateTimeImmutable $expiresAt,
    ) {
    }

    public function getJti(): string
    {
        return $this->jti;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
