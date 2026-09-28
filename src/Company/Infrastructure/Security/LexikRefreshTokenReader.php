<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\Exception\InvalidRefreshTokenException;
use App\Company\Domain\Service\RefreshTokenClaims;
use App\Company\Domain\Service\RefreshTokenReaderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final class LexikRefreshTokenReader implements RefreshTokenReaderInterface
{
    public function __construct(private readonly JWTTokenManagerInterface $jwtTokenManager) {}

    public function read(string $refreshToken): RefreshTokenClaims
    {
        try {
            // parse() also runs the revoked-token check (on_jwt_decoded), so an already
            // used/rotated or logged-out refresh token is rejected here as well.
            $payload = $this->jwtTokenManager->parse($refreshToken);
        } catch (JWTDecodeFailureException) {
            throw new InvalidRefreshTokenException();
        }

        $companyId = $payload['companyId'] ?? null;
        $jti       = $payload['jti'] ?? null;
        $exp       = $payload['exp'] ?? null;
        $type      = $payload['type'] ?? null;

        if ($type !== 'refresh' || !is_string($companyId) || $companyId === '' || !is_string($jti) || $jti === '' || !is_int($exp)) {
            throw new InvalidRefreshTokenException();
        }

        return new RefreshTokenClaims($companyId, $jti, (new \DateTimeImmutable())->setTimestamp($exp));
    }
}
