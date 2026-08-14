<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Application\DTO\CompanyRegistrationDto;
use App\Company\Domain\Exception\CompanyCuitAlreadyRegisteredException;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\Company\Domain\Service\PasswordHasherInterface;

final class CompanyRegistrator
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly PasswordHasherInterface    $passwordHasher,
    ) {}

    public function execute(CompanyRegistrationDto $input): void
    {
        if ($this->companyRepository->findByCuit($input->cuit) !== null) {
            throw new CompanyCuitAlreadyRegisteredException();
        }

        $company = new Company(
            $input->id,
            $input->socialReason,
            $input->cuit,
            $input->email,
            TaxStatus::from($input->taxStatus),
            $this->passwordHasher->hash($input->password),
        );

        $this->companyRepository->save($company);
    }
}
