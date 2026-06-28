<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Application\DTO\CompanyCreateDto;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Model\TaxStatus;
use App\Company\Domain\Repository\CompanyRepositoryInterface;

final class CompanyCreator
{
    public function __construct(private readonly CompanyRepositoryInterface $companyRepository) {}

    public function execute(CompanyCreateDto $input): void
    {
        if ($this->companyRepository->findById($input->id) !== null) {
            // TODO: delegate to EditCompany use case
            return;
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
