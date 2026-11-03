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

    /**
     * @return CreditRequestApplication|null null when the decision was ignored, because the application was already decided
     */
    public function execute(CreditRequestApplicationResultDto $dto): ?CreditRequestApplication
    {
        $creditRequestApplication = $this->creditRequestApplicationRepository->findById($dto->applicationId);
        if ($creditRequestApplication === null) {
            throw new CreditRequestApplicationNotFoundException();
        }

        if (!$creditRequestApplication->isEvaluationPending()) {
            return null;
        }

        $this->creditRequestApplicationUpdater->execute($creditRequestApplication, $dto->decision);
        $this->companyUpdater->execute($creditRequestApplication->getCompany(), (int) $dto->decision->score);

        if ($dto->decision->status === 'APPROVED') {
            $creditRequest = $this->creditRequestCreator->execute(
                $creditRequestApplication->getCompany(),
                $creditRequestApplication->getReference(),
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
