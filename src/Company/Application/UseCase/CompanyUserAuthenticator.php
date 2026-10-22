<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Application\DTO\CompanyUserLoginDto;
use App\Company\Application\DTO\CompanyUserLoginResultDto;
use App\Company\Domain\Exception\InvalidCredentialsException;
use App\Company\Domain\Repository\UserRepositoryInterface;
use App\Company\Domain\Service\AuthTokenGeneratorInterface;
use App\Company\Domain\Service\PasswordHasherInterface;

final class CompanyUserAuthenticator
{
    public function __construct(
        private readonly UserRepositoryInterface      $userRepository,
        private readonly PasswordHasherInterface      $passwordHasher,
        private readonly AuthTokenGeneratorInterface  $authTokenGenerator,
    ) {}

    public function execute(CompanyUserLoginDto $input): CompanyUserLoginResultDto
    {
        $user = $this->userRepository->findByTaxIdentifier($input->taxIdentifier);

        // A deactivated user gets the same answer as a wrong password, so accounts can't be enumerated.
        if ($user === null || !$user->isActive() || !$this->passwordHasher->verify($user->getHashedPassword(), $input->password)) {
            throw new InvalidCredentialsException();
        }

        return new CompanyUserLoginResultDto(
            $user,
            $user->getCompany(),
            $this->authTokenGenerator->generateAccessTokenFor($user),
            $this->authTokenGenerator->generateRefreshTokenFor($user),
        );
    }
}
