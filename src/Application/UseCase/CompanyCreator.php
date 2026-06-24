<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\DTO\CompanyCreateDto;
use App\Domain\Model\Company;
use App\Domain\Model\TaxStatus;
use App\Domain\Repository\CompanyRepositoryInterface;

final class CompanyCreator
{
    public function __construct(private readonly CompanyRepositoryInterface $companyRepository) {}

    public function execute(CompanyCreateDto $input): void
    {
        if ($this->companyRepository->findById($input->id) !== null) {
            throw new \DomainException("Company with id '{$input->id}' already exists.");
        }

        $company = new Company(
            $input->id,
            $input->socialReason,
            $input->cuit,
            $input->email,
            TaxStatus::from($input->taxStatus),
        );

        $this->companyRepository->save($company);
    }
}
