<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Domain\Model\RevokedToken;
use App\Company\Domain\Repository\RevokedTokenRepositoryInterface;
use App\Company\Domain\Service\CurrentAuthTokenProviderInterface;

final class CompanyLogout
{
    public function __construct(
        private readonly CurrentAuthTokenProviderInterface $currentAuthTokenProvider,
        private readonly RevokedTokenRepositoryInterface   $revokedTokenRepository,
    ) {}

    public function execute(): void
    {
        $this->revokedTokenRepository->save(new RevokedToken(
            $this->currentAuthTokenProvider->getJti(),
            $this->currentAuthTokenProvider->getExpiresAt(),
        ));
    }
}
