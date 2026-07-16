<?php

declare(strict_types=1);

namespace App\CreditRequest\Application\UseCase;

use App\CreditRequest\Domain\Repository\CreditRequestRepositoryInterface;

final class InstallmentOverdueMarker
{
    public function __construct(
        private readonly CreditRequestRepositoryInterface $creditRequestRepository,
        private readonly InstallmentPenaltyInterestUpdater $installmentPenaltyInterestUpdater,
    ) {}

    public function execute(\DateTimeImmutable $now): int
    {
        $overdueInstallments = $this->creditRequestRepository->findWithOverdueInstallments($now);

        foreach ($overdueInstallments as $installment) {
            $installment->markOverdue();
            $this->installmentPenaltyInterestUpdater->execute($installment, $now);
            $this->creditRequestRepository->saveInstallment($installment);
        }

        return count($overdueInstallments);
    }
}
