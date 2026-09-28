<?php

declare(strict_types=1);

namespace App\Company\Domain\Service;

use App\Company\Domain\Exception\InvalidRefreshTokenException;

interface RefreshTokenReaderInterface
{
    /**
     * @throws InvalidRefreshTokenException if the token is malformed, expired, revoked, or not a refresh token
     */
    public function read(string $refreshToken): RefreshTokenClaims;
}
