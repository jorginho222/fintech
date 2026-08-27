<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Exception\InstallmentNotFoundException;
use App\CreditRequest\Domain\Model\CreditRequest;
use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;
use App\Shared\Domain\Service\AuthenticatedCompanyIdProviderInterface;

final class InstallmentToPaidUpdater
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface        $creditRequestRepository,
        private readonly CreditRequestPaidChecker                 $creditRequestPaidChecker,
        private readonly AuthenticatedCompanyIdProviderInterface $authenticatedCompanyIdProvider,
    ) {}

    public function execute(string $installmentId): CreditRequest
    {
        $installment = $this->creditRequestRepository->findInstallmentById($installmentId);
        if (
            $installment === null
            || $installment->getCreditRequest()->getCompany()->getId()
                !== $this->authenticatedCompanyIdProvider->getCompanyId()
        ) {
            throw new InstallmentNotFoundException();
        }

        $installment->pay();
        $this->creditRequestRepository->saveInstallment($installment);

        $creditRequest = $installment->getCreditRequest();
        $this->creditRequestPaidChecker->execute($creditRequest);

        return $creditRequest;
    }
}
