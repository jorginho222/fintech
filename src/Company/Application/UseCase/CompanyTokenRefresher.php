<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Application\DTO\CompanyLoginResultDto;
use App\Company\Application\DTO\RefreshTokenDto;
use App\Company\Domain\Exception\InvalidRefreshTokenException;
use App\Company\Domain\Model\RevokedToken;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\Company\Domain\Repository\RevokedTokenRepositoryInterface;
use App\Company\Domain\Service\AuthTokenGeneratorInterface;
use App\Company\Domain\Service\RefreshTokenReaderInterface;

final class CompanyTokenRefresher
{
    public function __construct(
        private readonly RefreshTokenReaderInterface     $refreshTokenReader,
        private readonly CompanyRepositoryInterface      $companyRepository,
        private readonly RevokedTokenRepositoryInterface $revokedTokenRepository,
        private readonly AuthTokenGeneratorInterface     $authTokenGenerator,
    ) {}

    public function execute(RefreshTokenDto $input): CompanyLoginResultDto
    {
        $claims = $this->refreshTokenReader->read($input->refreshToken);

        $company = $this->companyRepository->findById($claims->companyId);
        if ($company === null) {
            throw new InvalidRefreshTokenException();
        }

        // Rotation: a refresh token is single-use, closing the window for replay if it leaked.
        $this->revokedTokenRepository->save(new RevokedToken($claims->jti, $claims->expiresAt));

        return new CompanyLoginResultDto(
            $company,
            $this->authTokenGenerator->generateAccessTokenFor($company),
            $this->authTokenGenerator->generateRefreshTokenFor($company),
        );
    }
}
