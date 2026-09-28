<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Application\DTO\CompanyLogoutDto;
use App\Company\Domain\Exception\InvalidRefreshTokenException;
use App\Company\Domain\Model\RevokedToken;
use App\Company\Domain\Repository\RevokedTokenRepositoryInterface;
use App\Company\Domain\Service\CurrentAuthTokenProviderInterface;
use App\Company\Domain\Service\RefreshTokenReaderInterface;

final class CompanyLogout
{
    public function __construct(
        private readonly CurrentAuthTokenProviderInterface $currentAuthTokenProvider,
        private readonly RevokedTokenRepositoryInterface   $revokedTokenRepository,
        private readonly RefreshTokenReaderInterface       $refreshTokenReader,
    ) {}

    public function execute(CompanyLogoutDto $input): void
    {
        $this->revokedTokenRepository->save(new RevokedToken(
            $this->currentAuthTokenProvider->getJti(),
            $this->currentAuthTokenProvider->getExpiresAt(),
        ));

        if ($input->refreshToken === null) {
            return;
        }

        try {
            $claims = $this->refreshTokenReader->read($input->refreshToken);
        } catch (InvalidRefreshTokenException) {
            // Best-effort: an already-invalid refresh token needs no revoking.
            return;
        }

        $this->revokedTokenRepository->save(new RevokedToken($claims->jti, $claims->expiresAt));
    }
}
