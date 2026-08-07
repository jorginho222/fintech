<?php

declare(strict_types=1);

namespace App\Company\Application\UseCase;

use App\Company\Domain\Model\Company;
use App\Company\Domain\Repository\CompanyRepositoryInterface;

final class CompanyUpdater
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
    ) {}

    public function execute(Company $company, int $score): Company
    {
        $company->changeScore($score);

        $this->companyRepository->save($company);

        return $company;
    }
}
