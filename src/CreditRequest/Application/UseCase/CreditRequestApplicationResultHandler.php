<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\Company\Application\UseCase\CompanyUpdater;
use App\CreditRequest\Application\DTO\CreditRequestApplicationResultDto;
use App\CreditRequest\Domain\Exception\CreditRequestApplicationNotFoundException;
use App\CreditRequest\Domain\Model\CreditRequestApplication;
use App\CreditRequest\Domain\Repository\CreditRequestApplicationRepositoryInterface;

final class CreditRequestApplicationResultHandler
{
    public function __construct(
        private readonly CreditRequestApplicationRepositoryInterface $creditRequestApplicationRepository,
        private readonly CreditRequestApplicationUpdater             $creditRequestApplicationUpdater,
        private readonly CreditRequestCreator                        $creditRequestCreator,
        private readonly CompanyUpdater                              $companyUpdater,
    ) {}

    public function execute(CreditRequestApplicationResultDto $dto): CreditRequestApplication
    {
        $creditRequestApplication = $this->creditRequestApplicationRepository->findById($dto->applicationId);
        if ($creditRequestApplication === null) {
            throw new CreditRequestApplicationNotFoundException();
        }

        $this->creditRequestApplicationUpdater->execute($creditRequestApplication, $dto->decision);
        $this->companyUpdater->execute($creditRequestApplication->getCompany(), (int) $dto->decision->score);

        if ($dto->decision->status === 'APPROVED') {
            $creditRequest = $this->creditRequestCreator->execute(
                $creditRequestApplication->getCompany(),
                (int) $creditRequestApplication->getAmount(),
                $creditRequestApplication->getInstallmentQuantity(),
                (string) $dto->decision->assignedTna,
            );

            $creditRequestApplication->setCreditRequest($creditRequest);
            $this->creditRequestApplicationRepository->save($creditRequestApplication);
        }

        return $creditRequestApplication;
    }
}
