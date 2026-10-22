<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Application\DTO\CompanyUserLoginResultDto;
use App\Company\Application\DTO\RefreshTokenDto;
use App\Company\Domain\Exception\InvalidRefreshTokenException;
use App\Company\Domain\Model\RevokedToken;
use App\Company\Domain\Repository\RevokedTokenRepositoryInterface;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Company\Domain\Service\AuthTokenGeneratorInterface;
use App\Company\Domain\Service\RefreshTokenReaderInterface;

final class CompanyUserTokenRefresher
{
    public function __construct(
        private readonly RefreshTokenReaderInterface     $refreshTokenReader,
        private readonly UserRepositoryInterface         $userRepository,
        private readonly RevokedTokenRepositoryInterface $revokedTokenRepository,
        private readonly AuthTokenGeneratorInterface     $authTokenGenerator,
    ) {}

    public function execute(RefreshTokenDto $input): CompanyUserLoginResultDto
    {
        $claims = $this->refreshTokenReader->read($input->refreshToken);

        $user = $this->userRepository->findById($claims->userId);
        if ($user === null || !$user->isActive()) {
            throw new InvalidRefreshTokenException();
        }

        // Rotation: a refresh token is single-use, closing the window for replay if it leaked.
        $this->revokedTokenRepository->save(new RevokedToken($claims->jti, $claims->expiresAt));

        return new CompanyUserLoginResultDto(
            $user,
            $user->getCompany(),
            $this->authTokenGenerator->generateAccessTokenFor($user),
            $this->authTokenGenerator->generateRefreshTokenFor($user),
        );
    }
}
