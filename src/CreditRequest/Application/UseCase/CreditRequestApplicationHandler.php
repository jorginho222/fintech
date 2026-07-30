<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\Company\Domain\Exception\CompanyNotFoundException;
use App\Company\Domain\Repository\CompanyRepositoryInterface;
use App\CreditRequest\Application\DTO\CreditRequestApplyDto;
use App\CreditRequest\Domain\Model\CreditRequestApplication;

final class CreditRequestApplicationHandler
{
    public function __construct(
        private readonly CompanyRepositoryInterface                $companyRepository,
        private readonly CreditRequestApplicationCreator            $creditRequestApplicationCreator,
        private readonly CreditRequestApplicationSimulationService  $creditRequestApplicationSimulationService,
    ) {}

    public function execute(CreditRequestApplyDto $dto): CreditRequestApplication
    {
        $company = $this->companyRepository->findById($dto->companyId);
        if ($company === null) {
            throw new CompanyNotFoundException();
        }

        $creditRequestApplication = $this->creditRequestApplicationCreator->execute($company, $dto->amount, $dto->installmentQuantity);

        $this->creditRequestApplicationSimulationService->execute($creditRequestApplication);

        return $creditRequestApplication;
    }
}
