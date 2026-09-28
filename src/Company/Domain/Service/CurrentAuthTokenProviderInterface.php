<?php

declare(strict_types=1);

namespace App\Company\Domain\Service;

/**
 * Reads identity claims from the JWT authenticating the current request.
 */
interface CurrentAuthTokenProviderInterface
{
    public function getJti(): string;

    public function getExpiresAt(): \DateTimeImmutable;
}
