<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\Service\CurrentAuthTokenProviderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

final class JwtCurrentAuthTokenProvider implements CurrentAuthTokenProviderInterface
{
    public function __construct(
        private readonly TokenStorageInterface     $tokenStorage,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
    ) {}

    public function getJti(): string
    {
        $jti = $this->payload()['jti'] ?? null;

        if (!is_string($jti) || $jti === '') {
            throw new AuthenticationCredentialsNotFoundException('The authenticated token has no identifier.');
        }

        return $jti;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        $exp = $this->payload()['exp'] ?? null;

        if (!is_int($exp)) {
            throw new AuthenticationCredentialsNotFoundException('The authenticated token has no expiration.');
        }

        return (new \DateTimeImmutable())->setTimestamp($exp);
    }

    private function payload(): array
    {
        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            throw new AuthenticationCredentialsNotFoundException('An authenticated company is required.');
        }

        $payload = $this->jwtTokenManager->decode($token);

        return is_array($payload) ? $payload : [];
    }
}
