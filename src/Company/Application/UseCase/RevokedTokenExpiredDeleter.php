<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Domain\Repository\RevokedTokenRepositoryInterface;

final class RevokedTokenExpiredDeleter
{
    public function __construct(
        private readonly RevokedTokenRepositoryInterface $revokedTokenRepository,
    ) {}

    public function execute(\DateTimeImmutable $now): int
    {
        // No retention period: once a revoked token's underlying JWT is past its own
        // expiry, the denylist entry serves no purpose (the JWT is already rejected
        // as expired on its own).
        $expiredTokens = $this->revokedTokenRepository->findExpiredBefore($now);

        foreach ($expiredTokens as $revokedToken) {
            $this->revokedTokenRepository->delete($revokedToken);
        }

        return count($expiredTokens);
    }
}
