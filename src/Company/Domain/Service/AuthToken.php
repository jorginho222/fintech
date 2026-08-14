<?php

declare(strict_types=1);

namespace App\Company\Domain\Service;

final readonly class AuthToken
{
    public function __construct(
        public string $value,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
