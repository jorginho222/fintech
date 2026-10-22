<?php

declare(strict_types=1);

namespace App\Company\Infrastructure\Security;

use App\Company\Domain\Model\User;
use App\Company\Domain\Service\AuthToken;
use App\Company\Domain\Service\AuthTokenGeneratorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Uid\Uuid;

final class LexikAuthTokenGenerator implements AuthTokenGeneratorInterface
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly int                      $refreshTokenTtl,
    ) {}

    public function generateAccessTokenFor(User $user): AuthToken
    {
        // No 'exp' override: falls back to the globally configured (short) token_ttl.
        return $this->generate($user, 'access', null);
    }

    public function generateRefreshTokenFor(User $user): AuthToken
    {
        return $this->generate($user, 'refresh', $this->refreshTokenTtl);
    }

    private function generate(User $user, string $type, ?int $ttl): AuthToken
    {
        $payload = [
            // Members act on behalf of their company, so data scoping keeps reading this claim.
            'companyId' => $user->getCompany()->getId(),
            'userId' => $user->getId(),
            'type' => $type,
            // Unique per token so a single session can be revoked via /logout, or rotated
            // via /refresh, without affecting the user's other active tokens.
            'jti' => Uuid::v4()->toRfc4122(),
        ];

        if ($ttl !== null) {
            $payload['exp'] = time() + $ttl;
        }

        $value = $this->jwtTokenManager->createFromPayload(CompanyUser::fromUser($user), $payload);

        return new AuthToken($value, $this->expirationOf($value));
    }

    private function expirationOf(string $token): \DateTimeImmutable
    {
        $payload = $this->jwtTokenManager->parse($token);

        return (new \DateTimeImmutable())->setTimestamp((int) $payload['exp']);
    }
}
