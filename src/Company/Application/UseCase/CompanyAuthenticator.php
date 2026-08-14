<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Application\DTO\CompanyLoginDto;
use App\Company\Application\DTO\CompanyLoginResultDto;
use App\Company\Domain\Exception\InvalidCredentialsException;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\Company\Domain\Service\AuthTokenGeneratorInterface;
use App\Company\Domain\Service\PasswordHasherInterface;

final class CompanyAuthenticator
{
    public function __construct(
        private readonly CompanyRepositoryInterface   $companyRepository,
        private readonly PasswordHasherInterface      $passwordHasher,
        private readonly AuthTokenGeneratorInterface  $authTokenGenerator,
    ) {}

    public function execute(CompanyLoginDto $input): CompanyLoginResultDto
    {
        $company = $this->companyRepository->findByCuit($input->cuit);

        if ($company === null || !$this->passwordHasher->verify($company->getHashedPassword(), $input->password)) {
            throw new InvalidCredentialsException();
        }

        return new CompanyLoginResultDto($company, $this->authTokenGenerator->generateFor($company));
    }
}
