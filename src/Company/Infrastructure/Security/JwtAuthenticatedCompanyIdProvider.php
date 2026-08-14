<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

final class JwtAuthenticatedCompanyIdProvider implements AuthenticatedCompanyIdProviderInterface
{
    public function __construct(
        private readonly TokenStorageInterface     $tokenStorage,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
    ) {}

    public function getCompanyId(): string
    {
        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            throw new AuthenticationCredentialsNotFoundException('An authenticated company is required.');
        }

        $payload = $this->jwtTokenManager->decode($token);
        $companyId = is_array($payload) ? ($payload['companyId'] ?? null) : null;

        if (!is_string($companyId) || $companyId === '') {
            throw new AuthenticationCredentialsNotFoundException('The authenticated token has no company identifier.');
        }

        return $companyId;
    }
}
